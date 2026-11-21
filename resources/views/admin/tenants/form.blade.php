@extends('layouts.app')
@section('title', $tenant->exists ? 'Ubah Tenant/Dosen' : 'Tambah Tenant/Dosen')

@section('content')
<div class="card mx-auto" style="max-width: 760px">
    <div class="card-body">
        <h1 class="page-title mb-3">{{ $tenant->exists ? 'Ubah tenant/dosen' : 'Tambah tenant/dosen' }}</h1>
        <form method="post" action="{{ $tenant->exists ? route('admin.tenants.update', $tenant) : route('admin.tenants.store') }}">
            @csrf
            @if ($tenant->exists) @method('put') @endif

            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label" for="code">Kode</label>
                    <input class="form-control bg-body-secondary" id="code" value="{{ $tenant->exists ? $tenant->code : 'Otomatis' }}" readonly tabindex="-1">
                    <div class="form-text">{{ $tenant->exists ? 'Kode tidak dapat diubah.' : 'Dibuat otomatis saat disimpan.' }}</div>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="name">Nama tenant / dosen</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $tenant->name) }}" required maxlength="150"
                           placeholder="Mis. Dr. I Made Suardana, S.T., M.T. atau CV Kopi Kintamani">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label d-block">Jenis</label>
                    <div class="btn-group w-100" role="group">
                        @foreach (\App\Models\Tenant::TYPES as $value => $label)
                            <input type="radio" class="btn-check" name="type" id="type-{{ $value }}" value="{{ $value }}" @checked(old('type', $tenant->type) === $value) required>
                            <label class="btn btn-outline-primary" for="type-{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-sm-8">
                    <label class="form-label" for="unit">Unit / Fakultas / Nama usaha</label>
                    <input class="form-control" id="unit" name="unit" value="{{ old('unit', $tenant->unit) }}" maxlength="150" placeholder="Mis. Fakultas Teknologi Pertanian">
                </div>
            </div>

            <h2 class="h6 mt-4 mb-2 text-muted text-uppercase small">Kontak</h2>
            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label" for="contact_person">Narahubung</label>
                    <input class="form-control" id="contact_person" name="contact_person" value="{{ old('contact_person', $tenant->contact_person) }}" maxlength="100">
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="phone">No. HP / WA</label>
                    <input class="form-control" id="phone" name="phone" value="{{ old('phone', $tenant->phone) }}" maxlength="30" inputmode="tel">
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="{{ old('email', $tenant->email) }}" maxlength="150">
                </div>
            </div>

            <h2 class="h6 mt-4 mb-2 text-muted text-uppercase small">Rekening penyaluran hasil penjualan</h2>
            <div class="row g-3 mb-3">
                <div class="col-sm-3">
                    <label class="form-label" for="bank_name">Bank</label>
                    <input class="form-control" id="bank_name" name="bank_name" value="{{ old('bank_name', $tenant->bank_name) }}" maxlength="50" list="banks">
                    <datalist id="banks"><option value="BPD Bali"><option value="BRI"><option value="BNI"><option value="Mandiri"><option value="BCA"><option value="BSI"></datalist>
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="bank_account_number">Nomor rekening</label>
                    <input class="form-control" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number', $tenant->bank_account_number) }}" maxlength="50" inputmode="numeric">
                </div>
                <div class="col-sm-5">
                    <label class="form-label" for="bank_account_name">Atas nama</label>
                    <input class="form-control" id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name', $tenant->bank_account_name) }}" maxlength="100">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="notes">Catatan</label>
                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $tenant->notes) }}</textarea>
            </div>
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $tenant->is_active))>
                <label class="form-check-label" for="is_active">Aktif</label>
                <div class="form-text">Tenant/dosen tidak aktif tidak dapat diberi produk baru, tetapi rekap penjualannya tetap tersimpan.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ $tenant->exists ? route('admin.tenants.show', $tenant) : route('admin.tenants.index') }}" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
