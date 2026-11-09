<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const CLOSED = 'closed';

    public const STATUSES = [
        self::DRAFT => 'Draft',
        self::ACTIVE => 'Aktif',
        self::CLOSED => 'Selesai',
    ];

    protected $fillable = [
        'code', 'name', 'location', 'start_date', 'end_date', 'description',
        'person_in_charge', 'receipt_header', 'receipt_footer',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(EventProduct::class);
    }

    public function cashiers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::CLOSED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::ACTIVE => 'text-bg-success',
            self::CLOSED => 'text-bg-secondary',
            default => 'text-bg-warning',
        };
    }

    /** Alasan kegiatan belum bisa diaktifkan (EV-06); kosong berarti siap. */
    public function activationBlockers(): array
    {
        $blockers = [];

        if (! $this->eventProducts()->where('is_active', true)->exists()) {
            $blockers[] = 'Belum ada produk aktif yang dialokasikan.';
        }

        if (! $this->cashiers()->exists()) {
            $blockers[] = 'Belum ada kasir yang ditugaskan.';
        }

        return $blockers;
    }
}
