<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\EventProduct;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan transaksi final (TR-17, BR-11): transaksi tidak dihapus,
 * status menjadi Batal dan stok dikembalikan lewat pergerakan pembalik.
 */
class VoidService
{
    public function __construct(private StockService $stock)
    {
    }

    public function void(Transaction $transaction, User $by, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $by, $reason) {
            $trx = Transaction::with('details')->lockForUpdate()->findOrFail($transaction->id);

            if ($trx->isVoid()) {
                throw new BusinessRuleException('Transaksi ini sudah dibatalkan.');
            }

            $eventProducts = EventProduct::with('product')
                ->whereIn('id', $trx->details->pluck('event_product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($trx->details as $detail) {
                $this->stock->move(
                    $eventProducts[$detail->event_product_id], StockMovement::VOID, $detail->quantity, $by,
                    "Batal {$trx->number}: {$reason}", 'transaction', $trx->id,
                );
            }

            $trx->forceFill([
                'status' => Transaction::VOID,
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => $reason,
            ])->save();

            return $trx;
        });
    }
}
