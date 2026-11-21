<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Transaction;
use App\Support\ReportFilters;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** RP-07: ekspor laporan ke XLSX, satu sheet per laporan. */
class ReportExporter
{
    private Style $bold;
    private Style $money;

    public function __construct(private ReportService $reports)
    {
        $this->bold = (new Style())->setFontBold();
        $this->money = (new Style())->setFormat('#,##0');
    }

    public function write(ReportFilters $f, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $summary = $this->reports->summary($f);
        $writer->getCurrentSheet()->setName('Ringkasan');
        $this->rows($writer, [
            ['Laporan Penjualan INBIS POS'],
            ['Filter', $f->describe()],
            ['Dicetak', now()->format('d/m/Y H:i')],
            [],
            ['Jumlah transaksi berhasil', $summary['count']],
            ['Total pendapatan (Rp)', $summary['revenue']],
            ['Unit terjual', $summary['units']],
            ['Rata-rata per transaksi (Rp)', $summary['average']],
            ['Transaksi dibatalkan', $summary['void_count']],
            ['Nilai transaksi dibatalkan (Rp)', $summary['void_amount']],
        ]);
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Metode pembayaran', 'Jumlah transaksi', 'Total (Rp)'], $this->bold));
        foreach ($this->reports->paymentRecap($f) as $r) {
            $writer->addRow(Row::fromValuesWithStyles([$r->label, $r->count, $r->total], null, [2 => $this->money]));
        }

        $this->sheet($writer, 'Transaksi', ['No. transaksi', 'Waktu', 'Kegiatan', 'Kasir', 'Item', 'Total (Rp)', 'Metode', 'Diterima (Rp)', 'Kembali (Rp)', 'Status']);
        $this->transactions($f, Transaction::PAID, function (Transaction $t) use ($writer) {
            $writer->addRow(Row::fromValuesWithStyles([
                $t->number, $t->transacted_at->format('Y-m-d H:i:s'), $t->event->name, $t->cashier->name, $t->item_count,
                $t->total, $t->paymentMethodLabel(), $t->amount_paid, $t->change_amount, $t->statusLabel(),
            ], null, [5 => $this->money, 7 => $this->money, 8 => $this->money]));
        });

        $this->sheet($writer, 'Per Produk', ['Kode', 'Produk', 'Satuan', 'Harga (Rp)', 'Qty terjual', 'Total (Rp)']);
        foreach ($this->reports->productSales($f) as $r) {
            $writer->addRow(Row::fromValuesWithStyles([$r->code, $r->name, $r->unit, $r->unit_price, $r->quantity, $r->total], null, [3 => $this->money, 5 => $this->money]));
        }

        $this->tenantSheet($writer, $f);

        if ($event = $f->event()) {
            $this->sheet($writer, 'Stok', ['Kode', 'Produk', 'Satuan', 'Harga (Rp)', 'Stok awal', 'Masuk', 'Terjual', 'Keluar/penyesuaian', 'Stok akhir', 'Min.']);
            foreach ($this->reports->stockReport($event) as $r) {
                $writer->addRow(Row::fromValuesWithStyles([$r->code, $r->name, $r->unit, $r->price, $r->opening, $r->in, $r->sold, $r->out, $r->available, $r->min], null, [3 => $this->money]));
            }
        }

        $this->sheet($writer, 'Dibatalkan', ['No. transaksi', 'Waktu', 'Kegiatan', 'Kasir', 'Total (Rp)', 'Metode', 'Dibatalkan', 'Oleh', 'Alasan']);
        $this->transactions($f, Transaction::VOID, function (Transaction $t) use ($writer) {
            $writer->addRow(Row::fromValuesWithStyles([
                $t->number, $t->transacted_at->format('Y-m-d H:i:s'), $t->event->name, $t->cashier->name, $t->total,
                $t->paymentMethodLabel(), $t->voided_at?->format('Y-m-d H:i:s'), $t->voidedBy?->name, $t->void_reason,
            ], null, [4 => $this->money]));
        });

        $writer->close();
    }

