<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Transaction;
use App\Services\CheckoutService;
use App\Services\EscPosReceipt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    /** Pilih kegiatan aktif; langsung masuk bila hanya ada satu. */
    public function index(Request $request)
    {
        $user = $request->user();

        $events = Event::where('status', Event::ACTIVE)
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('cashiers', fn ($q) => $q->whereKey($user->id)))
            ->orderBy('name')
            ->get();

        if ($events->count() === 1 && ! $request->boolean('pilih')) {
            return redirect()->route('pos.show', $events->first());
        }

        return view('pos.index', compact('events'));
    }

    public function show(Request $request, Event $event)
    {
        $this->authorizeSale($request, $event);

        return view('pos.show', [
            'event' => $event,
            'products' => $this->productPayload($event),
        ]);
    }

    /** Data stok terbaru untuk sinkronisasi layar kasir. */
    public function products(Request $request, Event $event)
    {
        $this->authorizeSale($request, $event);

        return response()->json(['products' => $this->productPayload($event)]);
    }

    public function checkout(Request $request, Event $event, CheckoutService $checkout, EscPosReceipt $escpos)
    {
        $this->authorizeSale($request, $event);

        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'payment_method' => ['required', Rule::in(array_keys(Transaction::PAYMENT_METHODS))],
            'amount_paid' => ['required_if:payment_method,cash', 'nullable', 'integer', 'min:0'],
            'expected_total' => ['nullable', 'integer', 'min:0'],
        ]);

        $items = collect($data['items'])->mapWithKeys(fn ($i) => [(int) $i['id'] => (int) $i['qty']])->all();

        $transaction = $checkout->checkout(
            $event,
            $request->user(),
            $items,
            $data['payment_method'],
            (int) ($data['amount_paid'] ?? 0),
            $data['idempotency_key'],
            isset($data['expected_total']) ? (int) $data['expected_total'] : null,
        );

        return response()->json([
            'transaction' => [
                'id' => $transaction->id,
                'number' => $transaction->number,
                'total' => $transaction->total,
                'amount_paid' => $transaction->amount_paid,
                'change' => $transaction->change_amount,
                'payment_method' => $transaction->paymentMethodLabel(),
                'receipt_url' => route('receipts.show', $transaction),
                // Untuk cetak Bluetooth via RawBT tanpa request tambahan
                'escpos' => $escpos->base64($transaction),
            ],
            'stock' => EventProduct::where('event_id', $event->id)->pluck('current_stock', 'id'),
        ]);
    }

    private function authorizeSale(Request $request, Event $event): void
    {
        abort_unless($event->isActive(), 403, 'Kegiatan tidak aktif.');
        abort_unless($request->user()->canSellAt($event), 403, 'Anda tidak ditugaskan pada kegiatan ini.');
    }

    private function productPayload(Event $event): array
    {
        return EventProduct::with('product.category')
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->get()
            ->sortBy('product.name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (EventProduct $ep) => [
                'id' => $ep->id,
                'code' => $ep->product->code,
                'name' => $ep->product->name,
                'category' => $ep->product->category?->name,
                'unit' => $ep->product->unit,
                'price' => $ep->selling_price,
                'stock' => $ep->current_stock,
                'min' => $ep->min_stock,
                'photo' => $ep->product->photoUrl(),
            ])
            ->values()
            ->all();
    }
}
