@extends('layouts.app')
@section('title', $product->exists ? 'Ubah Produk' : 'Tambah Produk')

@section('content')
<div class="card mx-auto" style="max-width: 720px">
    <div class="card-body">
        <h1 class="page-title mb-3">{{ $product->exists ? 'Ubah produk' : 'Tambah produk' }}</h1>
        <form method="post" enctype="multipart/form-data"
              action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
            @csrf
            @if ($product->exists) @method('put') @endif

            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label" for="code">Kode produk</label>
                    <input class="form-control bg-body-secondary" id="code" value="{{ $product->exists ? $product->code : 'Otomatis' }}" readonly tabindex="-1" aria-describedby="codeHelp">
                    <div class="form-text" id="codeHelp">{{ $product->exists ? 'Kode tidak dapat diubah.' : 'Dibuat otomatis saat disimpan.' }}</div>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="name">Nama produk</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-8">
                    <label class="form-label" for="category_id">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">— Tanpa kategori —</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $product->category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="unit">Satuan</label>
                    <input class="form-control" id="unit" name="unit" value="{{ old('unit', $product->unit) }}" required maxlength="30" list="units">
                    <datalist id="units"><option value="pcs"><option value="botol"><option value="paket"><option value="unit"><option value="pack"><option value="box"></datalist>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Deskripsi</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000">{{ old('description', $product->description) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="photo">Foto <span class="text-muted">(opsional, maks. 2 MB)</span></label>
                @if ($product->photo)
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="{{ $product->photoUrl() }}" alt="" class="rounded" width="64" height="64" style="object-fit: cover">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                            <label class="form-check-label" for="remove_photo">Hapus foto</label>
                        </div>
                    </div>
                @endif
                <input class="form-control" type="file" id="photo" name="photo" accept="image/*">
            </div>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                <label class="form-check-label" for="is_active">Produk aktif</label>
                <div class="form-text">Produk tidak aktif tidak dapat dialokasikan ke kegiatan baru.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ $product->exists ? route('admin.products.show', $product) : route('admin.products.index') }}" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
