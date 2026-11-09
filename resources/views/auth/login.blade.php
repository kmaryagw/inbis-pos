<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14365c">
    <title>Masuk · INBIS POS</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="d-flex align-items-center min-vh-100" style="background: var(--inbis-navy)">
<main class="container" style="max-width: 400px">
    <div class="text-center text-white mb-4">
        <div class="fs-2 fw-bold">INBIS POS</div>
        <div class="opacity-75">Inkubator Bisnis Universitas Udayana</div>
    </div>
    <div class="card p-4">
        <form method="post" action="{{ url('/login') }}">
            @csrf
            @error('username')
                <div class="alert alert-danger py-2 d-flex gap-2"><i class="bi bi-exclamation-triangle-fill"></i>{{ $message }}</div>
            @enderror
            <div class="mb-3">
                <label class="form-label" for="username">Username atau email</label>
                <input class="form-control form-control-lg" id="username" name="username" value="{{ old('username') }}"
                       autocomplete="username" autocapitalize="none" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Kata sandi</label>
                <input class="form-control form-control-lg" id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
            </div>
            <button class="btn btn-primary btn-lg w-100">Masuk</button>
        </form>
    </div>
</main>
</body>
</html>
