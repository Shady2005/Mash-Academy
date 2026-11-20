<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FinalProject;
use App\Models\FinalProjectSubmission;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\PaymentRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService
    ) {}

    /**
     * Top-level summary metrics for the Analytics Dashboard.
     *
     * @return array<string, int>
     */
    public function summaryMetrics(): array
    {
        $this->enrollmentService->expireOverdue();
        $now = now();

        return [
            'active_students' => User::query()
                ->where('role', UserRole::Student->value)
                ->where('is_active', true)
                ->count(),
            'active_enrollments' => Enrollment::query()
                ->currentlyActive($now)
                ->count(),
            'expiring_soon' => Enrollment::query()
                ->currentlyActive($now)
                ->where('expires_at', '<=', $now->copy()->addDays(7))
                ->count(),
            'pending_payments' => PaymentRequest::query()
                ->where('status', PaymentRequest::STATUS_PENDING)
                ->count(),
            'certificates_issued' => Certificate::query()
                ->whereNull('revoked_at')
                ->count(),
            'published_courses' => Course::query()->published()->count(),
        ];
    }

    /**
     * Paginated per-student/per-course progress report computed with batched queries (ZERO N+1!).
     *
     * @param  array{course_id?: mixed, status?: string, from?: string, to?: string, q?: string}  $filters
     */
    public function studentProgressTable(array $filters = [], int $perPage = 15, bool $expireFirst = true): LengthAwarePaginator
    {
        if ($expireFirst) {
            $this->enrollmentService->expireOverdue();
        }

        $query = Enrollment::query()
            ->with(['user', 'course'])
            ->latest('enrollments.created_at');

        if (! empty($filters['course_id'])) {
            $query->where('course_id', (int) $filters['course_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_EXPIRED, Enrollment::STATUS_REVOKED], true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('starts_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('starts_at', '<=', $filters['to']);
        }

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);
            $query->whereHas('user', function ($uq) use ($term): void {
                $uq->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $rows = $this->hydrateEnrollmentMetrics(collect($paginator->items()));
        $paginator->setCollection($rows);

        return $paginator;
    }

    /**
     * Batch-hydrate exact analytics metrics for a collection of Enrollment models in constant queries.
     *
     * @param  Collection<int, Enrollment>  $enrollments
     * @return Collection<int, array<string, mixed>>
     */
    public function hydrateEnrollmentMetrics(Collection $enrollments): Collection
    {
        if ($enrollments->isEmpty()) {
            return collect();
        }

        $userIds = $enrollments->pluck('user_id')->unique()->values();
        $courseIds = $enrollments->pluck('course_id')->unique()->values();

        // 1. Lessons per course
        $lessons = Lesson::query()
            ->whereIn('course_id', $courseIds)
            ->where('is_published', true)
            ->get(['id', 'course_id']);
        $lessonIdsByCourse = $lessons->groupBy('course_id')->map(fn ($g) => $g->pluck('id'));
        $allLessonIds = $lessons->pluck('id');

        // 2. Lesson progress for these users & lessons
        $progressRows = $allLessonIds->isNotEmpty()
            ? LessonProgress::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('lesson_id', $allLessonIds)
                ->get(['user_id', 'lesson_id', 'completed_at', 'updated_at'])
            : collect();
        $progressByUser = $progressRows->groupBy('user_id');

        // 3. Quizzes per course
        $quizzes = Quiz::query()
            ->whereIn('course_id', $courseIds)
            ->get(['id', 'course_id', 'is_required_for_next']);
        $quizzesByCourse = $quizzes->groupBy('course_id');
        $allQuizIds = $quizzes->pluck('id');

        // 4. Quiz attempts for these users & quizzes
        $attempts = $allQuizIds->isNotEmpty()
            ? QuizAttempt::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('quiz_id', $allQuizIds)
                ->orderByDesc('submitted_at')
                ->get(['user_id', 'quiz_id', 'percentage', 'passed', 'submitted_at'])
            : collect();
        $attemptsByUser = $attempts->groupBy('user_id');

        // 5. Assignments per course
        $assignments = Assignment::query()
            ->whereIn('course_id', $courseIds)
            ->get(['id', 'course_id']);
        $assignmentIdsByCourse = $assignments->groupBy('course_id')->map(fn ($g) => $g->pluck('id'));
        $allAssignmentIds = $assignments->pluck('id');

        // 6. Assignment submissions
        $assignmentSubs = $allAssignmentIds->isNotEmpty()
            ? AssignmentSubmission::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('assignment_id', $allAssignmentIds)
                ->get(['user_id', 'assignment_id', 'status', 'submitted_at'])
            : collect();
        $assignSubsByUser = $assignmentSubs->groupBy('user_id');

        // 7. Final projects & submissions
        $finalProjects = FinalProject::query()
            ->whereIn('course_id', $courseIds)
            ->get(['id', 'course_id', 'required_completion_percentage', 'is_published'])
            ->keyBy('course_id');
        $fpIds = $finalProjects->pluck('id');

        $fpSubs = $fpIds->isNotEmpty()
            ? FinalProjectSubmission::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('final_project_id', $fpIds)
                ->get(['user_id', 'final_project_id', 'status', 'grade', 'submitted_at'])
            : collect();
        $fpSubsByKey = $fpSubs->keyBy(fn ($s) => "{$s->user_id}:{$s->final_project_id}");

        // 8. Certificates
        $certificates = Certificate::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('course_id', $courseIds)
            ->get(['user_id', 'course_id', 'certificate_code', 'issued_at', 'revoked_at'])
            ->keyBy(fn ($c) => "{$c->user_id}:{$c->course_id}");

        return $enrollments->map(function (Enrollment $enrollment) use (
            $lessonIdsByCourse,
            $progressByUser,
            $quizzesByCourse,
            $attemptsByUser,
            $assignmentIdsByCourse,
            $assignSubsByUser,
            $finalProjects,
            $fpSubsByKey,
            $certificates
        ): array {
            $uid = $enrollment->user_id;
            $cid = $enrollment->course_id;

            $courseLessonIds = $lessonIdsByCourse->get($cid, collect());
            $totalLessons = $courseLessonIds->count();
            $userProgress = $progressByUser->get($uid, collect())->whereIn('lesson_id', $courseLessonIds);
            $completedLessons = $userProgress->whereNotNull('completed_at')->count();

            $courseQuizzes = $quizzesByCourse->get($cid, collect());
            $courseQuizIds = $courseQuizzes->pluck('id');
            $requiredQuizIds = $courseQuizzes->where('is_required_for_next', true)->pluck('id');
            $userAttempts = $attemptsByUser->get($uid, collect())->whereIn('quiz_id', $courseQuizIds);

            $passedRequiredQuizzes = $userAttempts
                ->whereIn('quiz_id', $requiredQuizIds)
                ->where('passed', true)
                ->pluck('quiz_id')
                ->unique()
                ->count();

            $quizAvg = $userAttempts->isNotEmpty() ? round((float) $userAttempts->avg('percentage'), 1) : null;
            $quizBest = $userAttempts->isNotEmpty() ? round((float) $userAttempts->max('percentage'), 1) : null;
            $quizLatest = $userAttempts->isNotEmpty() ? round((float) $userAttempts->first()->percentage, 1) : null;

            $courseAssignmentIds = $assignmentIdsByCourse->get($cid, collect());
            $totalAssignments = $courseAssignmentIds->count();
            $userAssignSubs = $assignSubsByUser->get($uid, collect())
                ->whereIn('assignment_id', $courseAssignmentIds)
                ->whereIn('status', [AssignmentSubmission::STATUS_SUBMITTED, AssignmentSubmission::STATUS_REVIEWED]);
            $submittedAssignments = $userAssignSubs->count();

            $assignmentStatus = $totalAssignments === 0
                ? 'N/A'
                : ($submittedAssignments >= $totalAssignments ? 'Submitted' : 'Pending');

            // Overall completion % matching DripLockService formula
            $totalItems = $totalLessons + $requiredQuizIds->count() + $totalAssignments;
            $completedItems = $completedLessons + $passedRequiredQuizzes + $submittedAssignments;
            $completionPct = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0.0;

            $fp = $finalProjects->get($cid);
            $fpSub = $fp ? $fpSubsByKey->get("{$uid}:{$fp->id}") : null;
            if (! $fp || ! $fp->is_published) {
                $fpStatus = 'None';
            } elseif ($fpSub) {
                $fpStatus = ucfirst($fpSub->status);
            } elseif ($totalItems > 0 && $completionPct >= $fp->required_completion_percentage) {
                $fpStatus = 'Unlocked (Pending)';
            } else {
                $fpStatus = 'Locked';
            }

            $cert = $certificates->get("{$uid}:{$cid}");

            $lastActivityCandidates = collect([
                $userProgress->max('updated_at'),
                $userAttempts->max('submitted_at'),
                $userAssignSubs->max('submitted_at'),
                $fpSub?->submitted_at,
            ])->filter();

            $lastActivity = $lastActivityCandidates->isNotEmpty()
                ? $lastActivityCandidates->max()
                : $enrollment->updated_at;

            return [
                'enrollment' => $enrollment,
                'student' => $enrollment->user,
                'course' => $enrollment->course,
                'effective_status' => $enrollment->effectiveStatus(),
                'videos_watched' => $completedLessons,
                'total_videos' => $totalLessons,
                'completion_percentage' => $completionPct,
                'quiz_attempts_count' => $userAttempts->count(),
                'quiz_avg' => $quizAvg,
                'quiz_best' => $quizBest,
                'quiz_latest' => $quizLatest,
                'submitted_assignments' => $submittedAssignments,
                'total_assignments' => $totalAssignments,
                'assignment_status' => $assignmentStatus,
                'final_project_status' => $fpStatus,
                'certificate' => $cert,
                'last_activity' => $lastActivity,
            ];
        });
    }

    /**
     * Per-course summary report for the Analytics Dashboard & Charts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function courseReport(): Collection
    {
        $courses = Course::query()
            ->withCount(['publishedLessons', 'enrollments'])
            ->orderBy('title')
            ->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $courseIds = $courses->pluck('id');
        $now = now();

        $activeByCourse = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->currentlyActive($now)
            ->selectRaw('course_id, COUNT(*) as cnt')
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        $certsByCourse = Certificate::query()
            ->whereIn('course_id', $courseIds)
            ->whereNull('revoked_at')
            ->selectRaw('course_id, COUNT(*) as cnt')
            ->groupBy('course_id')
            ->pluck('cnt', 'course_id');

        return $courses->map(function (Course $course) use ($activeByCourse, $certsByCourse): array {
            return [
                'course' => $course,
                'total_enrollments' => (int) $course->enrollments_count,
                'active_enrollments' => (int) ($activeByCourse[$course->id] ?? 0),
                'lessons_count' => (int) $course->published_lessons_count,
                'certificates_issued' => (int) ($certsByCourse[$course->id] ?? 0),
            ];
        });
    }

    /**
     * Chart.js datasets for the Analytics Dashboard.
     *
     * @return array<string, mixed>
     */
    public function chartData(Collection $courseReport): array
    {
        $statusCounts = Enrollment::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'course_labels' => $courseReport->map(fn ($r) => $r['course']->title)->values()->all(),
            'course_active' => $courseReport->map(fn ($r) => $r['active_enrollments'])->values()->all(),
            'course_certificates' => $courseReport->map(fn ($r) => $r['certificates_issued'])->values()->all(),
            'enrollment_statuses' => [
                'active' => (int) ($statusCounts[Enrollment::STATUS_ACTIVE] ?? 0),
                'expired' => (int) ($statusCounts[Enrollment::STATUS_EXPIRED] ?? 0),
                'revoked' => (int) ($statusCounts[Enrollment::STATUS_REVOKED] ?? 0),
            ],
        ];
    }

    /**
     * Full read-only dossier for a single student (Super Admins + Customer Support).
     *
     * @return array<string, mixed>
     */
    public function studentDetailReport(User $student): array
    {
        $this->enrollmentService->expireOverdue();

        $enrollments = Enrollment::query()
            ->with(['course' => fn ($c) => $c->withTrashed(), 'paymentRequest'])
            ->where('user_id', $student->id)
            ->latest()
            ->get();

        $progressRows = $this->hydrateEnrollmentMetrics($enrollments);

        $payments = PaymentRequest::query()
            ->with(['course' => fn ($c) => $c->withTrashed()])
            ->where('user_id', $student->id)
            ->latest()
            ->get();

        $certificates = Certificate::query()
            ->with(['course' => fn ($c) => $c->withTrashed()])
            ->where('user_id', $student->id)
            ->latest('issued_at')
            ->get();

        $latestPolicyAcceptance = $student->policyAcceptances()
            ->with('policy')
            ->latest('accepted_at')
            ->first();

        return [
            'student' => $student,
            'progressRows' => $progressRows,
            'payments' => $payments,
            'certificates' => $certificates,
            'latestPolicyAcceptance' => $latestPolicyAcceptance,
        ];
    }
}
