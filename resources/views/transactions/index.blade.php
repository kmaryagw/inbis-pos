@extends('layouts.app')
@section('title', 'Riwayat Transaksi')

@section('content')
<h1 class="page-title mb-3">Riwayat transaksi</h1>

<form class="card card-body mb-3" method="get">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-xl-3">
            <select class="form-select" name="event" aria-label="Kegiatan">
                <option value="">Semua kegiatan</option>
                @foreach ($events as $e)
                    <option value="{{ $e->id }}" @selected($filters->eventId === $e->id)>{{ $e->name }}{{ $e->status === 'active' ? ' (aktif)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4 col-xl-3">
            <input class="form-control" type="search" name="q" value="{{ $filters->number }}" placeholder="Nomor transaksi" aria-label="Nomor transaksi">
        </div>
        <div class="col-6 col-md-2 col-xl-2"><input class="form-control" type="date" name="from" value="{{ $filters->from?->toDateString() }}" aria-label="Dari tanggal"></div>
        <div class="col-6 col-md-2 col-xl-2"><input class="form-control" type="date" name="to" value="{{ $filters->to?->toDateString() }}" aria-label="Sampai tanggal"></div>
        @if ($cashiers->isNotEmpty())
            <div class="col-12 col-md-4 col-xl-2">
                <select class="form-select" name="cashier" aria-label="Kasir">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $c)
                        <option value="{{ $c->id }}" @selected($filters->cashierId === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="col-6 col-md-3 col-xl-2">
            <select class="form-select" name="method" aria-label="Metode">
                <option value="">Semua metode</option>
                @foreach (\App\Models\Transaction::PAYMENT_METHODS as $value => $label)
                    <option value="{{ $value }}" @selected($filters->paymentMethod === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <select class="form-select" name="status" aria-label="Status">
                <option value="">Semua status</option>
                <option value="paid" @selected($status === 'paid')>Berhasil</option>
                <option value="void" @selected($status === 'void')>Batal</option>
            </select>
        </div>
        <div class="col-12 col-md-2 col-xl-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-light" title="Reset"><i class="bi bi-x-lg"></i><span class="visually-hidden">Reset</span></a>
        </div>
    </div>
</form>

<p class="small text-muted mb-2">{{ number_format($transactions->total(), 0, ',', '.') }} transaksi</p>

{{-- HP: daftar kartu --}}
<div class="d-md-none">
    @forelse ($transactions as $t)
        <a href="{{ route('transactions.show', $t) }}" class="card card-body mb-2 text-decoration-none text-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="small font-monospace text-break">{{ $t->number }}</div>
                @if ($t->isVoid())
                    <span class="badge text-bg-danger"><i class="bi bi-x-circle"></i> Batal</span>
                @endif
            </div>
            <div class="d-flex justify-content-between align-items-end mt-1">
                <div class="small text-muted">{{ $t->transacted_at->format('d/m H:i') }} · {{ $t->cashier->name }} · {{ $t->paymentMethodLabel() }}</div>
                <div class="fw-bold num {{ $t->isVoid() ? 'text-decoration-line-through text-muted' : '' }}">{{ rupiah($t->total) }}</div>
            </div>
        </a>
    @empty
        <div class="card card-body text-center text-muted py-4">Tidak ada transaksi.</div>
    @endforelse
</div>

{{-- Desktop: tabel --}}
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>No. transaksi</th><th>Waktu</th><th>Kegiatan</th><th>Kasir</th><th>Metode</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr class="{{ $t->isVoid() ? 'text-muted' : '' }}">
                    <td class="font-monospace small text-nowrap"><a href="{{ route('transactions.show', $t) }}" class="text-decoration-none">{{ $t->number }}</a></td>
                    <td class="text-nowrap">{{ $t->transacted_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $t->event->name }}</td>
                    <td>{{ $t->cashier->name }}</td>
                    <td>{{ $t->paymentMethodLabel() }}</td>
                    <td class="text-end num fw-semibold {{ $t->isVoid() ? 'text-decoration-line-through' : '' }}">{{ rupiah($t->total) }}</td>
                    <td>
                        @if ($t->isVoid())
                            <span class="badge text-bg-danger"><i class="bi bi-x-circle"></i> Batal</span>
                        @else
                            <span class="badge text-bg-success"><i class="bi bi-check-circle"></i> Berhasil</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('receipts.show', $t) }}?print=1" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak ulang struk"><i class="bi bi-printer"></i><span class="visually-hidden">Cetak ulang</span></a>
                        <a href="{{ route('transactions.show', $t) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $transactions->links() }}</div>
@endsection
