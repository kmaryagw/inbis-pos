@extends('layouts.app')
@section('title', 'Kegiatan Pameran')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h1 class="page-title">Kegiatan pameran</h1>
    <a href="{{ route('admin.events.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah kegiatan</a>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-4">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, kode, lokasi">
    </div>
    <div class="col-12 col-md-2">
        <select class="form-select" name="status" aria-label="Status">
            <option value="">Semua status</option>
            @foreach (\App\Models\Event::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="{{ request('from') }}" aria-label="Dari tanggal"></div>
    <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="{{ request('to') }}" aria-label="Sampai tanggal"></div>
    <div class="col-12 col-md-2"><button class="btn btn-outline-secondary w-100"><i class="bi bi-funnel me-1"></i>Filter</button></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kegiatan</th><th>Lokasi</th><th>Tanggal</th><th class="text-center">Produk</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($events as $e)
                <tr>
                    <td>
                        <a href="{{ route('admin.events.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->name }}</a>
                        <div class="small text-muted">{{ $e->code }}</div>
                    </td>
                    <td>{{ $e->location }}</td>
                    <td class="text-nowrap">{{ $e->start_date->format('d/m/Y') }} – {{ $e->end_date->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $e->event_products_count }}</td>
                    <td><span class="badge {{ $e->statusBadgeClass() }}">{{ $e->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada kegiatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $events->links() }}</div>
@endsection
