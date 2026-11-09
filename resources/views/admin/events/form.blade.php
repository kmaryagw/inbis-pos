@extends('layouts.app')
@section('title', $event->exists ? 'Ubah Kegiatan' : 'Tambah Kegiatan')

@section('content')
<div class="card mx-auto" style="max-width: 760px">
    <div class="card-body">
        <h1 class="page-title mb-3">{{ $event->exists ? 'Ubah kegiatan' : 'Tambah kegiatan' }}</h1>
        <form method="post" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
            @csrf
            @if ($event->exists) @method('put') @endif

            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label" for="code">Kode kegiatan</label>
                    <input class="form-control text-uppercase" id="code" name="code" value="{{ old('code', $event->code) }}"
                           required minlength="2" maxlength="12" pattern="[A-Za-z0-9]+" @readonly($event->exists && ! $event->isDraft())>
                    <div class="form-text">Dipakai pada nomor transaksi, mis. UIE26. Tidak dapat diubah setelah aktif.</div>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="name">Nama kegiatan</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $event->name) }}" required maxlength="150">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="location">Lokasi</label>
                    <input class="form-control" id="location" name="location" value="{{ old('location', $event->location) }}" required maxlength="150">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="person_in_charge">Penanggung jawab</label>
                    <input class="form-control" id="person_in_charge" name="person_in_charge" value="{{ old('person_in_charge', $event->person_in_charge) }}" required maxlength="100">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label" for="start_date">Tanggal mulai</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="{{ old('start_date', $event->start_date?->format('Y-m-d')) }}" required>
                </div>
                <div class="col-6">
                    <label class="form-label" for="end_date">Tanggal selesai</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="{{ old('end_date', $event->end_date?->format('Y-m-d')) }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Deskripsi / catatan</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="2000">{{ old('description', $event->description) }}</textarea>
            </div>

            <fieldset class="border rounded p-3 mb-4">
                <legend class="float-none w-auto px-2 fs-6 mb-0">Struk <span class="text-muted small">(opsional)</span></legend>
                <div class="mb-2">
                    <label class="form-label" for="receipt_header">Judul struk</label>
                    <input class="form-control" id="receipt_header" name="receipt_header" value="{{ old('receipt_header', $event->receipt_header) }}" maxlength="100" placeholder="Default: INBIS UNIVERSITAS UDAYANA">
                </div>
                <div>
                    <label class="form-label" for="receipt_footer">Catatan bawah struk</label>
                    <input class="form-control" id="receipt_footer" name="receipt_footer" value="{{ old('receipt_footer', $event->receipt_footer) }}" maxlength="150" placeholder="Default: Terima kasih telah berbelanja!">
                </div>
            </fieldset>

            <div class="d-flex gap-2">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ $event->exists ? route('admin.events.show', $event) : route('admin.events.index') }}" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
