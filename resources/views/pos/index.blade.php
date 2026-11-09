@extends('layouts.app')
@section('title', 'Kasir')

@section('content')
<div class="mx-auto" style="max-width: 640px">
    <h1 class="page-title mb-3">Pilih kegiatan</h1>

    @forelse ($events as $e)
        <a href="{{ route('pos.show', $e) }}" class="card card-body mb-2 text-decoration-none text-body d-flex flex-row align-items-center gap-3" style="min-height: 76px">
            <div class="fs-3 text-success"><i class="bi bi-shop"></i></div>
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ $e->name }}</div>
                <div class="small text-muted">{{ $e->location }} · {{ $e->start_date->format('d/m') }}–{{ $e->end_date->format('d/m/Y') }}</div>
            </div>
            <i class="bi bi-chevron-right text-muted"></i>
        </a>
    @empty
        <div class="card card-body text-center text-muted py-5">
            <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
            Tidak ada kegiatan aktif yang ditugaskan kepada Anda.<br>Hubungi administrator INBIS.
        </div>
    @endforelse
</div>
@endsection
