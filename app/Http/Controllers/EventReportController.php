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

    public function index()
    {
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

        $events = Event::orderByRaw("FIELD(status, 'active', 'closed', 'draft')")->orderByDesc('start_date')->get();

        return view('reports.events.index', compact('events', 'sales', 'tenants'));
    }

    public function show(Request $request, Event $event)
    {
        $filters = ReportFilters::forEvent($request, $event);

        return view('reports.events.show', [
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
        $filters = ReportFilters::forEvent($request, $event);
        $group = $this->reports->eventTenantDetail($event, $filters, $tenant->id)->groups->first();

        return view('reports.events.tenant', compact('event', 'tenant', 'filters', 'group'));
    }

    public function export(Request $request, Event $event, ReportExporter $exporter)
    {
        $filters = ReportFilters::forEvent($request, $event);

        $name = 'laporan-kegiatan-'.Str::slug($event->code).'-'.now()->format('Ymd-His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $exporter->writeEvent($event, $filters, $path);

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
