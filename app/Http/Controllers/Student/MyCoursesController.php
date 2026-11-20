<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\DripLockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyCoursesController extends Controller
{
    public function __construct(
        private readonly DripLockService $dripLock
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $allPublished = Course::query()
                ->published()
                ->with(['creator', 'finalProject'])
                ->latest()
                ->get()
                ->map(fn (Course $course): array => [
                    'enrollment' => null,
                    'course' => $course,
                    'is_active' => true,
                    'is_admin_preview' => true,
                    'progress' => $this->dripLock->courseProgressSummary($user, $course),
                ]);

            return view('student.my-courses.index', [
                'activeCourses' => $allPublished,
                'expiredCourses' => collect(),
            ]);
        }

        $enrollments = Enrollment::query()
            ->with(['course.creator', 'course.finalProject'])
            ->where('user_id', $user->id)
            ->orderByDesc('expires_at')
            ->get()
            ->filter(fn (Enrollment $e): bool => $e->course !== null)
            ->unique('course_id')
            ->values();

        $items = $enrollments->map(function (Enrollment $enrollment) use ($user): array {
            return [
                'enrollment' => $enrollment,
                'course' => $enrollment->course,
                'is_active' => $enrollment->isCurrentlyActive(),
                'is_admin_preview' => false,
                'progress' => $this->dripLock->courseProgressSummary($user, $enrollment->course),
            ];
        });

        return view('student.my-courses.index', [
            'activeCourses' => $items->where('is_active', true)->values(),
            'expiredCourses' => $items->where('is_active', false)->values(),
        ]);
    }
}
