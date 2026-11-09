@extends('layouts.app')
@section('title', 'Dashboard')

@php
    $q = $filters->query();
    $payMax = max(1, $payments->max('total'));
    $topMax = max(1, $topProducts->max('total'));
    $cmpMax = max(1, $comparison->max('total'));
@endphp

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-3">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <div class="text-muted small">{{ $filters->describe() }}</div>
    </div>
    <form method="get" class="d-flex flex-wrap gap-2">
        <select class="form-select" name="event" aria-label="Kegiatan" onchange="this.form.submit()" style="min-width: 220px">
            <option value="">Semua kegiatan</option>
            @foreach ($events as $e)
                <option value="{{ $e->id }}" @selected($filters->eventId === $e->id)>{{ $e->name }} ({{ \App\Models\Event::STATUSES[$e->status] }})</option>
            @endforeach
        </select>
        <input class="form-control" type="date" name="from" value="{{ $filters->from?->toDateString() }}" aria-label="Dari tanggal" style="max-width: 160px">
        <input class="form-control" type="date" name="to" value="{{ $filters->to?->toDateString() }}" aria-label="Sampai tanggal" style="max-width: 160px">
        <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i><span class="visually-hidden">Terapkan</span></button>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label"><i class="bi bi-cash-coin me-1"></i>Total pendapatan</div>
        <div class="stat-value num">{{ rupiah($summary['revenue']) }}</div>
        <div class="small text-muted">Rata-rata {{ rupiah($summary['average']) }}/transaksi</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label"><i class="bi bi-receipt me-1"></i>Transaksi berhasil</div>
        <div class="stat-value num">{{ number_format($summary['count'], 0, ',', '.') }}</div>
        @if ($summary['void_count'])
            <div class="small text-danger">{{ $summary['void_count'] }} dibatalkan ({{ rupiah($summary['void_amount']) }})</div>
        @else
            <div class="small text-muted">Tidak ada pembatalan</div>
        @endif
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label"><i class="bi bi-box-seam me-1"></i>Produk terjual</div>
        <div class="stat-value num">{{ number_format($summary['units'], 0, ',', '.') }} <span class="fs-6 fw-normal text-muted">unit</span></div>
        <div class="small text-muted">Stok tersedia {{ number_format($summary['stock_available'], 0, ',', '.') }} unit</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label"><i class="bi bi-exclamation-triangle me-1"></i>Stok menipis</div>
        <div class="stat-value num {{ $summary['low_stock'] ? 'text-danger' : '' }}">{{ $summary['low_stock'] }} <span class="fs-6 fw-normal text-muted">produk</span></div>
        <div class="small text-muted">{{ $event ? 'Pada kegiatan ini' : 'Pada kegiatan aktif' }}</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between"><h2 class="h6 m-0">Metode pembayaran</h2></div>
            <div class="card-body">
                @foreach ($payments as $p)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ $p->label }} <span class="text-muted fw-normal">· {{ $p->count }} transaksi</span></span>
                            <span class="num fw-semibold">{{ rupiah($p->total) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ round($p->total / $payMax * 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <h2 class="h6 m-0">Produk terlaris</h2>
                <a href="{{ route('reports.index', $q + ['tab' => 'products']) }}" class="small">Semua produk</a>
            </div>
            <div class="card-body">
                @forelse ($topProducts as $p)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1 gap-2">
                            <span class="fw-semibold text-truncate">{{ $p->name }} <span class="text-muted fw-normal">· {{ $p->quantity }} {{ $p->unit }}</span></span>
                            <span class="num fw-semibold text-nowrap">{{ rupiah($p->total) }}</span>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ round($p->total / $topMax * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Belum ada penjualan.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if (! $event && $comparison->isNotEmpty())
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white"><h2 class="h6 m-0">Pendapatan per kegiatan</h2></div>
                <div class="card-body">
                    @foreach ($comparison as $c)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1 gap-2">
                                <a href="{{ route('dashboard', ['event' => $c->id]) }}" class="fw-semibold text-truncate">{{ $c->name }}</a>
                                <span class="num fw-semibold text-nowrap">{{ rupiah($c->total) }} <span class="text-muted fw-normal">· {{ $c->count }} trx</span></span>
                            </div>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ round($c->total / $cmpMax * 100) }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($lowStock->isNotEmpty())
        <div class="col-lg-6">
            <div class="card border-danger-subtle">
                <div class="card-header bg-white"><h2 class="h6 m-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Stok menipis</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($lowStock as $s)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $s->name }} <span class="small text-muted">{{ $s->code }}</span></span>
                            <span class="fw-semibold num">{{ $s->available }} <span class="small text-muted fw-normal">/ min {{ $s->min }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="{{ $lowStock->isNotEmpty() ? 'col-lg-6' : 'col-12' }}">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <h2 class="h6 m-0">Transaksi terbaru</h2>
                <a href="{{ route('transactions.index', $q) }}" class="small">Semua transaksi</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($recent as $t)
                    <a href="{{ route('transactions.show', $t) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-2">
                        <span class="small">
                            <span class="font-monospace">{{ $t->number }}</span><br>
                            <span class="text-muted">{{ $t->transacted_at->format('d/m H:i') }} · {{ $t->cashier->name }} · {{ $t->paymentMethodLabel() }}</span>
                        </span>
                        <span class="text-end">
                            <span class="fw-semibold num {{ $t->isVoid() ? 'text-decoration-line-through text-muted' : '' }}">{{ rupiah($t->total) }}</span>
                            @if ($t->isVoid())<br><span class="badge text-bg-danger">Batal</span>@endif
                        </span>
                    </a>
                @empty
                    <li class="list-group-item text-muted small">Belum ada transaksi.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