    /** Laporan detail satu kegiatan: ringkasan, rekap per tenant/dosen, dan rincian produk per tenant. */
    public function writeEvent(Event $event, ReportFilters $f, string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $summary = $this->reports->summary($f);
        $detail = $this->reports->eventTenantDetail($event, $f);
        $period = ($f->from || $f->to)
            ? ($f->from?->format('d/m/Y') ?? '…').' – '.($f->to?->format('d/m/Y') ?? '…')
            : $event->start_date->format('d/m/Y').' – '.$event->end_date->format('d/m/Y');

        $writer->getCurrentSheet()->setName('Ringkasan');
        $this->rows($writer, [
            ['Laporan Detail Kegiatan — INBIS POS'],
            ['Kegiatan', "{$event->name} ({$event->code})"],
            ['Lokasi', $event->location],
            ['Periode', $period],
            ['Status', $event->statusLabel()],
            ['Dicetak', now()->format('d/m/Y H:i')],
            [],
            ['Jumlah transaksi berhasil', $summary['count']],
            ['Total pendapatan (Rp)', $summary['revenue']],
            ['Unit terjual', $summary['units']],
            ['Transaksi dibatalkan', $summary['void_count']],
            ['Nilai transaksi dibatalkan (Rp)', $summary['void_amount']],
            ['Jumlah tenant/dosen', $detail->groups->whereNotNull('tenant')->count()],
        ]);
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Metode pembayaran', 'Jumlah transaksi', 'Total (Rp)'], $this->bold));
        foreach ($this->reports->paymentRecap($f) as $r) {
            $writer->addRow(Row::fromValuesWithStyles([$r->label, $r->count, $r->total], null, [2 => $this->money]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Tanggal', 'Jumlah transaksi', 'Total (Rp)'], $this->bold));
        foreach ($this->reports->dailySales($f) as $r) {
            $writer->addRow(Row::fromValuesWithStyles([$r->day->format('d/m/Y'), $r->count, $r->total], null, [2 => $this->money]));
        }

        $this->sheet($writer, 'Per Tenant', ['Kode', 'Tenant/Dosen', 'Jenis', 'Unit/Fakultas', 'Rekening', 'Jumlah produk', 'Qty terjual', 'Sisa stok', 'Total penjualan (Rp)']);
        foreach ($detail->groups as $g) {
            $writer->addRow(Row::fromValuesWithStyles([
                $g->tenant?->code ?? '-', $g->tenant?->name ?? 'Tanpa pemilik', $g->tenant?->typeLabel() ?? '-',
                $g->tenant?->unit ?? '', $g->tenant?->bankAccount() ?? '', $g->products, $g->quantity, $g->available, $g->total,
            ], null, [8 => $this->money]));
        }
        $writer->addRow(Row::fromValuesWithStyles(['', 'TOTAL', '', '', '', $detail->groups->sum('products'), $detail->quantity, $detail->groups->sum('available'), $detail->total], $this->bold, [8 => $this->money]));

        $this->sheet($writer, 'Detail Produk', ['Kode tenant', 'Tenant/Dosen', 'Kode produk', 'Produk', 'Satuan', 'Harga (Rp)', 'Stok diterima', 'Qty terjual', 'Keluar/retur', 'Sisa stok', 'Total penjualan (Rp)']);
        foreach ($detail->groups as $g) {
            foreach ($g->rows as $r) {
                $writer->addRow(Row::fromValuesWithStyles([
                    $g->tenant?->code ?? '-', $g->tenant?->name ?? 'Tanpa pemilik', $r->code, $r->name, $r->unit, $r->price,
                    $r->has_stock ? $r->received : '', $r->quantity, $r->has_stock ? $r->out : '', $r->has_stock ? $r->available : '', $r->total,
                ], null, [5 => $this->money, 10 => $this->money]));
            }
        }

        $this->sheet($writer, 'Transaksi', ['No. transaksi', 'Waktu', 'Kasir', 'Item', 'Total (Rp)', 'Metode', 'Status', 'Alasan batal']);
        $f->apply(Transaction::query())
            ->with(['cashier:id,name'])
            ->orderBy('transacted_at')
            ->chunk(500, fn ($chunk) => $chunk->each(fn (Transaction $t) => $writer->addRow(Row::fromValuesWithStyles([
                $t->number, $t->transacted_at->format('Y-m-d H:i:s'), $t->cashier->name, $t->item_count, $t->total,
                $t->paymentMethodLabel(), $t->statusLabel(), $t->void_reason,
            ], null, [4 => $this->money]))));

        $writer->close();
    }

    private function tenantSheet(Writer $writer, ReportFilters $f): void
    {
        $this->sheet($writer, 'Per Tenant', ['Kode', 'Tenant/Dosen', 'Jenis', 'Unit/Fakultas', 'Jumlah produk', 'Transaksi', 'Qty terjual', 'Total (Rp)']);
        foreach ($this->reports->tenantSales($f) as $r) {
            $writer->addRow(Row::fromValuesWithStyles([$r->code ?? '-', $r->name, $r->type ?? '-', $r->unit ?? '', $r->products, $r->transactions, $r->quantity, $r->total], null, [7 => $this->money]));
        }
    }

    private function sheet(Writer $writer, string $name, array $header): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName($name);
        $writer->addRow(Row::fromValues($header, $this->bold));
    }

    private function rows(Writer $writer, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $writer->addRow(Row::fromValues($row, $i === 0 ? $this->bold : null));
        }
    }

    private function transactions(ReportFilters $f, string $status, callable $each): void
    {
        $f->apply(Transaction::query())
            ->with(['event:id,name', 'cashier:id,name', 'voidedBy:id,name'])
            ->where('status', $status)
            ->orderBy('transacted_at')
            ->chunk(500, fn ($chunk) => $chunk->each($each));
    }
}
