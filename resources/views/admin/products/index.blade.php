@extends('layouts.app')
@section('title', 'Produk')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h1 class="page-title">Master produk</h1>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah produk</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-5">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode">
    </div>
    <div class="col-6 col-md-3">
        <select class="form-select" name="category" aria-label="Kategori">
            <option value="">Semua kategori</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
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
            <thead><tr><th></th><th>Kode</th><th>Nama</th><th>Kategori</th><th>Satuan</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                <tr>
                    <td style="width: 52px">
                        @if ($p->photo)
                            <img src="{{ $p->photoUrl() }}" alt="" class="rounded" width="40" height="40" style="object-fit: cover" loading="lazy">
                        @else
                            <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px"><i class="bi bi-image"></i></div>
                        @endif
                    </td>
                    <td class="text-nowrap">{{ $p->code }}</td>
                    <td class="fw-medium"><a href="{{ route('admin.products.show', $p) }}" class="text-reset text-decoration-none">{{ $p->name }}</a></td>
                    <td>{{ $p->category?->name ?? '—' }}</td>
                    <td>{{ $p->unit }}</td>
                    <td>
                        @if ($p->is_active)
                            <span class="badge text-bg-success">Aktif</span>
                        @else
                            <span class="badge text-bg-secondary">Tidak aktif</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.products.show', $p) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                        <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
