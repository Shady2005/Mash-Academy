<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PaymentRequest;
use App\Services\CourseAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseBrowseController extends Controller
{
    public function __construct(
        private readonly CourseAccessService $courseAccess
    ) {}

    public function index(Request $request): View
    {
        $student = $request->user();

        $courses = Course::query()
            ->published()
            ->with(['creator', 'finalProject'])
            ->withCount(['publishedLessons', 'quizzes', 'assignments'])
            ->orderByDesc('is_featured')
            ->latest()
            ->paginate(9);

        $activeEnrollments = $this->courseAccess->activeEnrollmentsMapFor($student);

        $pendingCourseIds = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('status', PaymentRequest::STATUS_PENDING)
            ->pluck('course_id')
            ->all();

        return view('student.courses.index', [
            'courses' => $courses,
            'activeEnrollments' => $activeEnrollments,
            'pendingCourseIds' => $pendingCourseIds,
        ]);
    }
}
