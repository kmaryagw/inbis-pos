@extends('layouts.app')
@section('title', $product->name)

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
    <div>
        <a href="{{ route('admin.products.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Produk</a>
        <h1 class="page-title mt-1">{{ $product->name }}
            @if ($product->is_active)
                <span class="badge text-bg-success align-middle fs-6">Aktif</span>
            @else
                <span class="badge text-bg-secondary align-middle fs-6">Tidak aktif</span>
            @endif
        </h1>
        <div class="text-muted small font-monospace">{{ $product->code }}</div>
    </div>
    <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card h-100">
            @if ($product->photo)
                <img src="{{ $product->photoUrl() }}" alt="{{ $product->name }}" class="card-img-top" style="aspect-ratio: 1; object-fit: cover">
            @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center text-muted" style="aspect-ratio: 1">
                    <i class="bi bi-image fs-1"></i>
                </div>
            @endif
        </div>
    </div>
    <div class="col-12 col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 col-sm-4 fw-normal text-muted">Kode</dt><dd class="col-7 col-sm-8 font-monospace">{{ $product->code }}</dd>
                    <dt class="col-5 col-sm-4 fw-normal text-muted">Kategori</dt><dd class="col-7 col-sm-8">{{ $product->category?->name ?? '—' }}</dd>
                    <dt class="col-5 col-sm-4 fw-normal text-muted">Satuan</dt><dd class="col-7 col-sm-8">{{ $product->unit }}</dd>
                    <dt class="col-5 col-sm-4 fw-normal text-muted">Dibuat</dt><dd class="col-7 col-sm-8">{{ $product->created_at?->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5 col-sm-4 fw-normal text-muted">Terakhir diubah</dt><dd class="col-7 col-sm-8">{{ $product->updated_at?->format('d/m/Y H:i') }}</dd>
                    <dt class="col-12 fw-normal text-muted mt-2">Deskripsi</dt>
                    <dd class="col-12 mb-0" style="white-space: pre-line">{{ $product->description ?: '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Dialokasikan ke</div><div class="stat-value num">{{ $allocations->count() }} <span class="fs-6 fw-normal text-muted">kegiatan</span></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Total terjual</div><div class="stat-value num">{{ number_format($totals->units, 0, ',', '.') }} <span class="fs-6 fw-normal text-muted">{{ $product->unit }}</span></div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Total pendapatan</div><div class="stat-value num">{{ rupiah($totals->revenue) }}</div>
    </div></div></div>
    <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body">
        <div class="stat-label">Stok di kegiatan aktif</div><div class="stat-value num">{{ number_format($totals->stock, 0, ',', '.') }}</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold">Riwayat per kegiatan</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th>Kegiatan</th><th>Status</th><th class="text-end">Harga jual</th><th class="text-end">Stok awal</th>
                <th class="text-end">Terjual</th><th class="text-end">Sisa stok</th><th class="text-end">Pendapatan</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($allocations as $ep)
                <tr>
                    <td>
                        <a href="{{ route('admin.events.show', $ep->event) }}" class="fw-medium text-decoration-none">{{ $ep->event->name }}</a>
                        <div class="small text-muted">{{ $ep->event->code }} · {{ $ep->event->start_date->format('d/m/Y') }}</div>
                    </td>
                    <td><span class="badge {{ $ep->event->statusBadgeClass() }}">{{ $ep->event->statusLabel() }}</span></td>
                    <td class="text-end num">{{ rupiah($ep->selling_price) }}</td>
                    <td class="text-end num">{{ $ep->opening_stock }}</td>
                    <td class="text-end num">{{ (int) $ep->units_sold }}</td>
                    <td class="text-end num {{ $ep->isLowStock() ? 'text-danger fw-semibold' : '' }}">{{ $ep->current_stock }}</td>
                    <td class="text-end num">{{ rupiah((int) $ep->revenue) }}</td>
                    <td class="text-end"><a href="{{ route('admin.event-products.history', [$ep->event, $ep]) }}" class="btn btn-sm btn-outline-secondary text-nowrap">Riwayat stok</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Produk ini belum dialokasikan ke kegiatan mana pun.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
