<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventProduct;
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
