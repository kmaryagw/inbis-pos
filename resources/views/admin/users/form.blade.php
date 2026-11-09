@extends('layouts.app')
@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')

@section('content')
<div class="card mx-auto" style="max-width: 640px">
    <div class="card-body">
        <h1 class="page-title mb-3">{{ $user->exists ? 'Ubah pengguna' : 'Tambah pengguna' }}</h1>
        <form method="post" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists) @method('put') @endif

            <div class="mb-3">
                <label class="form-label" for="name">Nama lengkap</label>
                <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" id="username" name="username" value="{{ old('username', $user->username) }}" required autocapitalize="none" maxlength="50">
                    <div class="form-text">Huruf, angka, tanda - atau _.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="email">Email <span class="text-muted">(opsional)</span></label>
                    <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" id="role" name="role">
                        @foreach (\App\Models\User::ROLES as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="is_active">Akun aktif</label>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-sm-6">
                    <label class="form-label" for="password">Kata sandi{{ $user->exists ? ' baru' : '' }}</label>
                    <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" @required(! $user->exists)>
                    @if ($user->exists)<div class="form-text">Kosongkan bila tidak diubah.</div>@endif
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="password_confirmation">Ulangi kata sandi</label>
                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
