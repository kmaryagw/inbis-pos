<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#14365c">
    <title>@yield('title', 'INBIS POS') · INBIS POS</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
@php($user = auth()->user())
<nav class="navbar navbar-expand-lg navbar-dark navbar-inbis sticky-top">
    <div class="container-xl">
        <a class="navbar-brand fw-bold" href="{{ route('home') }}">INBIS POS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                @unless ($user->isCashier())
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
                @endunless
                @unless ($user->isManager())
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}"><i class="bi bi-cart3 me-1"></i>Kasir</a></li>
                @endunless
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}" href="{{ route('transactions.index') }}"><i class="bi bi-receipt me-1"></i>Transaksi</a></li>
                @unless ($user->isCashier())
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart me-1"></i>Laporan</a></li>
                @endunless
                @if ($user->isAdmin())
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.events.*', 'admin.event-products.*') ? 'active' : '' }}" href="{{ route('admin.events.index') }}"><i class="bi bi-calendar-event me-1"></i>Kegiatan</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-box-seam me-1"></i>Produk</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags me-1"></i>Kategori</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people me-1"></i>Pengguna</a></li>
                @endif
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>{{ $user->name }} <span class="small opacity-75">({{ $user->roleLabel() }})</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Ubah kata sandi</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container-xl py-3 py-md-4">
    @include('partials.flash')
    @yield('content')
</main>

<script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
