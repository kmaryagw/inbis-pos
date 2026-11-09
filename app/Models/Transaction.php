<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    public const PAID = 'paid';
    public const VOID = 'void';

    public const CASH = 'cash';
    public const QRIS = 'qris';

    public const PAYMENT_METHODS = [
        self::CASH => 'Tunai',
        self::QRIS => 'QRIS',
    ];

    protected $fillable = [
        'number', 'idempotency_key', 'event_id', 'cashier_id', 'transacted_at', 'item_count',
        'total', 'payment_method', 'amount_paid', 'change_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'transacted_at' => 'datetime',
            'voided_at' => 'datetime',
            'item_count' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'change_amount' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    public function statusLabel(): string
    {
        return $this->isVoid() ? 'Batal' : 'Berhasil';
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? $this->payment_method;
    }
}
