<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CourseAccessService
{
    /**
     * Returns the student's active, unexpired enrollment for the course (checked at request time).
     */
    public function activeEnrollmentFor(User $user, Course $course, ?CarbonInterface $at = null): ?Enrollment
    {
        if (! $user->is_active || $user->trashed()) {
            return null;
        }

        return Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->currentlyActive($at)
            ->orderByDesc('expires_at')
            ->first();
    }

    /**
     * Request-time gate: Super Admins (1 & 2) always have unrestricted preview access;
     * students require an active, unexpired enrollment right now.
     */
    public function canAccess(User $user, Course $course, ?CarbonInterface $at = null): bool
    {
        if ($user->is_active && ! $user->trashed() && $user->isSuperAdmin()) {
            return true;
        }

        return $this->activeEnrollmentFor($user, $course, $at) !== null;
    }

    /**
     * Batch map of [course_id => Enrollment] for all currently active enrollments of a student.
     *
     * @return Collection<int, Enrollment>
     */
    public function activeEnrollmentsMapFor(User $user, ?CarbonInterface $at = null): Collection
    {
        return Enrollment::query()
            ->where('user_id', $user->id)
            ->currentlyActive($at)
            ->orderByDesc('expires_at')
            ->get()
            ->unique('course_id')
            ->keyBy('course_id');
    }
}
