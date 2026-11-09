@extends('layouts.app')
@section('title', 'Riwayat Stok')

@section('content')
<a href="{{ route('admin.events.show', $event) }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> {{ $event->name }}</a>
<h1 class="page-title mt-1 mb-1">Riwayat stok: {{ $eventProduct->product->name }}</h1>
<p class="text-muted small">{{ $eventProduct->product->code }} · Stok tersedia saat ini: <strong>{{ $eventProduct->current_stock }}</strong></p>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Waktu</th><th>Jenis</th><th class="text-end">Jumlah</th><th class="text-end">Saldo</th><th>Catatan / referensi</th><th>Oleh</th></tr></thead>
            <tbody>
            @forelse ($movements as $m)
                <tr>
                    <td class="text-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->typeLabel() }}</td>
                    <td class="text-end num fw-semibold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                    <td class="text-end num">{{ $m->balance_after }}</td>
                    <td>{{ $m->note ?? '—' }}</td>
                    <td>{{ $m->user->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pergerakan stok.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $movements->links() }}</div>
@endsection
