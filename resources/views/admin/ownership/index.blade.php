@extends('layouts.app')
@section('title', 'Kepemilikan Produk')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div>
        <h1 class="page-title">Kepemilikan produk</h1>
        <div class="text-muted small">Tetapkan tenant/dosen pemilik untuk satu atau banyak produk sekaligus.</div>
    </div>
    <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline-secondary"><i class="bi bi-person-badge me-1"></i>Tenant / Dosen</a>
</div>

@if ($unownedCount)
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div class="me-auto"><strong>{{ $unownedCount }} produk</strong> belum memiliki pemilik. Penjualannya akan masuk ke kelompok "Tanpa pemilik" di laporan.</div>
        @if (request('owner') !== 'none')
            <a href="{{ route('admin.ownership.index', ['owner' => 'none']) }}" class="btn btn-sm btn-warning">Tampilkan</a>
        @endif
    </div>
@endif

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-4">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode produk">
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" name="owner" aria-label="Pemilik">
            <option value="">Semua pemilik</option>
            <option value="none" @selected(request('owner') === 'none')>— Belum ada pemilik —</option>
            @foreach ($tenants as $t)
                <option value="{{ $t->id }}" @selected(request('owner') == $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" name="category" aria-label="Kategori">
            <option value="">Semua kategori</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel me-1"></i>Filter</button></div>
</form>

<form method="post" action="{{ route('admin.ownership.update') }}" id="ownershipForm">
    @csrf @method('put')

    <div class="card mb-3 sticky-top" style="top: .5rem; z-index: 5">
        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted me-1"><span id="selCount">0</span> produk dipilih →</span>
            <select class="form-select w-auto flex-grow-1" name="tenant_id" aria-label="Tetapkan ke" style="max-width: 360px">
                <option value="">— Kosongkan pemilik —</option>
                @foreach ($tenants->where('is_active', true) as $t)
                    <option value="{{ $t->id }}" @selected(old('tenant_id', ctype_digit((string) request('owner')) ? request('owner') : null) == $t->id)>{{ $t->name }} ({{ $t->code }})</option>
                @endforeach
            </select>
            <button class="btn btn-primary" id="applyBtn" disabled><i class="bi bi-check2-square me-1"></i>Terapkan</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th style="width: 40px"><input class="form-check-input" type="checkbox" id="checkAll" aria-label="Pilih semua"></th>
                    <th>Kode</th><th>Produk</th><th>Kategori</th><th>Pemilik saat ini</th>
                </tr></thead>
                <tbody>
                @forelse ($products as $p)
                    <tr>
                        <td><input class="form-check-input row-check" type="checkbox" name="products[]" value="{{ $p->id }}" id="p{{ $p->id }}" aria-label="Pilih {{ $p->name }}"></td>
                        <td class="text-nowrap font-monospace small"><label for="p{{ $p->id }}">{{ $p->code }}</label></td>
                        <td><label for="p{{ $p->id }}" class="fw-medium">{{ $p->name }}</label>@unless ($p->is_active) <span class="badge text-bg-secondary">Tidak aktif</span>@endunless</td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td>
                            @if ($p->tenant)
                                <a href="{{ route('admin.tenants.show', $p->tenant) }}" class="text-decoration-none">{{ $p->tenant->name }}</a>
                                <span class="badge {{ $p->tenant->typeBadgeClass() }}">{{ $p->tenant->typeLabel() }}</span>
                            @else
                                <span class="text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>Belum ada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>
<div class="mt-3">{{ $products->links() }}</div>

<p class="small text-muted mt-3">
    Penjualan dicatat atas nama pemilik pada saat barang terjual. Bila pemilik diganti, penjualan sebelumnya tetap milik pemilik lama;
    hanya penjualan lama yang belum memiliki pemilik yang ikut dipindahkan ke pemilik baru.
</p>
@endsection

@push('scripts')
<script>
(() => {
    const all = document.getElementById('checkAll');
    const rows = [...document.querySelectorAll('.row-check')];
    const count = document.getElementById('selCount');
    const btn = document.getElementById('applyBtn');
    const refresh = () => {
        const n = rows.filter(r => r.checked).length;
        count.textContent = n;
        btn.disabled = n === 0;
        all.checked = n > 0 && n === rows.length;
        all.indeterminate = n > 0 && n < rows.length;
    };
    all?.addEventListener('change', () => { rows.forEach(r => r.checked = all.checked); refresh(); });
    rows.forEach(r => r.addEventListener('change', refresh));
    refresh();
})();
</script>
@endpush
