@extends('layouts.app')
@section('title', $transaction->number)

@php($t = $transaction)

@section('content')
<div class="mx-auto" style="max-width: 760px">
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('transactions.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali</a>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-1 mb-3">
        <div>
            <h1 class="page-title font-monospace text-break">{{ $t->number }}</h1>
            <div class="text-muted small">{{ $t->event->name }}</div>
        </div>
        @if ($t->isVoid())
            <span class="badge text-bg-danger fs-6"><i class="bi bi-x-circle me-1"></i>Batal</span>
        @else
            <span class="badge text-bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Berhasil</span>
        @endif
    </div>

    @if ($t->isVoid())
        <div class="alert alert-danger d-flex gap-2">
            <i class="bi bi-x-octagon-fill"></i>
            <div>
                Dibatalkan oleh <strong>{{ $t->voidedBy?->name }}</strong> pada {{ $t->voided_at->format('d/m/Y H:i') }}.<br>
                Alasan: {{ $t->void_reason }}<br>
                <span class="small">Stok telah dikembalikan dan transaksi ini tidak dihitung dalam laporan penjualan.</span>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5 col-sm-3 fw-normal text-muted">Waktu</dt><dd class="col-7 col-sm-3">{{ $t->transacted_at->format('d/m/Y H:i:s') }}</dd>
                <dt class="col-5 col-sm-3 fw-normal text-muted">Kasir</dt><dd class="col-7 col-sm-3">{{ $t->cashier->name }}</dd>
                <dt class="col-5 col-sm-3 fw-normal text-muted">Metode</dt><dd class="col-7 col-sm-3">{{ $t->paymentMethodLabel() }}</dd>
                <dt class="col-5 col-sm-3 fw-normal text-muted">Jumlah item</dt><dd class="col-7 col-sm-3">{{ $t->item_count }}</dd>
            </dl>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Qty</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                @foreach ($t->details as $d)
                    <tr>
                        <td>{{ $d->product_name_snapshot }}<div class="small text-muted">{{ $d->product_code_snapshot }}</div></td>
                        <td class="text-end num">{{ rupiah($d->unit_price) }}</td>
                        <td class="text-end num">{{ $d->quantity }}</td>
                        <td class="text-end num">{{ rupiah($d->subtotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="3" class="text-end">Total</th><th class="text-end num fs-5">{{ rupiah($t->total) }}</th></tr>
                    @if ($t->payment_method === \App\Models\Transaction::CASH)
                        <tr><td colspan="3" class="text-end text-muted">Diterima</td><td class="text-end num">{{ rupiah($t->amount_paid) }}</td></tr>
                        <tr><td colspan="3" class="text-end text-muted">Kembali</td><td class="text-end num">{{ rupiah($t->change_amount) }}</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('receipts.show', $t) }}?print=1" target="_blank" class="btn btn-primary"><i class="bi bi-printer me-1"></i>Cetak ulang struk</a>
        @if (auth()->user()->isAdmin() && ! $t->isVoid())
            <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#voidForm" aria-expanded="{{ $errors->has('reason') ? 'true' : 'false' }}">
                <i class="bi bi-x-octagon me-1"></i>Batalkan transaksi
            </button>
        @endif
    </div>

    @if (auth()->user()->isAdmin() && ! $t->isVoid())
        <div class="collapse {{ $errors->has('reason') ? 'show' : '' }} mt-3" id="voidForm">
            <form method="post" action="{{ route('transactions.void', $t) }}" class="card card-body border-danger"
                  onsubmit="return confirm('Batalkan transaksi {{ $t->number }}? Stok akan dikembalikan dan tindakan ini tercatat.')">
                @csrf
                <label class="form-label fw-semibold" for="reason">Alasan pembatalan</label>
                <textarea class="form-control mb-2" id="reason" name="reason" rows="2" minlength="5" maxlength="200" required
                          placeholder="Mis. salah input jumlah, pembeli batal membeli">{{ old('reason') }}</textarea>
                <div class="small text-muted mb-3">Transaksi tidak dihapus. Status berubah menjadi Batal, stok dikembalikan, dan nama Anda, waktu, serta alasan dicatat.</div>
                <div><button class="btn btn-danger">Ya, batalkan transaksi</button></div>
            </form>
        </div>
    @endif
</div>
@endsection
