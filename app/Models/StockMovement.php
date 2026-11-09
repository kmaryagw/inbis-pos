<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const OPENING = 'opening';
    public const IN = 'in';
    public const OUT = 'out';
    public const SALE = 'sale';
    public const VOID = 'void';

    public const TYPES = [
        self::OPENING => 'Stok awal',
        self::IN => 'Stok masuk',
        self::OUT => 'Stok keluar / penyesuaian',
        self::SALE => 'Penjualan',
        self::VOID => 'Pembatalan',
    ];

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_product_id', 'type', 'quantity', 'balance_after',
        'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer'];
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
