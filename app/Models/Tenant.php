<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    public const DOSEN = 'dosen';
    public const TENANT = 'tenant';

    public const TYPES = [self::DOSEN => 'Dosen', self::TENANT => 'Tenant'];

    protected $fillable = [
        'name', 'type', 'unit', 'contact_person', 'phone', 'email',
        'bank_name', 'bank_account_number', 'bank_account_name', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Kode dibuat otomatis dari ID (TNT-0001), tidak dapat diubah. */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (blank($tenant->code)) {
                $tenant->code = 'TMP-'.Str::random(12);
            }
        });

        static::created(function (Tenant $tenant) {
            if (str_starts_with($tenant->code, 'TMP-')) {
                $tenant->code = self::codeFor($tenant->id);
                $tenant->saveQuietly();
            }
        });
    }

    public static function codeFor(int $id): string
    {
        return 'TNT-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function transactionDetails(): HasMany
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function typeBadgeClass(): string
    {
        return $this->type === self::DOSEN ? 'text-bg-info' : 'text-bg-warning';
    }

    public function bankAccount(): ?string
    {
        if (blank($this->bank_account_number)) {
            return null;
        }

        return trim("{$this->bank_name} {$this->bank_account_number}".($this->bank_account_name ? " a.n. {$this->bank_account_name}" : ''));
    }
}
