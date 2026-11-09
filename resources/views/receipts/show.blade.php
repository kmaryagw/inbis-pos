<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $transaction->number }}</title>
    <style>
        /* Kertas thermal 55/58 mm: area cetak efektif ±48 mm. Sesuaikan --paper bila printer berbeda. */
        :root { --paper: 48mm; }
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #e9ecef; }
        body { font-family: ui-monospace, "Courier New", Menlo, monospace; color: #000; }
        .receipt {
            width: var(--paper); margin: 12px auto; padding: 3mm 0; background: #fff;
            font-size: 11px; line-height: 1.35;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .hr { border-top: 1px dashed #000; margin: 2mm 0; }
        .row { display: flex; justify-content: space-between; gap: 2mm; }
        .row span:last-child { text-align: right; white-space: nowrap; }
        .item-name { word-break: break-word; }
        .total { font-size: 14px; font-weight: 700; }
        .void { border: 2px solid #000; text-align: center; font-weight: 700; font-size: 14px; padding: 1mm; margin-bottom: 2mm; }
        .actions { display: flex; gap: 8px; justify-content: center; padding: 12px; font-family: system-ui, sans-serif; }
        .actions button, .actions a {
            font: inherit; font-size: 16px; padding: 12px 18px; border-radius: 10px; border: 1px solid #14365c;
            background: #fff; color: #14365c; text-decoration: none; cursor: pointer;
        }
        .actions .primary { background: #14365c; color: #fff; }

        @media print {
            html, body { background: #fff; }
            .receipt { margin: 0 auto; padding: 0 0 4mm; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
@php($trx = $transaction)
<div class="receipt">
    @if ($trx->status === \App\Models\Transaction::VOID)
        <div class="void">*** DIBATALKAN ***</div>
    @endif
    <div class="center bold">{{ $trx->event->receipt_header ?: 'INBIS UNIVERSITAS UDAYANA' }}</div>
    <div class="center">{{ $trx->event->name }}</div>
    <div class="hr"></div>
    <div>No: {{ $trx->number }}</div>
    <div class="row"><span>{{ $trx->transacted_at->format('d/m/Y H:i') }}</span><span>{{ $trx->cashier->name }}</span></div>
    <div class="hr"></div>
    @foreach ($trx->details as $d)
        <div class="item-name">{{ $d->product_name_snapshot }}</div>
        <div class="row"><span>{{ $d->quantity }} x {{ rupiah($d->unit_price, false) }}</span><span>{{ rupiah($d->subtotal, false) }}</span></div>
    @endforeach
    <div class="hr"></div>
    <div class="row total"><span>TOTAL</span><span>{{ rupiah($trx->total) }}</span></div>
    @if ($trx->payment_method === \App\Models\Transaction::CASH)
        <div class="row"><span>BAYAR</span><span>{{ rupiah($trx->amount_paid) }}</span></div>
        <div class="row"><span>KEMBALI</span><span>{{ rupiah($trx->change_amount) }}</span></div>
    @endif
    <div>Metode: {{ strtoupper($trx->paymentMethodLabel()) }}</div>
    <div class="hr"></div>
    <div class="center">{{ $trx->event->receipt_footer ?: 'Terima kasih telah berbelanja!' }}</div>
</div>

<div class="actions">
    <button type="button" class="primary" onclick="window.print()">Cetak</button>
    <button type="button" id="rawbtBtn" onclick="InbisPrinter.rawbt(@js($escpos))">Cetak via RawBT</button>
    <a href="#" onclick="if (window.opener) { window.close(); } else { history.back(); } return false;">Tutup</a>
</div>

<script src="{{ asset('js/printer.js') }}"></script>
<script>
    // Di Android dengan pengaturan RawBT, tombol RawBT menjadi aksi utama
    if (InbisPrinter.get().mode === 'rawbt') {
        document.getElementById('rawbtBtn').classList.add('primary');
        document.querySelector('.actions .primary').classList.remove('primary');
    }
</script>
@if (request()->boolean('print'))
    <script>
        // Cetak otomatis sesuai pengaturan perangkat (RawBT di Android, dialog browser di laptop)
        window.addEventListener('load', () => setTimeout(() => {
            if (InbisPrinter.get().mode === 'rawbt') InbisPrinter.rawbt(@js($escpos));
            else window.print();
        }, 150));
    </script>
@endif
</body>
</html>
