<?php

namespace App\Models;

use App\Enums\UserRole;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Strictly exclude `role`, `staff_role_key`, `is_active`, `must_change_password`,
     * and `last_login_at` from mass assignment to prevent privilege escalation.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->role === null) {
                $user->role = UserRole::Student;
            }

            $role = $user->role instanceof UserRole
                ? $user->role
                : UserRole::from((string) $user->role);

            if ($role->isStaff()) {
                $duplicateExists = static::withTrashed()
                    ->where('role', $role->value)
                    ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
                    ->exists();

                if ($duplicateExists) {
                    throw new DomainException(
                        "Only one account may hold the '{$role->label()}' role. Exactly 4 staff accounts are permitted."
                    );
                }

                $otherStaffCount = static::withTrashed()
                    ->whereIn('role', UserRole::staffValues())
                    ->when($user->exists, fn ($query) => $query->where('id', '!=', $user->id))
                    ->count();

                if ($otherStaffCount >= 4) {
                    throw new DomainException('Cannot exceed the maximum of 4 staff accounts.');
                }

                $user->staff_role_key = $role->value;
            } else {
                $user->staff_role_key = null;
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    public function isTeacher(): bool
    {
        return $this->role === UserRole::Teacher;
    }

    public function isSupport(): bool
    {
        return $this->role === UserRole::Support;
    }

    public function isStudent(): bool
    {
        return $this->role === UserRole::Student;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        if ($this->role === null) {
            return false;
        }

        foreach ($roles as $role) {
            $expected = $role instanceof UserRole ? $role->value : $role;
            if ($this->role->value === $expected) {
                return true;
            }
        }

        return false;
    }

    public function dashboardRoute(): string
    {
        return $this->isStaff() ? route('admin.dashboard') : route('student.dashboard');
    }

    public function policyAcceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }
}
