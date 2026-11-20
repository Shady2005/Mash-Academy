<?php

namespace App\Models;

use App\Enums\UserRole;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Staff roles that teach courses and appear in "Meet Our Instructors".
     *
     * @var list<string>
     */
    public const INSTRUCTOR_ROLES = [
        UserRole::SuperAdmin1->value,
        UserRole::SuperAdmin2->value,
        UserRole::Teacher->value,
    ];

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

    public function isInstructor(): bool
    {
        return $this->isSuperAdmin() || $this->isTeacher();
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar_path);
    }

    public function instapayQrUrl(): ?string
    {
        if (! $this->instapay_qr_path) {
            return null;
        }

        return Storage::disk('public')->url($this->instapay_qr_path);
    }

    public function localizedInstructorTitle(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->instructor_title_ar)) {
            return $this->instructor_title_ar;
        }

        return (string) ($this->instructor_title ?: ($this->role?->label() ?? 'Instructor'));
    }

    public function localizedInstructorBio(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->instructor_bio_ar)) {
            return $this->instructor_bio_ar;
        }

        return (string) ($this->instructor_bio ?? '');
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

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function finalProjectSubmissions(): HasMany
    {
        return $this->hasMany(FinalProjectSubmission::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
