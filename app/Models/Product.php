<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = ['code', 'name', 'category_id', 'description', 'unit', 'photo', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Kode produk dibuat otomatis dari ID (PRD-00001) sehingga selalu unik walau dua admin
     * menyimpan bersamaan. Kode tidak dapat diubah setelah dibuat.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (blank($product->code)) {
                $product->code = 'TMP-'.Str::uuid();
            }
        });

        static::created(function (Product $product) {
            if (str_starts_with($product->code, 'TMP-')) {
                $product->code = self::codeFor($product->id);
                $product->saveQuietly();
            }
        });
    }

    public static function codeFor(int $id): string
    {
        return 'PRD-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }
}
