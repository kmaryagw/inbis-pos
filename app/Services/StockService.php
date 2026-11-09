<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\EventProduct;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok: setiap perubahan current_stock
 * selalu disertai satu baris stock_movements dalam transaksi database yang sama.
 */
class StockService
{
    /**
     * Catat pergerakan stok. Pemanggil wajib sudah mengunci baris event_product
     * (lockForUpdate) bila berada di dalam transaksi yang lebih besar.
     */
    public function move(
        EventProduct $eventProduct,
        string $type,
        int $quantity,
        User $user,
        ?string $note = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        $newBalance = $eventProduct->current_stock + $quantity;

        if ($newBalance < 0) {
            throw new BusinessRuleException(
                "Stok {$eventProduct->product->name} tidak mencukupi (tersedia {$eventProduct->current_stock})."
            );
        }

        $eventProduct->current_stock = $newBalance;
        if ($type === StockMovement::OPENING) {
            $eventProduct->opening_stock += $quantity;
        }
        $eventProduct->save();

        return $eventProduct->movements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $newBalance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'user_id' => $user->id,
        ]);
    }

    /** Versi mandiri yang membuka transaksi dan mengunci baris sendiri (untuk aksi admin). */
    public function moveLocked(EventProduct $eventProduct, string $type, int $quantity, User $user, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($eventProduct, $type, $quantity, $user, $note) {
            $locked = EventProduct::with('product')->lockForUpdate()->findOrFail($eventProduct->id);

            return $this->move($locked, $type, $quantity, $user, $note);
        });
    }

    /**
     * Ringkasan stok per alokasi produk (ST-06):
     * [event_product_id => [opening, in, sold, out]] dari stock_movements.
     */
    public function summaryForEvent(int $eventId): array
    {
        $rows = StockMovement::query()
            ->join('event_products', 'event_products.id', '=', 'stock_movements.event_product_id')
            ->where('event_products.event_id', $eventId)
            ->groupBy('stock_movements.event_product_id', 'stock_movements.type')
            ->selectRaw('stock_movements.event_product_id, stock_movements.type, SUM(stock_movements.quantity) as qty')
            ->get();

        $summary = [];
        foreach ($rows as $row) {
            $s = $summary[$row->event_product_id] ??= ['opening' => 0, 'in' => 0, 'sold' => 0, 'out' => 0];
            $qty = (int) $row->qty;

            match ($row->type) {
                StockMovement::OPENING => $s['opening'] += $qty,
                StockMovement::IN => $s['in'] += $qty,
                // Terjual bersih = penjualan dikurangi yang dibatalkan
                StockMovement::SALE, StockMovement::VOID => $s['sold'] -= $qty,
                StockMovement::OUT => $s['out'] -= $qty,
                default => null,
            };

            $summary[$row->event_product_id] = $s;
        }

        return $summary;
    }
}
