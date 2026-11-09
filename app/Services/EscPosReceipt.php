<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Struk dalam perintah ESC/POS untuk printer thermal 58 mm (mis. iWare X583):
 * font A, 384 dot = 32 karakter per baris. Dikirim ke printer lewat aplikasi RawBT di Android.
 */
class EscPosReceipt
{
    private const ESC = "\x1B";
    private const GS = "\x1D";

    private string $out = '';

    public function __construct(private int $width = 32)
    {
    }

    public function build(Transaction $trx): string
    {
        $trx->loadMissing(['event', 'cashier', 'details']);
        $this->out = self::ESC.'@';                         // inisialisasi printer

        if ($trx->isVoid()) {
            $this->align(1)->bold(true)->line('*** DIBATALKAN ***')->bold(false);
        }

        $this->align(1)->bold(true)
            ->wrap($trx->event->receipt_header ?: 'INBIS UNIVERSITAS UDAYANA')
            ->bold(false)
            ->wrap($trx->event->name)
            ->align(0)
            ->rule()
            ->line('No: '.$trx->number)
            ->columns($trx->transacted_at->format('d/m/Y H:i'), $trx->cashier->name)
            ->rule();

        foreach ($trx->details as $d) {
            $this->wrap($d->product_name_snapshot)
                ->columns("{$d->quantity} x ".$this->money($d->unit_price), $this->money($d->subtotal));
        }

        $this->rule()
            ->bold(true)->size(0x01)                         // tinggi ganda untuk total
            ->columns('TOTAL', 'Rp'.$this->money($trx->total))
            ->size(0x00)->bold(false);

        if ($trx->payment_method === Transaction::CASH) {
            $this->columns('BAYAR', 'Rp'.$this->money($trx->amount_paid))
                ->columns('KEMBALI', 'Rp'.$this->money($trx->change_amount));
        }

        $this->line('Metode: '.strtoupper($trx->paymentMethodLabel()))
            ->rule()
            ->align(1)
            ->wrap($trx->event->receipt_footer ?: 'Terima kasih telah berbelanja!')
            ->align(0);

        // Dorong kertas agar baris terakhir melewati pisau sobek (X583 tanpa auto-cutter)
        $this->out .= self::ESC.'d'.chr(4);

        return $this->out;
    }

    public function base64(Transaction $trx): string
    {
        return base64_encode($this->build($trx));
    }

    private function line(string $text): self
    {
        $this->out .= $this->clean($text)."\n";

        return $this;
    }

    private function wrap(string $text): self
    {
        foreach (explode("\n", wordwrap($this->clean($text), $this->width, "\n", true)) as $part) {
            $this->out .= $part."\n";
        }

        return $this;
    }

    /**
     * Teks kiri dan kanan dalam satu baris. Bila tidak muat, kiri tetap mendapat
     * hingga 16 karakter (mis. tanggal) dan sisanya dipotong.
     */
    private function columns(string $left, string $right): self
    {
        $left = $this->clean($left);
        $right = $this->clean($right);
        $right = substr($right, 0, $this->width - min(strlen($left), 16) - 1);
        $left = substr($left, 0, $this->width - strlen($right) - 1);

        return $this->line(str_pad($left, $this->width - strlen($right)).$right);
    }

    private function rule(): self
    {
        return $this->line(str_repeat('-', $this->width));
    }

    private function align(int $n): self
    {
        $this->out .= self::ESC.'a'.chr($n);

        return $this;
    }

    private function bold(bool $on): self
    {
        $this->out .= self::ESC.'E'.chr($on ? 1 : 0);

        return $this;
    }

    private function size(int $n): self
    {
        $this->out .= self::GS.'!'.chr($n);

        return $this;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    /** Printer hanya mengenal ASCII: ubah huruf beraksen dan buang karakter kontrol. */
    private function clean(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', Str::ascii($text));
    }
}
