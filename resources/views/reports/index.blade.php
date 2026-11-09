@extends('layouts.app')
@section('title', 'Laporan')

@php
    $q = $filters->query();
    $tabs = ['sales' => 'Ringkasan penjualan', 'products' => 'Per produk', 'payments' => 'Metode pembayaran', 'stock' => 'Stok'];
@endphp

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3 no-print">
    <h1 class="page-title">Laporan</h1>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
        <a href="{{ route('reports.export', $q) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Ekspor Excel</a>
    </div>
</div>

{{-- Judul saat dicetak --}}
<div class="print-only mb-3">
    <h1 class="h5 mb-0">Laporan {{ $tabs[$tab] }} — INBIS POS</h1>
    <div>{{ $filters->describe() }}</div>
    <div class="small">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }}</div>
</div>

<form class="card card-body mb-3 no-print" method="get">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="row g-2">
        <div class="col-12 col-md-6 col-xl-4">
            <label class="form-label small mb-1" for="f-event">Kegiatan</label>
            <select class="form-select" id="f-event" name="event">
                <option value="">Semua kegiatan</option>
                @foreach ($events as $e)
                    <option value="{{ $e->id }}" @selected($filters->eventId === $e->id)>{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1" for="f-from">Dari</label>
            <input class="form-control" type="date" id="f-from" name="from" value="{{ $filters->from?->toDateString() }}">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label small mb-1" for="f-to">Sampai</label>
            <input class="form-control" type="date" id="f-to" name="to" value="{{ $filters->to?->toDateString() }}">
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small mb-1" for="f-cashier">Kasir</label>
            <select class="form-select" id="f-cashier" name="cashier">
                <option value="">Semua</option>
                @foreach ($cashiers as $c)
                    <option value="{{ $c->id }}" @selected($filters->cashierId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <label class="form-label small mb-1" for="f-method">Metode</label>
            <select class="form-select" id="f-method" name="method">
                <option value="">Semua</option>
                @foreach (\App\Models\Transaction::PAYMENT_METHODS as $value => $label)
                    <option value="{{ $value }}" @selected($filters->paymentMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-4">
            <label class="form-label small mb-1" for="f-product">Produk</label>
            <select class="form-select" id="f-product" name="product">
                <option value="">Semua produk</option>
                @foreach ($products as $p)
                    <option value="{{ $p->id }}" @selected($filters->productId === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-xl-8 d-flex gap-2 align-items-end">
            <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Terapkan</button>
            <a href="{{ route('reports.index', ['tab' => $tab]) }}" class="btn btn-light">Reset</a>
        </div>
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

<ul class="nav nav-tabs no-print">
    @foreach ($tabs as $key => $label)
        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('reports.index', $q + ['tab' => $key]) }}">{{ $label }}</a></li>
    @endforeach
</ul>

<div class="card border-top-0 rounded-top-0">
    @if ($tab === 'sales')
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>No. transaksi</th><th>Waktu</th>@unless ($event)<th>Kegiatan</th>@endunless<th>Kasir</th><th>Metode</th><th class="text-end">Item</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="font-monospace small text-nowrap"><a href="{{ route('transactions.show', $t) }}">{{ $t->number }}</a></td>
                        <td class="text-nowrap">{{ $t->transacted_at->format('d/m/Y H:i') }}</td>
                        @unless ($event)<td>{{ $t->event->name }}</td>@endunless
                        <td>{{ $t->cashier->name }}</td>
                        <td>{{ $t->paymentMethodLabel() }}</td>
                        <td class="text-end num">{{ $t->item_count }}</td>
                        <td class="text-end num">{{ rupiah($t->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada transaksi berhasil.</td></tr>
                @endforelse
                </tbody>
                @if ($transactions->total())
                    <tfoot><tr class="fw-bold"><td colspan="{{ $event ? 5 : 6 }}" class="text-end">Total ({{ $transactions->total() }} transaksi)</td><td class="text-end num">{{ rupiah($summary['revenue']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
        @if ($transactions->hasPages())<div class="p-2 no-print">{{ $transactions->links() }}</div>@endif

        @if ($voided->isNotEmpty())
            <div class="card-body border-top">
                <h2 class="h6 text-danger"><i class="bi bi-x-octagon me-1"></i>Transaksi dibatalkan (tidak dihitung dalam total)</h2>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>No. transaksi</th><th>Waktu</th><th>Kasir</th><th class="text-end">Total</th><th>Dibatalkan oleh</th><th>Alasan</th></tr></thead>
                        <tbody>
                        @foreach ($voided as $t)
                            <tr>
                                <td class="font-monospace small text-nowrap"><a href="{{ route('transactions.show', $t) }}">{{ $t->number }}</a></td>
                                <td class="text-nowrap">{{ $t->transacted_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $t->cashier->name }}</td>
                                <td class="text-end num">{{ rupiah($t->total) }}</td>
                                <td>{{ $t->voidedBy?->name }} <span class="small text-muted">{{ $t->voided_at?->format('d/m H:i') }}</span></td>
                                <td>{{ $t->void_reason }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @elseif ($tab === 'products')
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Kode</th><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Qty terjual</th><th class="text-end">Total penjualan</th></tr></thead>
                <tbody>
                @forelse ($productSales as $p)
                    <tr>
                        <td class="text-nowrap">{{ $p->code }}</td>
                        <td>{{ $p->name }}</td>
                        <td class="text-end num">{{ rupiah($p->unit_price) }}</td>
                        <td class="text-end num">{{ $p->quantity }} <span class="small text-muted">{{ $p->unit }}</span></td>
                        <td class="text-end num">{{ rupiah($p->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada penjualan.</td></tr>
                @endforelse
                </tbody>
                @if ($productSales->isNotEmpty())
                    <tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end num">{{ $productSales->sum('quantity') }}</td><td class="text-end num">{{ rupiah($productSales->sum('total')) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
        <p class="small text-muted p-2 mb-0">Produk yang harganya berubah di tengah kegiatan ditampilkan per harga.</p>

    @elseif ($tab === 'payments')
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Metode</th><th class="text-end">Jumlah transaksi</th><th class="text-end">Nominal</th><th class="text-end">Porsi</th></tr></thead>
                <tbody>
                @php($payTotal = max(1, $payments->sum('total')))
                @foreach ($payments as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->label }}</td>
                        <td class="text-end num">{{ $p->count }}</td>
                        <td class="text-end num">{{ rupiah($p->total) }}</td>
                        <td class="text-end num">{{ round($p->total / $payTotal * 100, 1) }}%</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end num">{{ $payments->sum('count') }}</td><td class="text-end num">{{ rupiah($payments->sum('total')) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <p class="small text-muted p-2 mb-0">QRIS dicatat oleh kasir setelah pembayaran diverifikasi di aplikasi merchant; cocokkan dengan mutasi merchant.</p>

    @elseif ($tab === 'stock')
        @if (! $event)
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-funnel fs-2 d-block mb-2"></i>Pilih satu kegiatan pada filter untuk melihat laporan stok.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Kode</th><th>Produk</th><th class="text-end">Awal</th><th class="text-end">Masuk</th><th class="text-end">Terjual</th><th class="text-end">Keluar/ penyesuaian</th><th class="text-end">Stok akhir</th></tr></thead>
                    <tbody>
                    @forelse ($stock as $s)
                        <tr class="{{ $s->active ? '' : 'text-muted' }}">
                            <td class="text-nowrap">{{ $s->code }}</td>
                            <td>{{ $s->name }} @unless ($s->active)<span class="badge text-bg-secondary">Tidak dijual</span>@endunless</td>
                            <td class="text-end num">{{ $s->opening }}</td>
                            <td class="text-end num">{{ $s->in }}</td>
                            <td class="text-end num">{{ $s->sold }}</td>
                            <td class="text-end num">{{ $s->out }}</td>
                            <td class="text-end num fw-semibold">
                                {{ $s->available }}
                                @if ($s->low)<span class="badge text-bg-danger ms-1"><i class="bi bi-exclamation-triangle-fill"></i> Menipis</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk dialokasikan.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($stock->isNotEmpty())
                        <tfoot><tr class="fw-bold">
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end num">{{ $stock->sum('opening') }}</td><td class="text-end num">{{ $stock->sum('in') }}</td>
                            <td class="text-end num">{{ $stock->sum('sold') }}</td><td class="text-end num">{{ $stock->sum('out') }}</td>
                            <td class="text-end num">{{ $stock->sum('available') }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>
            <p class="small text-muted p-2 mb-0">Laporan stok menunjukkan kondisi saat ini untuk seluruh kegiatan (tidak terpengaruh filter tanggal/kasir/metode). Stok akhir = awal + masuk − terjual − keluar.</p>
        @endif
    @endif
</div>
@endsection
