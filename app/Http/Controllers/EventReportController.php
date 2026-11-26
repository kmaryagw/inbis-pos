<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\ReportExporter;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Laporan detail per kegiatan dengan rekap per tenant/dosen. */
class EventReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $visible = $request->user()->visibleEvents();

        $sales = DB::table('transactions')
            ->where('status', Transaction::PAID)
            ->groupBy('event_id')
            ->selectRaw('event_id, COUNT(*) as count, SUM(total) as total')
            ->get()
            ->keyBy('event_id');

        $tenants = DB::table('event_products as ep')
            ->join('products as p', 'p.id', '=', 'ep.product_id')
            ->whereNotNull('p.tenant_id')
            ->groupBy('ep.event_id')
            ->selectRaw('ep.event_id, COUNT(DISTINCT p.tenant_id) as tenants')
            ->pluck('tenants', 'event_id');

        $events = $visible->orderByRaw("FIELD(status, 'active', 'closed', 'draft')")->orderByDesc('start_date')->get();

        return view('reports.events.index', compact('events', 'sales', 'tenants'));
    }

    public function show(Request $request, Event $event)
    {
        $this->authorizeEvent($request, $event);
        $filters = ReportFilters::forEvent($request, $event);
        $status = in_array($request->status, [Transaction::PAID, Transaction::VOID], true) ? $request->status : null;

        return view('reports.events.show', [
            'status' => $status,
            'transactions' => $filters->apply(Transaction::query())
                ->with('cashier:id,name')
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->orderBy('transacted_at')
                ->orderBy('id')
                ->paginate(50, pageName: 'trx')
                ->withQueryString()
                ->fragment('transaksi'),
            'event' => $event,
            'filters' => $filters,
            'summary' => $this->reports->summary($filters),
            'payments' => $this->reports->paymentRecap($filters),
            'daily' => $this->reports->dailySales($filters),
            'detail' => $this->reports->eventTenantDetail($event, $filters),
        ]);
    }

    /** Rekap satu tenant/dosen untuk satu kegiatan, siap cetak sebagai bukti serah terima. */
    public function tenant(Request $request, Event $event, Tenant $tenant)
    {
        $this->authorizeEvent($request, $event);
        $filters = ReportFilters::forEvent($request, $event);
        $group = $this->reports->eventTenantDetail($event, $filters, $tenant->id)->groups->first();

        // ?jenis=terjual: rekap ringkas hanya produk yang terjual (tanpa kolom stok)
        $soldOnly = $request->query('jenis') === 'terjual';
        if ($soldOnly && $group) {
            $group->rows = $group->rows->where('quantity', '>', 0)->values();
        }

        return view('reports.events.tenant', compact('event', 'tenant', 'filters', 'group', 'soldOnly'));
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        abort_unless($request->user()->canViewEvent($event), 403, 'Anda tidak memiliki akses ke kegiatan ini.');
    }

    public function export(Request $request, Event $event, ReportExporter $exporter)
    {
        $this->authorizeEvent($request, $event);
        $filters = ReportFilters::forEvent($request, $event);

        $name = 'laporan-kegiatan-'.Str::slug($event->code).'-'.now()->format('Ymd-His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $exporter->writeEvent($event, $filters, $path);

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
