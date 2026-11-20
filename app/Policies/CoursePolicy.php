<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Super Admins (1 & 2) and Teacher may access course management.
     * Support and Student are strictly denied.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && ($actor->isSuperAdmin() || $actor->isTeacher());
    }

    public function create(User $actor): bool
    {
        return $actor->is_active && ($actor->isSuperAdmin() || $actor->isTeacher());
    }

    public function view(User $actor, Course $course): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isTeacher() && $course->created_by === $actor->id;
    }

    public function update(User $actor, Course $course): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->isTeacher() && $course->created_by === $actor->id;
    }

    /**
     * Ownership rule:
     * - Super Admins (1 & 2) can delete ANY course.
     * - Teacher can delete ONLY their own courses, and NEVER a course created by Super Admin 1 or 2.
     * - Support and Student cannot delete any course.
     */
    public function delete(User $actor, Course $course): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (! $actor->isTeacher()) {
            return false;
        }

        $creator = $course->relationLoaded('creator') ? $course->creator : $course->creator()->first();
        if ($creator && $creator->isSuperAdmin()) {
            return false;
        }

        return $course->created_by === $actor->id;
    }

    public function manageContent(User $actor, Course $course): bool
    {
        return $this->update($actor, $course);
    }
}
