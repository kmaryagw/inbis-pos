<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('end_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->withCount('eventProducts')
            ->orderByRaw("FIELD(status, 'active', 'draft', 'closed')")
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return view('admin.events.index', compact('events'));
    }

    public function create()
    {
        return view('admin.events.form', ['event' => new Event(['start_date' => today(), 'end_date' => today()])]);
    }

    public function store(Request $request)
    {
        $event = new Event($this->validated($request));
        $event->status = Event::DRAFT;
        $event->save();

        return redirect()->route('admin.events.show', $event)->with('success', 'Kegiatan dibuat dengan status Draft.');
    }

    public function show(Event $event, StockService $stock)
    {
        $event->load(['eventProducts' => fn ($q) => $q->with('product.category')->orderBy('id'), 'cashiers']);

        $sales = $event->transactions()
            ->where('status', Transaction::PAID)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(item_count), 0) as units')
            ->first();

        return view('admin.events.show', [
            'event' => $event,
            'summary' => $stock->summaryForEvent($event->id),
            'sales' => $sales,
            'availableProducts' => Product::where('is_active', true)
                ->whereNotIn('id', $event->eventProducts->pluck('product_id'))
                ->orderBy('name')
                ->get(),
            'allCashiers' => User::where('role', User::ROLE_CASHIER)->where('is_active', true)->orderBy('name')->get(),
            'blockers' => $event->isDraft() ? $event->activationBlockers() : [],
        ]);
    }

    public function edit(Event $event)
    {
        abort_if($event->isClosed(), 403, 'Kegiatan yang sudah selesai tidak dapat diubah.');

        return view('admin.events.form', compact('event'));
    }

    public function update(Request $request, Event $event)
    {
        abort_if($event->isClosed(), 403, 'Kegiatan yang sudah selesai tidak dapat diubah.');

        $data = $this->validated($request, $event);

        // Kode kegiatan dipakai pada nomor transaksi; hanya boleh diubah saat Draft
        if (! $event->isDraft()) {
            unset($data['code']);
        }

        $event->update($data);

        return redirect()->route('admin.events.show', $event)->with('success', 'Kegiatan berhasil diperbarui.');
    }

    public function destroy(Event $event)
    {
        if (! $event->isDraft() || $event->transactions()->exists()) {
            return back()->with('error', 'Hanya kegiatan Draft tanpa transaksi yang dapat dihapus.');
        }

        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Kegiatan dihapus.');
    }

    public function activate(Event $event)
    {
        if (! $event->isDraft()) {
            return back()->with('error', 'Hanya kegiatan Draft yang dapat diaktifkan.');
        }

        if ($blockers = $event->activationBlockers()) {
            return back()->with('error', 'Kegiatan belum dapat diaktifkan: '.implode(' ', $blockers));
        }

        $event->forceFill(['status' => Event::ACTIVE, 'activated_at' => now()])->save();

        return back()->with('success', 'Kegiatan aktif dan siap digunakan kasir.');
    }

    public function close(Event $event)
    {
        if (! $event->isActive()) {
            return back()->with('error', 'Hanya kegiatan Aktif yang dapat ditutup.');
        }

        $event->forceFill(['status' => Event::CLOSED, 'closed_at' => now()])->save();

        return back()->with('success', 'Kegiatan ditutup. Transaksi baru tidak lagi diterima.');
    }

    public function syncCashiers(Request $request, Event $event)
    {
        abort_if($event->isClosed(), 403);

        $data = $request->validate([
            'cashiers' => ['array'],
            'cashiers.*' => [Rule::exists('users', 'id')->where('role', User::ROLE_CASHIER)],
        ]);

        $event->cashiers()->sync($data['cashiers'] ?? []);

        return back()->with('success', 'Penugasan kasir diperbarui.');
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $request->merge(['code' => strtoupper((string) $request->code)]);

        return $request->validate([
            'code' => ['required', 'alpha_num:ascii', 'min:2', 'max:12', Rule::unique('events')->ignore($event)],
            'name' => ['required', 'string', 'max:150', Rule::unique('events')->ignore($event)],
            'location' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'person_in_charge' => ['required', 'string', 'max:100'],
            'receipt_header' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:150'],
        ], [], [
            'code' => 'kode kegiatan',
            'name' => 'nama kegiatan',
            'location' => 'lokasi',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
            'person_in_charge' => 'penanggung jawab',
        ]);
    }
}
