<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportExporter;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    /** RP-01: dashboard. Default ke kegiatan aktif bila hanya ada satu. */
    public function dashboard(Request $request)
    {
        if (! $request->has('event')) {
            $active = Event::where('status', Event::ACTIVE)->pluck('id');
            if ($active->count() === 1) {
                return redirect()->route('dashboard', ['event' => $active->first()]);
            }
        }

        $filters = ReportFilters::fromRequest($request, $request->user());
        $event = $filters->event();

        return view('reports.dashboard', [
            'filters' => $filters,
            'event' => $event,
            'events' => Event::orderByRaw("FIELD(status, 'active', 'draft', 'closed')")->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'summary' => $this->reports->summary($filters),
            'payments' => $this->reports->paymentRecap($filters),
            'topProducts' => $this->reports->productSales($filters)->take(5),
            'comparison' => $event ? collect() : $this->reports->eventComparison($filters),
            'lowStock' => $event ? $this->reports->stockReport($event)->where('low', true)->values() : collect(),
            'recent' => $filters->apply(Transaction::query())->with('cashier:id,name')->latest('transacted_at')->latest('id')->limit(8)->get(),
        ]);
    }

    /** RP-02..RP-06, RP-08: laporan dengan tab, ramah cetak. */
    public function index(Request $request)
    {
        $filters = ReportFilters::fromRequest($request, $request->user());
        $event = $filters->event();
        $tab = in_array($request->tab, ['sales', 'products', 'payments', 'stock'], true) ? $request->tab : 'sales';

        $data = [
            'filters' => $filters,
            'event' => $event,
            'tab' => $tab,
            'summary' => $this->reports->summary($filters),
            'events' => Event::orderByDesc('start_date')->get(['id', 'name', 'status']),
            'cashiers' => User::whereIn('role', [User::ROLE_CASHIER, User::ROLE_ADMIN])->orderBy('name')->get(['id', 'name']),
            'products' => Product::orderBy('name')->get(['id', 'code', 'name']),
        ];

        $data += match ($tab) {
            'sales' => [
                'transactions' => $filters->apply(Transaction::query())->with('cashier:id,name', 'event:id,name')
                    ->where('status', Transaction::PAID)->orderBy('transacted_at')->paginate(100)->withQueryString(),
                'voided' => $filters->apply(Transaction::query())->with('cashier:id,name', 'voidedBy:id,name')
                    ->where('status', Transaction::VOID)->orderBy('transacted_at')->get(),
            ],
            'products' => ['productSales' => $this->reports->productSales($filters)],
            'payments' => ['payments' => $this->reports->paymentRecap($filters)],
            'stock' => ['stock' => $event ? $this->reports->stockReport($event) : null],
        };

        return view('reports.index', $data);
    }

    public function export(Request $request, ReportExporter $exporter)
    {
        $filters = ReportFilters::fromRequest($request, $request->user());

        $name = 'laporan-'.Str::slug($filters->event()?->code ?? 'semua-kegiatan').'-'.now()->format('Ymd-His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'rpt');
        $exporter->write($filters, $path);

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
