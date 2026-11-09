<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CASHIER = 'kasir';
    public const ROLE_MANAGER = 'pimpinan';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_CASHIER => 'Kasir',
        self::ROLE_MANAGER => 'Pengelola/Pimpinan',
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isCashier(): bool
    {
        return $this->role === self::ROLE_CASHIER;
    }

    /** Admin boleh berjualan di semua kegiatan; kasir hanya yang ditugaskan; pimpinan tidak berjualan. */
    public function canSellAt(Event $event): bool
    {
        if ($this->isManager()) {
            return false;
        }

        return $this->isAdmin() || $this->events()->whereKey($event->id)->exists();
    }

    /** Admin & pimpinan melihat semua kegiatan; kasir hanya kegiatan yang ditugaskan. */
    public function canViewEvent(Event $event): bool
    {
        return ! $this->isCashier() || $this->events()->whereKey($event->id)->exists();
    }

    /** Query kegiatan yang boleh dilihat pengguna. */
    public function visibleEvents(): Builder
    {
        return Event::query()->when($this->isCashier(), fn ($q) => $q->whereHas('cashiers', fn ($q) => $q->whereKey($this->id)));
    }
}
