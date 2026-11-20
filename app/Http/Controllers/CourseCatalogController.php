<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\CourseAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseCatalogController extends Controller
{
    public function __construct(
        private readonly CourseAccessService $courseAccess
    ) {}

    public function index(): View
    {
        $courses = Course::query()
            ->published()
            ->with(['creator', 'finalProject'])
            ->withCount(['publishedLessons', 'quizzes', 'assignments'])
            ->orderByDesc('is_featured')
            ->orderByRaw('CASE WHEN featured_order IS NULL THEN 99 ELSE featured_order END ASC')
            ->latest()
            ->paginate(9);

        return view('public.courses.index', [
            'courses' => $courses,
        ]);
    }

    public function show(Request $request, Course $course): View
    {
        abort_unless($course->is_published, 404);

        $course->load([
            'creator',
            'publishedLessons',
            'quizzes' => fn ($q) => $q->withCount('questions'),
            'assignments' => fn ($q) => $q->withCount('questions'),
            'finalProject',
        ]);

        $activeEnrollment = null;
        if ($request->user()?->isStudent()) {
            $activeEnrollment = $this->courseAccess->activeEnrollmentFor($request->user(), $course);
        }

        return view('public.courses.show', [
            'course' => $course,
            'activeEnrollment' => $activeEnrollment,
        ]);
    }
}
