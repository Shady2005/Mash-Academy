<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\CourseReview;
use App\Services\ActivityLogger;
use App\Services\DripLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourseReviewController extends Controller
{
    public const REQUIRED_COMPLETION_PERCENTAGE = 50;

    public function __construct(
        private readonly DripLockService $dripLock
    ) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_course_reviews'), 404);

        $user = $request->user();
        $summary = $this->dripLock->courseProgressSummary($user, $course);

        if (! $user->isSuperAdmin() && $summary['completion_percentage'] < self::REQUIRED_COMPLETION_PERCENTAGE) {
            abort(
                403,
                'Reach at least '.self::REQUIRED_COMPLETION_PERCENTAGE.'% course completion before submitting a course review.'
            );
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $review = CourseReview::query()->updateOrCreate(
            [
                'course_id' => $course->id,
                'user_id' => $user->id,
            ],
            [
                'rating' => (int) $validated['rating'],
                'comment' => isset($validated['comment']) && trim((string) $validated['comment']) !== ''
                    ? trim((string) $validated['comment'])
                    : null,
            ]
        );

        ActivityLogger::log(
            action: 'course_review.saved',
            subject: $review,
            meta: ['course_id' => $course->id, 'rating' => $review->rating],
            actor: $user,
            ip: $request->ip()
        );

        return back()->with('status', 'Thank you! Your course review has been saved.');
    }
}
