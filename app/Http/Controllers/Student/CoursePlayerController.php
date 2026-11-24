<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\AiUsageLog;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\CourseReview;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use App\Services\CourseAccessService;
use App\Services\DripLockService;
use App\Services\VideoProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoursePlayerController extends Controller
{
    public function __construct(
        private readonly CourseAccessService $courseAccess,
        private readonly DripLockService $dripLock,
        private readonly VideoProgressService $videoProgress
    ) {}

    public function show(Request $request, Course $course, ?Lesson $lesson = null): View
    {
        $student = $request->user();
        $enrollment = $this->courseAccess->activeEnrollmentFor($student, $course);
        $lessonStates = $this->dripLock->lessonStatesFor($student, $course);

        if ($lesson !== null) {
            abort_unless($lesson->course_id === $course->id && $lesson->is_published, 404);

            $check = $this->dripLock->checkLessonAccess($student, $course, $lesson);
            if (! $check['unlocked']) {
                abort(403, $check['reason'] ?? 'Complete the previous lesson first to unlock this lesson.');
            }

            $currentState = $lessonStates->first(fn (array $s): bool => $s['lesson']->id === $lesson->id);
        } else {
            // Pick the first unlocked, uncompleted lesson; fall back to the first lesson
            $currentState = $lessonStates->first(fn (array $s): bool => $s['unlocked'] && ! $s['completed'])
                ?? $lessonStates->first();
        }

        $currentLesson = $currentState['lesson'] ?? null;
        $currentProgress = $currentState['progress'] ?? null;

        // Next lesson in sequence (if any)
        $nextState = null;
        if ($currentLesson) {
            $idx = $lessonStates->search(fn (array $s): bool => $s['lesson']->id === $currentLesson->id);
            if ($idx !== false && isset($lessonStates[$idx + 1])) {
                $nextState = $lessonStates[$idx + 1];
            }
        }

        $course->load([
            'creator',
            'quizzes' => fn ($q) => $q->withCount('questions')->with('lesson'),
            'assignments' => fn ($q) => $q->withCount('questions')->with('lesson'),
            'finalProject',
        ]);

        // Student's best quiz attempt per quiz
        $bestAttemptsByQuiz = QuizAttempt::query()
            ->where('user_id', $student->id)
            ->whereIn('quiz_id', $course->quizzes->pluck('id'))
            ->orderByDesc('percentage')
            ->get()
            ->unique('quiz_id')
            ->keyBy('quiz_id');

        // Student's assignment submissions
        $submissionsByAssignment = AssignmentSubmission::query()
            ->where('user_id', $student->id)
            ->whereIn('assignment_id', $course->assignments->pluck('id'))
            ->get()
            ->keyBy('assignment_id');

        $progressSummary = $this->dripLock->courseProgressSummary($student, $course);

        $finalProjectSubmission = $course->finalProject
            ? $course->finalProject->submissions()->where('user_id', $student->id)->first()
            : null;

        $unlockedLessonIds = $lessonStates
            ->where('unlocked', true)
            ->pluck('lesson.id')
            ->all();

        $qaEnabled = AcademySetting::isFeatureEnabled('enable_course_qa');
        $reviewsEnabled = AcademySetting::isFeatureEnabled('enable_course_reviews');

        $questions = $qaEnabled
            ? CourseQuestion::query()
                ->with(['user', 'lesson', 'replies.user'])
                ->where('course_id', $course->id)
                ->latest()
                ->limit(30)
                ->get()
            : collect();

        $myReview = $reviewsEnabled
            ? CourseReview::query()
                ->where('course_id', $course->id)
                ->where('user_id', $student->id)
                ->first()
            : null;

        $canReview = $reviewsEnabled && ($student->isSuperAdmin() || $progressSummary['completion_percentage'] >= 50);

        $aiTutorEnabled = AcademySetting::isAiStudentTutorEnabled();
        $aiTutorDailyLimit = AcademySetting::aiStudentDailyLimit();
        $aiTutorRemainingToday = $aiTutorEnabled
            ? ($student->isSuperAdmin() ? $aiTutorDailyLimit : max(0, $aiTutorDailyLimit - AiUsageLog::studentTutorCountToday($student)))
            : 0;

        return view('student.courses.learn', [
            'course' => $course,
            'enrollment' => $enrollment,
            'lessonStates' => $lessonStates,
            'currentLesson' => $currentLesson,
            'currentProgress' => $currentProgress,
            'currentCompleted' => $currentState['completed'] ?? false,
            'nextState' => $nextState,
            'progressSummary' => $progressSummary,
            'bestAttemptsByQuiz' => $bestAttemptsByQuiz,
            'submissionsByAssignment' => $submissionsByAssignment,
            'finalProjectSubmission' => $finalProjectSubmission,
            'unlockedLessonIds' => $unlockedLessonIds,
            'qaEnabled' => $qaEnabled,
            'questions' => $questions,
            'reviewsEnabled' => $reviewsEnabled,
            'myReview' => $myReview,
            'canReview' => $canReview,
            'aiTutorEnabled' => $aiTutorEnabled,
            'aiTutorDailyLimit' => $aiTutorDailyLimit,
            'aiTutorRemainingToday' => $aiTutorRemainingToday,
        ]);
    }

    /**
     * Secure student video streaming endpoint with HTTP Range (206 Partial Content) support.
     * Enforces BOTH active enrollment (`course.access` middleware) AND sequential drip-lock!
     */
    public function stream(Request $request, Course $course, Lesson $lesson): StreamedResponse
    {
        abort_unless($lesson->course_id === $course->id && $lesson->is_published, 404);

        $check = $this->dripLock->checkLessonAccess($request->user(), $course, $lesson);
        if (! $check['unlocked']) {
            abort(403, $check['reason'] ?? 'This lesson is locked.');
        }

        abort_unless($lesson->hasUploadedVideo(), 404, 'Video file not found.');

        $fullPath = Storage::disk('local')->path($lesson->video_path);
        $fileSize = filesize($fullPath);
        $mime = $lesson->video_mime ?: 'video/mp4';

        $start = 0;
        $end = $fileSize - 1;
        $status = 200;

        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline',
        ];

        $rangeHeader = $request->header('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $matches)) {
            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }
            if ($matches[2] !== '') {
                $end = min((int) $matches[2], $fileSize - 1);
            }

            if ($start > $end || $start >= $fileSize) {
                abort(416, 'Requested Range Not Satisfiable');
            }

            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $length = ($end - $start) + 1;
        $headers['Content-Length'] = (string) $length;

        return response()->stream(function () use ($fullPath, $start, $length): void {
            $handle = fopen($fullPath, 'rb');
            if ($handle === false) {
                return;
            }

            fseek($handle, $start);
            $remaining = $length;
            $chunkSize = 1024 * 64;

            while ($remaining > 0 && ! feof($handle)) {
                $readLength = min($chunkSize, $remaining);
                $buffer = fread($handle, $readLength);
                if ($buffer === false) {
                    break;
                }
                echo $buffer;
                flush();
                $remaining -= strlen($buffer);
            }

            fclose($handle);
        }, $status, $headers);
    }

    /**
     * AJAX Progress Heartbeat API with strict 100% full-watch & server-side anti-skip validation.
     */
    public function recordProgress(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->course_id === $course->id && $lesson->is_published, 404);

        $student = $request->user();
        $check = $this->dripLock->checkLessonAccess($student, $course, $lesson);
        if (! $check['unlocked']) {
            return response()->json([
                'message' => $check['reason'] ?? 'This lesson is locked.',
            ], 403);
        }

        $validated = $request->validate([
            'current_time' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'numeric', 'min:1'],
            'delta_seconds' => ['required', 'integer', 'min:0', 'max:60'],
            'ended' => ['nullable', 'boolean'],
        ]);

        $result = $this->videoProgress->recordHeartbeat(
            student: $student,
            lesson: $lesson,
            currentTime: (float) $validated['current_time'],
            reportedDuration: (float) $validated['duration'],
            deltaSeconds: (int) $validated['delta_seconds'],
            ended: $request->boolean('ended')
        );

        $courseSummary = $this->dripLock->courseProgressSummary($student, $course);

        $nextLessonUrl = null;
        if ($result['completed']) {
            $states = $this->dripLock->lessonStatesFor($student, $course);
            $idx = $states->search(fn (array $s): bool => $s['lesson']->id === $lesson->id);
            if ($idx !== false && isset($states[$idx + 1]) && $states[$idx + 1]['unlocked']) {
                $nextLessonUrl = route('student.courses.learn', [$course, $states[$idx + 1]['lesson']]);
            }
        }

        return response()->json([
            'completed' => $result['completed'],
            'just_completed' => $result['just_completed'],
            'skipped_blocked' => $result['skipped_blocked'],
            'allowed_position' => $result['allowed_position'],
            'last_position_seconds' => $result['progress']->last_position_seconds,
            'watched_seconds' => $result['progress']->watched_seconds,
            'effective_duration' => $result['effective_duration'],
            'watched_percentage' => $result['watched_percentage'],
            'message' => $result['message'],
            'course_completion_percentage' => $courseSummary['completion_percentage'],
            'final_project_unlocked' => $courseSummary['final_project_unlocked'],
            'next_lesson_url' => $nextLessonUrl,
        ], $result['skipped_blocked'] && $request->boolean('ended') ? 422 : 200);
    }
}
