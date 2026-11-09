@extends('layouts.app')
@section('title', 'Kategori')

@section('content')
<h1 class="page-title mb-3">Kategori produk</h1>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="h6 mb-3">Tambah kategori</h2>
                <form method="post" action="{{ route('admin.categories.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label" for="name">Nama</label>
                        <input class="form-control" id="name" name="name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Deskripsi</label>
                        <input class="form-control" id="description" name="description" maxlength="500">
                    </div>
                    <button class="btn btn-primary w-100">Tambah</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <ul class="list-group list-group-flush">
                @forelse ($categories as $c)
                    <li class="list-group-item">
                        <form method="post" action="{{ route('admin.categories.update', $c) }}" class="row g-2 align-items-center">
                            @csrf @method('put')
                            <div class="col-md-4"><input class="form-control form-control-sm" name="name" value="{{ $c->name }}" required aria-label="Nama"></div>
                            <div class="col-md-4"><input class="form-control form-control-sm" name="description" value="{{ $c->description }}" placeholder="Deskripsi" aria-label="Deskripsi"></div>
                            <div class="col-6 col-md-2">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="cat{{ $c->id }}" @checked($c->is_active)>
                                    <label class="form-check-label small" for="cat{{ $c->id }}">Aktif</label>
                                </div>
                                <div class="small text-muted">{{ $c->products_count }} produk</div>
                            </div>
                            <div class="col-6 col-md-2 text-end"><button class="btn btn-sm btn-outline-primary">Simpan</button></div>
                        </form>
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-4">Belum ada kategori.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
