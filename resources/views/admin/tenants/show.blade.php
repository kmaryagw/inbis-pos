@extends('layouts.app')
@section('title', $tenant->name)

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-start justify-content-between mb-3">
    <div>
        <a href="{{ route('admin.tenants.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Tenant / Dosen</a>
        <h1 class="page-title mt-1">{{ $tenant->name }}
            <span class="badge {{ $tenant->typeBadgeClass() }} align-middle fs-6">{{ $tenant->typeLabel() }}</span>
            @unless ($tenant->is_active)<span class="badge text-bg-secondary align-middle fs-6">Tidak aktif</span>@endunless
        </h1>
        <div class="text-muted small"><span class="font-monospace">{{ $tenant->code }}</span>@if ($tenant->unit) · {{ $tenant->unit }}@endif</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ownership.index', ['owner' => $tenant->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>Atur produk</a>
        <a href="{{ route('admin.tenants.edit', $tenant) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Ubah</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-5">
        <div class="card h-100"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5 fw-normal text-muted">Narahubung</dt><dd class="col-7">{{ $tenant->contact_person ?: '—' }}</dd>
                <dt class="col-5 fw-normal text-muted">No. HP / WA</dt><dd class="col-7">{{ $tenant->phone ?: '—' }}</dd>
                <dt class="col-5 fw-normal text-muted">Email</dt><dd class="col-7 text-break">{{ $tenant->email ?: '—' }}</dd>
                <dt class="col-5 fw-normal text-muted">Rekening</dt><dd class="col-7">{{ $tenant->bankAccount() ?? '—' }}</dd>
                @if ($tenant->notes)
                    <dt class="col-12 fw-normal text-muted mt-2">Catatan</dt><dd class="col-12 mb-0" style="white-space: pre-line">{{ $tenant->notes }}</dd>
                @endif
            </dl>
        </div></div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="row g-3">
            <div class="col-6"><div class="card h-100"><div class="card-body">
                <div class="stat-label">Produk dimiliki</div><div class="stat-value num">{{ $products->count() }}</div>
            </div></div></div>
            <div class="col-6"><div class="card h-100"><div class="card-body">
                <div class="stat-label">Ikut kegiatan</div><div class="stat-value num">{{ $sales->count() }}</div>
            </div></div></div>
            <div class="col-6"><div class="card h-100"><div class="card-body">
                <div class="stat-label">Total terjual</div><div class="stat-value num">{{ number_format($sales->sum('quantity'), 0, ',', '.') }}</div>
            </div></div></div>
            <div class="col-6"><div class="card h-100"><div class="card-body">
                <div class="stat-label">Total penjualan</div><div class="stat-value num">{{ rupiah((int) $sales->sum('total')) }}</div>
            </div></div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">Rekap penjualan per kegiatan</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kegiatan</th><th>Status</th><th class="text-end">Transaksi</th><th class="text-end">Qty terjual</th><th class="text-end">Total penjualan</th><th></th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                @php($ev = (new \App\Models\Event)->forceFill(['status' => $s->status]))
                <tr>
                    <td>
                        <span class="fw-medium">{{ $s->name }}</span>
                        <div class="small text-muted">{{ $s->code }} · {{ \Carbon\Carbon::parse($s->start_date)->format('d/m/Y') }}</div>
                    </td>
                    <td><span class="badge {{ $ev->statusBadgeClass() }}">{{ $ev->statusLabel() }}</span></td>
                    <td class="text-end num">{{ $s->transactions }}</td>
                    <td class="text-end num">{{ $s->quantity }}</td>
                    <td class="text-end num fw-semibold">{{ rupiah((int) $s->total) }}</td>
                    <td class="text-end text-nowrap">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('reports.events.tenant', [$s->id, $tenant]) }}" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Rekap lengkap</a>
                            <a href="{{ route('reports.events.tenant', [$s->id, $tenant, 'jenis' => 'terjual']) }}" class="btn btn-outline-secondary">Terjual</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penjualan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
        Produk milik {{ $tenant->typeLabel() === 'Dosen' ? 'dosen' : 'tenant' }} ini
        <a href="{{ route('admin.ownership.index', ['owner' => 'none']) }}" class="small fw-normal">Tambah dari produk tanpa pemilik</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Produk</th><th>Kategori</th><th>Satuan</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td class="text-nowrap font-monospace small">{{ $p->code }}</td>
                    <td><a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a></td>
                    <td>{{ $p->category?->name ?? '—' }}</td>
                    <td>{{ $p->unit }}</td>
                    <td>@if ($p->is_active)<span class="badge text-bg-success">Aktif</span>@else<span class="badge text-bg-secondary">Tidak aktif</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
