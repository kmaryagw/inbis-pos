<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'event_product_id', 'product_code_snapshot', 'product_name_snapshot',
        'unit_price', 'quantity', 'subtotal',
    ];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }
}
