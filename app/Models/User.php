<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'zd_user_id',
        'can_view_org_tickets',
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
            'can_view_org_tickets' => 'boolean',
        ];
    }

    public function zdUser(): BelongsTo
    {
        return $this->belongsTo(ZdUser::class, 'zd_user_id');
    }

    /**
     * Zendesk user ID for ticket visibility filtering.
     */
    public function getZdIdAttribute(): ?int
    {
        return $this->zdUser?->zd_id;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canViewAllOrgTickets(): bool
    {
        return $this->can_view_org_tickets && $this->zdUser?->org_id !== null;
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
