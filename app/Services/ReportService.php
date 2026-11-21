<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\ReportFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Angka laporan hanya menghitung transaksi berhasil; transaksi batal disajikan terpisah (BR-12).
 */
class ReportService
{
    public function __construct(private StockService $stock)
    {
    }

    /** RP-01: indikator dashboard. */
    public function summary(ReportFilters $f): array
    {
        $paid = $f->apply(DB::table('transactions'))
            ->where('status', Transaction::PAID)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $void = $f->apply(DB::table('transactions'))
            ->where('status', Transaction::VOID)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as amount')
            ->first();

        $units = (int) $this->detailQuery($f)->sum('td.quantity');

        $stock = $this->stockScope($f);

        return [
            'count' => (int) $paid->count,
            'revenue' => (int) $paid->revenue,
            'units' => $units,
            'average' => $paid->count ? intdiv((int) $paid->revenue, (int) $paid->count) : 0,
            'void_count' => (int) $void->count,
            'void_amount' => (int) $void->amount,
            'stock_available' => (int) (clone $stock)->sum('current_stock'),
            'low_stock' => (clone $stock)->whereColumn('current_stock', '<=', 'min_stock')->count(),
        ];
    }

    /** RP-03: penjualan per produk (dipisah per harga bila harga berubah). */
    public function productSales(ReportFilters $f): Collection
    {
        return $this->detailQuery($f)
            ->join('products as p', 'p.id', '=', 'ep.product_id')
            ->groupBy('p.id', 'p.code', 'p.name', 'p.unit', 'td.unit_price')
            ->selectRaw('p.id as product_id, p.code, p.name, p.unit, td.unit_price, SUM(td.quantity) as quantity, SUM(td.subtotal) as total')
            ->orderByDesc('total')
            ->orderBy('p.name')
            ->get()
            ->map(fn ($r) => (object) [
                'product_id' => (int) $r->product_id, 'code' => $r->code, 'name' => $r->name, 'unit' => $r->unit,
                'unit_price' => (int) $r->unit_price, 'quantity' => (int) $r->quantity, 'total' => (int) $r->total,
            ]);
    }

    /** RP-04: rekap metode pembayaran; Tunai & QRIS selalu tampil. */
    public function paymentRecap(ReportFilters $f): Collection
    {
        $rows = $f->apply(DB::table('transactions'))
            ->where('status', Transaction::PAID)
            ->groupBy('payment_method')
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
            ->get()
            ->keyBy('payment_method');

        return collect(Transaction::PAYMENT_METHODS)->map(fn ($label, $method) => (object) [
            'method' => $method,
            'label' => $label,
            'count' => (int) ($rows[$method]->count ?? 0),
            'total' => (int) ($rows[$method]->total ?? 0),
        ])->values();
    }

    /** RP-09: perbandingan pendapatan antar kegiatan. */
    public function eventComparison(ReportFilters $f): Collection
    {
        return $f->apply(DB::table('transactions'))
            ->join('events as e', 'e.id', '=', 'transactions.event_id')
            ->where('transactions.status', Transaction::PAID)
            ->groupBy('e.id', 'e.name', 'e.status', 'e.start_date')
            ->selectRaw('e.id, e.name, e.status, e.start_date, COUNT(*) as count, SUM(transactions.total) as total')
            ->orderByDesc('e.start_date')
            ->get();
    }

    /** Rekap penjualan per pemilik (tenant/dosen), berdasarkan pemilik saat barang terjual. */
    public function tenantSales(ReportFilters $f): Collection
    {
        return $this->detailQuery($f)
            ->leftJoin('tenants as tn', 'tn.id', '=', 'td.tenant_id')
            ->groupBy('td.tenant_id', 'tn.code', 'tn.name', 'tn.type', 'tn.unit')
            ->selectRaw('td.tenant_id, tn.code, tn.name, tn.type, tn.unit,
                COUNT(DISTINCT ep.product_id) as products, COUNT(DISTINCT t.id) as transactions,
                SUM(td.quantity) as quantity, SUM(td.subtotal) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => (object) [
                'tenant_id' => $r->tenant_id ? (int) $r->tenant_id : null,
                'code' => $r->code,
                'name' => $r->name ?? 'Tanpa pemilik',
                'type' => $r->type ? Tenant::TYPES[$r->type] ?? $r->type : null,
                'unit' => $r->unit,
                'products' => (int) $r->products,
                'transactions' => (int) $r->transactions,
                'quantity' => (int) $r->quantity,
                'total' => (int) $r->total,
            ]);
    }

