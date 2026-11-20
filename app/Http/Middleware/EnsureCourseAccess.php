<?php

namespace App\Http\Middleware;

use App\Models\Course;
use App\Services\CourseAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourseAccess
{
    public function __construct(
        private readonly CourseAccessService $courseAccess
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $courseParam = $request->route('course');
        $course = $courseParam instanceof Course
            ? $courseParam
            : Course::query()
                ->where('slug', $courseParam)
                ->orWhere('id', $courseParam)
                ->first();

        if (! $course) {
            abort(404, 'Course not found.');
        }

        if (! $this->courseAccess->canAccess($user, $course)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'expired' => true,
                    'message' => 'Your course enrollment has expired or is not active. Please subscribe or renew your access.',
                    'redirect' => route('student.courses.subscribe', $course),
                ], 403);
            }

            abort(403, 'You do not have an active enrollment for this course. Please subscribe or renew your access.');
        }

        return $next($request);
    }
}
