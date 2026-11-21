@extends('layouts.app')
@section('title', 'Laporan per Kegiatan')

@section('content')
@include('reports._nav')
<h1 class="page-title mb-3">Laporan per kegiatan</h1>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Kegiatan</th><th>Status</th><th class="text-end">Tenant/dosen</th><th class="text-end">Transaksi</th><th class="text-end">Pendapatan</th><th></th></tr></thead>
            <tbody>
            @forelse ($events as $e)
                <tr>
                    <td>
                        <a href="{{ route('reports.events.show', $e) }}" class="fw-medium text-decoration-none">{{ $e->name }}</a>
                        <div class="small text-muted">{{ $e->code }} · {{ $e->start_date->format('d/m/Y') }} – {{ $e->end_date->format('d/m/Y') }} · {{ $e->location }}</div>
                    </td>
                    <td><span class="badge {{ $e->statusBadgeClass() }}">{{ $e->statusLabel() }}</span></td>
                    <td class="text-end num">{{ $tenants[$e->id] ?? 0 }}</td>
                    <td class="text-end num">{{ number_format($sales[$e->id]->count ?? 0, 0, ',', '.') }}</td>
                    <td class="text-end num fw-semibold">{{ rupiah((int) ($sales[$e->id]->total ?? 0)) }}</td>
                    <td class="text-end"><a href="{{ route('reports.events.show', $e) }}" class="btn btn-sm btn-outline-primary text-nowrap">Lihat detail</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada kegiatan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
