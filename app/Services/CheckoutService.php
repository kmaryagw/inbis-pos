<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Simpan transaksi penjualan secara atomik (TR-12, TR-14, NF-05).
     *
     * @param  array<int, int>  $items  event_product_id => quantity
     * @param  int|null  $expectedTotal  total yang dilihat kasir; ditolak jika harga berubah
     */
    public function checkout(
        Event $event,
        User $cashier,
        array $items,
        string $paymentMethod,
        int $amountPaid,
        string $idempotencyKey,
        ?int $expectedTotal = null,
    ): Transaction {
        if ($existing = $this->findByKey($idempotencyKey, $cashier)) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->store(
                $event, $cashier, $items, $paymentMethod, $amountPaid, $idempotencyKey, $expectedTotal
            ));
        } catch (UniqueConstraintViolationException $e) {
            // Dua request dengan kunci sama berjalan bersamaan: kembalikan yang sudah tersimpan
            if ($existing = $this->findByKey($idempotencyKey, $cashier)) {
                return $existing;
            }
            throw $e;
        }
    }

    private function store(
        Event $event,
        User $cashier,
        array $items,
        string $paymentMethod,
        int $amountPaid,
        string $idempotencyKey,
        ?int $expectedTotal,
    ): Transaction {
        // Kunci kegiatan (shared) agar tidak ditutup di tengah transaksi
        $event = Event::query()->sharedLock()->findOrFail($event->id);

        if (! $event->isActive()) {
            throw new BusinessRuleException('Kegiatan tidak aktif; transaksi tidak dapat disimpan.');
        }
        if (! $cashier->canSellAt($event)) {
            throw new BusinessRuleException('Anda tidak ditugaskan pada kegiatan ini.');
        }
        if ($items === []) {
            throw new BusinessRuleException('Keranjang masih kosong.');
        }

        // Urutkan id agar urutan penguncian konsisten (menghindari deadlock antar kasir)
        ksort($items);
        $eventProducts = EventProduct::with('product')
            ->where('event_id', $event->id)
            ->whereIn('id', array_keys($items))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $total = 0;
        $itemCount = 0;
        $lines = [];
        $shortages = [];

        foreach ($items as $eventProductId => $quantity) {
            $ep = $eventProducts->get($eventProductId);

            if (! $ep || ! $ep->is_active) {
                throw new BusinessRuleException('Ada produk yang tidak tersedia pada kegiatan ini.');
            }
            if ($quantity < 1) {
                throw new BusinessRuleException("Jumlah {$ep->product->name} harus lebih dari 0.");
            }
            if ($quantity > $ep->current_stock) {
                $shortages[$ep->id] = $ep->current_stock;
                continue;
            }

            $subtotal = $ep->selling_price * $quantity;
            $total += $subtotal;
            $itemCount += $quantity;
            $lines[] = [$ep, $quantity, $subtotal];
        }

        if ($shortages !== []) {
            $names = $eventProducts->only(array_keys($shortages))
                ->map(fn ($ep) => "{$ep->product->name} (sisa {$ep->current_stock})")
                ->implode(', ');

            throw new BusinessRuleException("Stok tidak mencukupi: {$names}.", ['stock' => $shortages]);
        }

        if ($expectedTotal !== null && $expectedTotal !== $total) {
            throw new BusinessRuleException('Harga produk telah diperbarui. Periksa kembali total belanja.');
        }

        if ($paymentMethod === Transaction::CASH) {
            if ($amountPaid < $total) {
                throw new BusinessRuleException('Uang diterima kurang dari total belanja.');
            }
            $change = $amountPaid - $total;
        } else {
            // QRIS: hanya pencatatan metode; verifikasi dilakukan petugas di aplikasi merchant (BR-08)
            $amountPaid = $total;
            $change = 0;
        }

        $now = now();
        $transaction = Transaction::create([
            'number' => $this->nextNumber($event, $now),
            'idempotency_key' => $idempotencyKey,
            'event_id' => $event->id,
            'cashier_id' => $cashier->id,
            'transacted_at' => $now,
            'item_count' => $itemCount,
            'total' => $total,
            'payment_method' => $paymentMethod,
            'amount_paid' => $amountPaid,
            'change_amount' => $change,
            'status' => Transaction::PAID,
        ]);

        foreach ($lines as [$ep, $quantity, $subtotal]) {
            $transaction->details()->create([
                'event_product_id' => $ep->id,
                'product_code_snapshot' => $ep->product->code,
                'product_name_snapshot' => $ep->product->name,
                'unit_price' => $ep->selling_price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            $this->stock->move(
                $ep, StockMovement::SALE, -$quantity, $cashier,
                $transaction->number, 'transaction', $transaction->id,
            );
        }

        return $transaction;
    }

    /** POS-[KODE]-YYYYMMDD-NNNN, urut per kegiatan per hari, aman dari duplikasi (BR-09). */
    private function nextNumber(Event $event, \DateTimeInterface $at): string
    {
        $date = $at->format('Y-m-d');

        DB::table('transaction_counters')->insertOrIgnore([
            'event_id' => $event->id,
            'date' => $date,
            'last_number' => 0,
        ]);

        $counter = DB::table('transaction_counters')
            ->where(['event_id' => $event->id, 'date' => $date])
            ->lockForUpdate()
            ->first();

        $next = $counter->last_number + 1;

        DB::table('transaction_counters')
            ->where(['event_id' => $event->id, 'date' => $date])
            ->update(['last_number' => $next]);

        return sprintf('POS-%s-%s-%04d', $event->code, $at->format('Ymd'), $next);
    }

    private function findByKey(string $key, User $cashier): ?Transaction
    {
        return Transaction::where('idempotency_key', $key)->where('cashier_id', $cashier->id)->first();
    }
}