    /** Pendapatan per hari (untuk kegiatan beberapa hari). */
    public function dailySales(ReportFilters $f): Collection
    {
        return $f->apply(DB::table('transactions'))
            ->where('status', Transaction::PAID)
            ->groupByRaw('DATE(transacted_at)')
            ->selectRaw('DATE(transacted_at) as day, COUNT(*) as count, SUM(total) as total')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => (object) ['day' => \Carbon\Carbon::parse($r->day), 'count' => (int) $r->count, 'total' => (int) $r->total]);
    }

    /**
     * Laporan detail satu kegiatan, dikelompokkan per pemilik lalu per produk.
     * Penjualan mengikuti filter tanggal; kolom stok adalah kondisi saat ini.
     * Stok dicantumkan pada baris pemilik produk saat ini (bila kepemilikan pernah berpindah,
     * penjualan lama tetap tercatat pada pemilik lama).
     */
    public function eventTenantDetail(Event $event, ReportFilters $f, ?int $onlyTenantId = null): object
    {
        $sales = $this->detailQuery($f)
            ->where('t.event_id', $event->id)
            ->groupBy('td.tenant_id', 'td.event_product_id')
            ->selectRaw('td.tenant_id, td.event_product_id, SUM(td.quantity) as quantity, SUM(td.subtotal) as total, COUNT(DISTINCT td.unit_price) as prices')
            ->get();

        $eventProducts = EventProduct::with('product.tenant')->where('event_id', $event->id)->get()->keyBy('id');
        $stock = $this->stock->summaryForEvent($event->id);

        $makeRow = function (?int $tenantId, EventProduct $ep, int $qty, int $total, bool $multiPrice) use ($stock) {
            $owner = $ep->product->tenant_id;
            $s = $stock[$ep->id] ?? ['opening' => 0, 'in' => 0, 'sold' => 0, 'out' => 0];

            return (object) [
                'tenant_id' => $tenantId,
                'ep_id' => $ep->id,
                'code' => $ep->product->code,
                'name' => $ep->product->name,
                'unit' => $ep->product->unit,
                'price' => $ep->selling_price,
                'multi_price' => $multiPrice,
                'quantity' => $qty,
                'total' => $total,
                'has_stock' => $tenantId === $owner,
                'received' => $s['opening'] + $s['in'],
                'out' => $s['out'],
                'available' => $ep->current_stock,
            ];
        };

        $rows = collect();
        foreach ($sales as $s) {
            $ep = $eventProducts[$s->event_product_id];
            $rows->push($makeRow($s->tenant_id ? (int) $s->tenant_id : null, $ep, (int) $s->quantity, (int) $s->total, $s->prices > 1));
        }
        // Produk tanpa penjualan (atau yang pemiliknya kini berbeda) tetap tampil untuk pemilik saat ini
        foreach ($eventProducts as $ep) {
            if (! $rows->contains(fn ($r) => $r->has_stock && $r->ep_id === $ep->id)) {
                $rows->push($makeRow($ep->product->tenant_id, $ep, 0, 0, false));
            }
        }

        if ($onlyTenantId !== null) {
            $rows = $rows->where('tenant_id', $onlyTenantId);
        }

        $tenants = Tenant::whereIn('id', $rows->pluck('tenant_id')->filter()->unique())->get()->keyBy('id');

        $groups = $rows->groupBy(fn ($r) => $r->tenant_id ?? 0)
            ->map(fn ($items, $key) => (object) [
                'tenant' => $key ? $tenants[$key] : null,
                'rows' => $items->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
                'products' => $items->count(),
                'quantity' => $items->sum('quantity'),
                'total' => $items->sum('total'),
                'available' => $items->where('has_stock', true)->sum('available'),
            ])
            // Urut: pendapatan terbesar dulu, "Tanpa pemilik" paling akhir
            ->sortBy([fn ($a, $b) => ($a->tenant === null) <=> ($b->tenant === null), fn ($a, $b) => $b->total <=> $a->total])
            ->values();

        return (object) [
            'groups' => $groups,
            'quantity' => $groups->sum('quantity'),
            'total' => $groups->sum('total'),
        ];
    }

    /** RP-05: stok per produk untuk satu kegiatan (kondisi saat ini, tidak terpengaruh filter tanggal). */
    public function stockReport(Event $event): Collection
    {
        $summary = $this->stock->summaryForEvent($event->id);

        return EventProduct::with('product')
            ->where('event_id', $event->id)
            ->get()
            ->sortBy('product.name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (EventProduct $ep) use ($summary) {
                $s = $summary[$ep->id] ?? ['opening' => 0, 'in' => 0, 'sold' => 0, 'out' => 0];

                return (object) [
                    'code' => $ep->product->code,
                    'name' => $ep->product->name,
                    'unit' => $ep->product->unit,
                    'price' => $ep->selling_price,
                    'opening' => $s['opening'],
                    'in' => $s['in'],
                    'sold' => $s['sold'],
                    'out' => $s['out'],
                    'available' => $ep->current_stock,
                    'min' => $ep->min_stock,
                    'low' => $ep->is_active && $ep->isLowStock(),
                    'active' => $ep->is_active,
                ];
            })
            ->values();
    }

    /** Detail item dari transaksi berhasil, dengan filter produk dibatasi ke baris produk itu. */
    private function detailQuery(ReportFilters $f): Builder
    {
        return $f->apply(DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->join('event_products as ep', 'ep.id', '=', 'td.event_product_id')
            ->where('t.status', Transaction::PAID), 't')
            ->when($f->productId, fn ($q, $pid) => $q->where('ep.product_id', $pid));
    }

    /** Alokasi produk aktif yang relevan untuk indikator stok. */
    private function stockScope(ReportFilters $f)
    {
        return EventProduct::query()
            ->where('is_active', true)
            ->when($f->eventId,
                fn ($q, $id) => $q->where('event_id', $id),
                fn ($q) => $q->whereIn('event_id', Event::where('status', Event::ACTIVE)->select('id')))
            ->when($f->productId, fn ($q, $pid) => $q->where('product_id', $pid));
    }
}
