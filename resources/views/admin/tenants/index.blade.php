@extends('layouts.app')
@section('title', 'Tenant / Dosen')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h1 class="page-title">Tenant / Dosen</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.ownership.index') }}" class="btn btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>Kepemilikan produk</a>
        <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
    </div>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-5">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode, atau unit">
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" name="type" aria-label="Jenis">
            <option value="">Semua jenis</option>
            @foreach (\App\Models\Tenant::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select class="form-select" name="status" aria-label="Status">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Tidak aktif</option>
        </select>
    </div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel me-1"></i>Filter</button></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Unit / Fakultas</th><th>Kontak</th><th class="text-end">Produk</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($tenants as $t)
                <tr>
                    <td class="text-nowrap font-monospace small">{{ $t->code }}</td>
                    <td class="fw-medium"><a href="{{ route('admin.tenants.show', $t) }}" class="text-reset text-decoration-none">{{ $t->name }}</a></td>
                    <td><span class="badge {{ $t->typeBadgeClass() }}">{{ $t->typeLabel() }}</span></td>
                    <td>{{ $t->unit ?: '—' }}</td>
                    <td class="small">{{ $t->phone ?: '' }}@if ($t->phone && $t->email)<br>@endif{{ $t->email ?: '' }}@if (! $t->phone && ! $t->email)—@endif</td>
                    <td class="text-end num">{{ $t->products_count }}</td>
                    <td>
                        @if ($t->is_active)
                            <span class="badge text-bg-success">Aktif</span>
                        @else
                            <span class="badge text-bg-secondary">Tidak aktif</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.tenants.show', $t) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                        <a href="{{ route('admin.tenants.edit', $t) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada tenant/dosen.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $tenants->links() }}</div>
@endsection
