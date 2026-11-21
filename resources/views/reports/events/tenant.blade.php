@extends('layouts.app')
@section('title', 'Rekap '.$tenant->name.' — '.$event->code)

@php
    $dq = $filters->dateQuery();
    $period = ($filters->from || $filters->to)
        ? ($filters->from?->format('d/m/Y') ?? '…').' – '.($filters->to?->format('d/m/Y') ?? '…')
        : $event->start_date->format('d/m/Y').' – '.$event->end_date->format('d/m/Y');
@endphp

@section('content')
<div class="mx-auto" style="max-width: 860px">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3 no-print">
        <a href="{{ route('reports.events.show', [$event] + $dq) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Laporan {{ $event->name }}</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak rekap</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="text-center mb-3">
                <div class="fw-bold">INKUBATOR BISNIS UNIVERSITAS UDAYANA</div>
                <h1 class="h5 mb-0">REKAP PENJUALAN TENANT / DOSEN</h1>
            </div>

            <div class="row small mb-3">
                <div class="col-sm-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted ps-0" style="width: 40%">Kegiatan</td><td>{{ $event->name }} ({{ $event->code }})</td></tr>
                        <tr><td class="text-muted ps-0">Lokasi</td><td>{{ $event->location }}</td></tr>
                        <tr><td class="text-muted ps-0">Periode</td><td>{{ $period }}</td></tr>
                    </table>
                </div>
                <div class="col-sm-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted ps-0" style="width: 40%">{{ $tenant->typeLabel() }}</td><td class="fw-semibold">{{ $tenant->name }}</td></tr>
                        <tr><td class="text-muted ps-0">Kode / Unit</td><td>{{ $tenant->code }}@if ($tenant->unit) · {{ $tenant->unit }}@endif</td></tr>
                        <tr><td class="text-muted ps-0">Rekening</td><td>{{ $tenant->bankAccount() ?? '—' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th>No</th><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Diterima</th><th class="text-end">Terjual</th><th class="text-end">Retur</th><th class="text-end">Sisa</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse ($group?->rows ?? [] as $i => $r)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $r->name }}<div class="small text-muted">{{ $r->code }}</div></td>
                            <td class="text-end num">{{ rupiah($r->price) }}{{ $r->multi_price ? '*' : '' }}</td>
                            <td class="text-end num">{{ $r->has_stock ? $r->received : '—' }}</td>
                            <td class="text-end num">{{ $r->quantity }}</td>
                            <td class="text-end num">{{ $r->has_stock ? $r->out : '—' }}</td>
                            <td class="text-end num">{{ $r->has_stock ? $r->available : '—' }}</td>
                            <td class="text-end num">{{ rupiah($r->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada produk tenant/dosen ini pada kegiatan tersebut.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($group)
                        <tfoot><tr class="fw-bold">
                            <td colspan="4" class="text-end">TOTAL</td>
                            <td class="text-end num">{{ $group->quantity }}</td><td></td>
                            <td class="text-end num">{{ $group->available }}</td>
                            <td class="text-end num fs-6">{{ rupiah($group->total) }}</td>
                        </tr></tfoot>
                    @endif
                </table>
            </div>

            <p class="small text-muted mb-4">
                Total penjualan dihitung dari transaksi berhasil (transaksi batal tidak dihitung).
                Sisa stok adalah barang yang dikembalikan kepada {{ strtolower($tenant->typeLabel()) }}.
                @if ($group?->rows->contains('multi_price', true)) Tanda * berarti harga pernah berubah; total mengikuti harga saat terjual.@endif
            </p>

            <div class="row text-center small mt-4 break-avoid">
                <div class="col-6">
                    Diserahkan oleh,<br>Pengelola INBIS
                    <div style="height: 64px"></div>
                    (..............................)
                </div>
                <div class="col-6">
                    Diterima oleh,<br>{{ $tenant->typeLabel() }}
                    <div style="height: 64px"></div>
                    ({{ $tenant->contact_person ?: $tenant->name }})
                </div>
            </div>
            <div class="small text-muted text-end mt-4">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name }} · INBIS POS</div>
        </div>
    </div>
</div>
@endsection
