@extends('layouts.app')
@section('title', 'Ubah Kata Sandi')

@section('content')
<div class="card mx-auto" style="max-width: 480px">
    <div class="card-body">
        <h1 class="page-title mb-3">Ubah kata sandi</h1>
        <form method="post">
            @csrf @method('put')
            <div class="mb-3">
                <label class="form-label" for="current_password">Kata sandi saat ini</label>
                <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Kata sandi baru</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                <div class="form-text">Minimal 8 karakter.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Ulangi kata sandi baru</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>
            <button class="btn btn-primary">Simpan</button>
        </form>
    </div>
</div>
@endsection
