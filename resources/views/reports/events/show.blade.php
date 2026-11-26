@extends('layouts.app')
@section('title', 'Laporan '.$event->name)

@php
    $dq = $filters->dateQuery();
    $period = ($filters->from || $filters->to)
        ? ($filters->from?->format('d/m/Y') ?? '…').' – '.($filters->to?->format('d/m/Y') ?? '…')
        : $event->start_date->format('d/m/Y').' – '.$event->end_date->format('d/m/Y');
@endphp

@section('content')
@include('reports._nav')

<div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
    <div>
        <a href="{{ route('reports.events.index') }}" class="small text-decoration-none no-print"><i class="bi bi-arrow-left"></i> Semua kegiatan</a>
        <h1 class="page-title mt-1">{{ $event->name }} <span class="badge {{ $event->statusBadgeClass() }} align-middle fs-6 no-print">{{ $event->statusLabel() }}</span></h1>
        <div class="text-muted small">{{ $event->code }} · {{ $event->location }} · {{ $period }} · PJ: {{ $event->person_in_charge }}</div>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
        <a href="{{ route('reports.events.export', [$event] + $dq) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Ekspor Excel</a>
    </div>
</div>

<div class="print-only mb-3">
    <div class="small">Laporan Detail Kegiatan — INBIS POS · Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</div>

