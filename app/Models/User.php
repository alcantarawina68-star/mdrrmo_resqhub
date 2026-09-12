<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'contact_number',
        'barangay',
        'status',
        'session_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLog::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        $expected = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role,
            $roles,
        );

        return in_array($this->role?->value, $expected, true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperadmin(): bool
    {
        return in_array($this->role?->value, [UserRole::Superadmin->value], true);
    }

    public function hasActiveSession(): bool
    {
        return $this->session_id !== null
            && DB::table('sessions')
                ->where('id', $this->session_id)
                ->where('user_id', $this->id)
                ->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }
}
