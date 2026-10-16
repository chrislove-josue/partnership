<?php

namespace App\Models;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'assigned_church_id',
        'is_active',
        'phone',
        'handle_kc',
        'email_verified_at',
        'image',
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

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    public function assignedChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'assigned_church_id');
    }

    public function managedChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'manager_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers métier (miroir de types/auth.ts)
    // ─────────────────────────────────────────────────────────────────────

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->all();
    }

    public function hasPermission(PermissionName|string $permission): bool
    {
        $name = $permission instanceof PermissionName ? $permission->value : $permission;

        return $this->hasPermissionTo($name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }
}