<form class="row g-2 align-items-end mb-3 no-print" method="get">
    <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="f-from">Dari tanggal</label>
        <input class="form-control" type="date" id="f-from" name="from" value="{{ $filters->from?->toDateString() }}">
    </div>
    <div class="col-6 col-md-3">
        <label class="form-label small mb-1" for="f-to">Sampai tanggal</label>
        <input class="form-control" type="date" id="f-to" name="to" value="{{ $filters->to?->toDateString() }}">
    </div>
    <div class="col-12 col-md-6 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        @if ($dq)<a href="{{ route('reports.events.show', $event) }}" class="btn btn-light">Seluruh kegiatan</a>@endif
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
        <div class="stat-label">Total pendapatan</div><div class="stat-value num">{{ rupiah($summary['revenue']) }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
        <div class="stat-label">Transaksi berhasil</div><div class="stat-value num">{{ number_format($summary['count'], 0, ',', '.') }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
        <div class="stat-label">Unit terjual</div><div class="stat-value num">{{ number_format($summary['units'], 0, ',', '.') }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body py-2">
        <div class="stat-label">Dibatalkan (tidak dihitung)</div>
        <div class="stat-value num {{ $summary['void_count'] ? 'text-danger' : '' }}">{{ $summary['void_count'] }} <span class="fs-6 fw-normal">· {{ rupiah($summary['void_amount']) }}</span></div>
    </div></div></div>
</div>

{{-- Rekap per tenant/dosen --}}
<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">Rekap per tenant / dosen</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Tenant / Dosen</th><th class="text-end">Produk</th><th class="text-end">Qty terjual</th><th class="text-end">Sisa stok</th><th class="text-end">Total penjualan</th><th class="text-end">Porsi</th><th class="no-print"></th></tr></thead>
            <tbody>
            @php($grand = max(1, $detail->total))
            @forelse ($detail->groups as $g)
                <tr class="{{ $g->tenant ? '' : 'table-warning' }}">
                    <td>
                        @if ($g->tenant)
                            <a href="#tenant-{{ $g->tenant->id }}" class="fw-medium text-decoration-none">{{ $g->tenant->name }}</a>
                            <div class="small text-muted">{{ $g->tenant->code }} · {{ $g->tenant->typeLabel() }}@if ($g->tenant->unit) · {{ $g->tenant->unit }}@endif</div>
                        @else
                            <a href="#tenant-none" class="fw-medium text-decoration-none">Tanpa pemilik</a>
                            <div class="small text-muted">Tetapkan pemilik di menu Kepemilikan produk</div>
                        @endif
                    </td>
                    <td class="text-end num">{{ $g->products }}</td>
                    <td class="text-end num">{{ number_format($g->quantity, 0, ',', '.') }}</td>
                    <td class="text-end num">{{ number_format($g->available, 0, ',', '.') }}</td>
                    <td class="text-end num fw-semibold">{{ rupiah($g->total) }}</td>
                    <td class="text-end num">{{ round($g->total / $grand * 100, 1) }}%</td>
                    <td class="text-end no-print">
                        @if ($g->tenant)
                            <div class="btn-group btn-group-sm" role="group" aria-label="Rekap {{ $g->tenant->name }}">
                                <a href="{{ route('reports.events.tenant', [$event, $g->tenant] + $dq) }}" class="btn btn-outline-secondary text-nowrap" title="Semua produk beserta stok dan sisa"><i class="bi bi-printer me-1"></i>Rekap lengkap</a>
                                <a href="{{ route('reports.events.tenant', [$event, $g->tenant] + $dq + ['jenis' => 'terjual']) }}" class="btn btn-outline-secondary text-nowrap" title="Hanya produk yang terjual">Terjual</a>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk dialokasikan ke kegiatan ini.</td></tr>
            @endforelse
            </tbody>
            @if ($detail->groups->isNotEmpty())
                <tfoot><tr class="fw-bold">
                    <td>Total</td><td class="text-end num">{{ $detail->groups->sum('products') }}</td>
                    <td class="text-end num">{{ number_format($detail->quantity, 0, ',', '.') }}</td>
                    <td class="text-end num">{{ number_format($detail->groups->sum('available'), 0, ',', '.') }}</td>
                    <td class="text-end num">{{ rupiah($detail->total) }}</td><td></td><td class="no-print"></td>
                </tr></tfoot>
            @endif
        </table>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">Metode pembayaran</div>
            <table class="table mb-0">
                <tbody>
                @foreach ($payments as $p)
                    <tr><td>{{ $p->label }}</td><td class="text-end num">{{ $p->count }} trx</td><td class="text-end num fw-semibold">{{ rupiah($p->total) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">Penjualan per hari</div>
            <table class="table mb-0">
                <tbody>
                @forelse ($daily as $d)
                    <tr><td>{{ $d->day->translatedFormat('l, d/m/Y') }}</td><td class="text-end num">{{ $d->count }} trx</td><td class="text-end num fw-semibold">{{ rupiah($d->total) }}</td></tr>
                @empty
                    <tr><td class="text-center text-muted py-3">Belum ada penjualan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Rincian produk per tenant/dosen --}}
<h2 class="h5 mt-4 mb-3">Rincian produk per tenant / dosen</h2>
@foreach ($detail->groups as $g)
    <div class="card mb-3 break-avoid" id="tenant-{{ $g->tenant?->id ?? 'none' }}">
        <div class="card-header bg-transparent d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div>
                <span class="fw-semibold">{{ $g->tenant?->name ?? 'Tanpa pemilik' }}</span>
                @if ($g->tenant)
                    <span class="badge {{ $g->tenant->typeBadgeClass() }}">{{ $g->tenant->typeLabel() }}</span>
                    <span class="small text-muted ms-1">{{ $g->tenant->code }}@if ($g->tenant->bankAccount()) · {{ $g->tenant->bankAccount() }}@endif</span>
                @endif
            </div>
            <span class="fw-semibold num">{{ rupiah($g->total) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Kode</th><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Diterima</th><th class="text-end">Terjual</th><th class="text-end">Keluar/retur</th><th class="text-end">Sisa</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @foreach ($g->rows as $r)
                    <tr>
                        <td class="text-nowrap small">{{ $r->code }}</td>
                        <td>{{ $r->name }}</td>
                        <td class="text-end num">{{ rupiah($r->price) }}@if ($r->multi_price)<span class="text-muted" title="Harga pernah berubah; total mengikuti harga saat terjual">*</span>@endif</td>
                        <td class="text-end num">{{ $r->has_stock ? $r->received : '—' }}</td>
                        <td class="text-end num">{{ $r->quantity }} <span class="small text-muted">{{ $r->unit }}</span></td>
                        <td class="text-end num">{{ $r->has_stock ? $r->out : '—' }}</td>
                        <td class="text-end num">{{ $r->has_stock ? $r->available : '—' }}</td>
                        <td class="text-end num">{{ rupiah($r->total) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td colspan="4" class="text-end">Subtotal</td><td class="text-end num">{{ $g->quantity }}</td><td></td><td class="text-end num">{{ $g->available }}</td><td class="text-end num">{{ rupiah($g->total) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
@endforeach

{{-- Daftar transaksi kegiatan --}}
<div class="card mb-3 mt-4" id="transaksi">
    <div class="card-header bg-transparent d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span class="fw-semibold">Transaksi <span class="fw-normal text-muted small">({{ number_format($transactions->total(), 0, ',', '.') }})</span></span>
        <ul class="nav nav-pills nav-sm small no-print">
            @foreach (['' => 'Semua', 'paid' => 'Berhasil', 'void' => 'Batal'] as $value => $label)
                <li class="nav-item">
                    <a class="nav-link py-1 px-2 {{ ($status ?? '') === $value ? 'active' : '' }}"
                       href="{{ route('reports.events.show', [$event] + $dq + array_filter(['status' => $value])) }}#transaksi">{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>No. transaksi</th><th>Waktu</th><th>Kasir</th><th>Metode</th><th class="text-end">Item</th><th class="text-end">Total</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr class="{{ $t->isVoid() ? 'text-muted' : '' }}">
                    <td class="font-monospace small text-nowrap"><a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">{{ $t->number }}</a></td>
                    <td class="text-nowrap">{{ $t->transacted_at->format('d/m H:i') }}</td>
                    <td>{{ $t->cashier->name }}</td>
                    <td>{{ $t->paymentMethodLabel() }}</td>
                    <td class="text-end num">{{ $t->item_count }}</td>
                    <td class="text-end num {{ $t->isVoid() ? 'text-decoration-line-through' : 'fw-semibold' }}">{{ rupiah($t->total) }}</td>
                    <td>
                        @if ($t->isVoid())
                            <span class="badge text-bg-danger" title="{{ $t->void_reason }}">Batal</span>
                        @else
                            <span class="badge text-bg-success">Berhasil</span>
                        @endif
                    </td>
                    <td class="small" style="max-width: 260px">
                        @if ($t->note)<span class="text-warning-emphasis"><i class="bi bi-sticky me-1"></i>{{ \Illuminate\Support\Str::limit($t->note, 80) }}</span>@endif
                        @if ($t->isVoid())<div class="text-danger">Batal: {{ \Illuminate\Support\Str::limit($t->void_reason, 60) }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())<div class="p-2 no-print">{{ $transactions->links() }}</div>@endif
</div>

<p class="small text-muted">
    Penjualan dihitung dari transaksi berhasil{{ $dq ? ' dalam periode terpilih' : '' }} dan dicatat atas pemilik saat barang terjual.
    Kolom Diterima (stok awal + tambahan), Keluar/retur, dan Sisa adalah kondisi stok saat ini.
    Tanda * berarti harga pernah berubah selama kegiatan; total mengikuti harga saat terjual.
</p>
@endsection
