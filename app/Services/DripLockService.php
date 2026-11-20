<?php

namespace App\Services;

use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class DripLockService
{
    /**
     * Build sequential lock/completion state for every published lesson in a course.
     *
     * @return Collection<int, array{
     *   lesson: Lesson,
     *   progress: ?LessonProgress,
     *   completed: bool,
     *   unlocked: bool,
     *   lock_reason: ?string,
     *   blocking_lesson: ?Lesson,
     *   blocking_quiz: ?Quiz
     * }>
     */
    public function lessonStatesFor(User $student, Course $course): Collection
    {
        $lessons = $course->publishedLessons()->get()->values();
        if ($lessons->isEmpty()) {
            return collect();
        }

        $lessonIds = $lessons->pluck('id');

        $progressByLesson = LessonProgress::query()
            ->where('user_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $requiredQuizzesByLesson = Quiz::query()
            ->where('course_id', $course->id)
            ->where('is_required_for_next', true)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->groupBy('lesson_id');

        $requiredQuizIds = $requiredQuizzesByLesson->flatten()->pluck('id');
        $passedQuizIds = $requiredQuizIds->isNotEmpty()
            ? QuizAttempt::query()
                ->where('user_id', $student->id)
                ->whereIn('quiz_id', $requiredQuizIds)
                ->where('passed', true)
                ->pluck('quiz_id')
                ->unique()
                ->all()
            : [];

        $states = collect();
        $chainOpen = true;
        $blockingLesson = null;
        $blockingQuiz = null;
        $lockReason = null;

        $isSuperAdmin = $student->isSuperAdmin();

        foreach ($lessons as $index => $lesson) {
            /** @var LessonProgress|null $progress */
            $progress = $progressByLesson->get($lesson->id);
            $completed = $progress !== null && $progress->isCompleted();

            $unlocked = $isSuperAdmin || $index === 0 ? true : $chainOpen;

            $states->push([
                'lesson' => $lesson,
                'progress' => $progress,
                'completed' => $completed,
                'unlocked' => $unlocked,
                'lock_reason' => $unlocked ? null : $lockReason,
                'blocking_lesson' => $unlocked ? null : $blockingLesson,
                'blocking_quiz' => $unlocked ? null : $blockingQuiz,
            ]);

            // Determine whether the NEXT lesson in sequence will be unlocked
            if ($chainOpen) {
                if (! $completed) {
                    $chainOpen = false;
                    $blockingLesson = $lesson;
                    $blockingQuiz = null;
                    $lockReason = "Complete Lesson #{$lesson->position} (\"{$lesson->title}\") 100% from start to finish first.";
                } else {
                    /** @var Collection<int, Quiz> $lessonRequiredQuizzes */
                    $lessonRequiredQuizzes = $requiredQuizzesByLesson->get($lesson->id, collect());
                    foreach ($lessonRequiredQuizzes as $reqQuiz) {
                        if (! in_array($reqQuiz->id, $passedQuizIds, true)) {
                            $chainOpen = false;
                            $blockingLesson = $lesson;
                            $blockingQuiz = $reqQuiz;
                            $lockReason = "Pass the required quiz \"{$reqQuiz->title}\" (pass mark: {$reqQuiz->pass_mark}%) to unlock the next lesson.";
                            break;
                        }
                    }
                }
            }
        }

        return $states;
    }

    /**
     * Check whether a specific published lesson in a course is currently unlocked for a student.
     *
     * @return array{unlocked: bool, reason: ?string, blocking_lesson: ?Lesson, blocking_quiz: ?Quiz}
     */
    public function checkLessonAccess(User $student, Course $course, Lesson $lesson): array
    {
        if ($lesson->course_id !== $course->id || ! $lesson->is_published) {
            return [
                'unlocked' => false,
                'reason' => 'This lesson is not available.',
                'blocking_lesson' => null,
                'blocking_quiz' => null,
            ];
        }

        $state = $this->lessonStatesFor($student, $course)
            ->first(fn (array $item): bool => $item['lesson']->id === $lesson->id);

        if (! $state) {
            return [
                'unlocked' => false,
                'reason' => 'Lesson not found in course curriculum.',
                'blocking_lesson' => null,
                'blocking_quiz' => null,
            ];
        }

        return [
            'unlocked' => $state['unlocked'],
            'reason' => $state['lock_reason'],
            'blocking_lesson' => $state['blocking_lesson'],
            'blocking_quiz' => $state['blocking_quiz'],
        ];
    }

    /**
     * Compute overall course completion % (published lessons + required quizzes + course assignments)
     * and evaluate the ≥ 95% Final Project unlock gate.
     *
     * @return array{
     *   total_lessons: int,
     *   completed_lessons: int,
     *   total_required_quizzes: int,
     *   passed_required_quizzes: int,
     *   total_assignments: int,
     *   submitted_assignments: int,
     *   total_items: int,
     *   completed_items: int,
     *   completion_percentage: float,
     *   required_final_project_percentage: int,
     *   has_final_project: bool,
     *   final_project_unlocked: bool
     * }
     */
    public function courseProgressSummary(User $student, Course $course): array
    {
        $publishedLessonIds = $course->publishedLessons()->pluck('id');
        $totalLessons = $publishedLessonIds->count();

        $completedLessons = $totalLessons > 0
            ? LessonProgress::query()
                ->where('user_id', $student->id)
                ->whereIn('lesson_id', $publishedLessonIds)
                ->whereNotNull('completed_at')
                ->count()
            : 0;

        $requiredQuizIds = $course->quizzes()
            ->where('is_required_for_next', true)
            ->pluck('id');
        $totalRequiredQuizzes = $requiredQuizIds->count();

        $passedRequiredQuizzes = $totalRequiredQuizzes > 0
            ? QuizAttempt::query()
                ->where('user_id', $student->id)
                ->whereIn('quiz_id', $requiredQuizIds)
                ->where('passed', true)
                ->distinct('quiz_id')
                ->count('quiz_id')
            : 0;

        $assignmentIds = $course->assignments()->pluck('id');
        $totalAssignments = $assignmentIds->count();

        $submittedAssignments = $totalAssignments > 0
            ? AssignmentSubmission::query()
                ->where('user_id', $student->id)
                ->whereIn('assignment_id', $assignmentIds)
                ->whereIn('status', [AssignmentSubmission::STATUS_SUBMITTED, AssignmentSubmission::STATUS_REVIEWED])
                ->count()
            : 0;

        $totalItems = $totalLessons + $totalRequiredQuizzes + $totalAssignments;
        $completedItems = $completedLessons + $passedRequiredQuizzes + $submittedAssignments;

        $completionPercentage = $totalItems > 0
            ? round(($completedItems / $totalItems) * 100, 1)
            : 0.0;

        $finalProject = $course->relationLoaded('finalProject')
            ? $course->finalProject
            : $course->finalProject()->first();

        $hasFinalProject = $finalProject !== null && $finalProject->is_published;
        $requiredPercentage = $finalProject?->required_completion_percentage ?? 95;
        $finalProjectUnlocked = $hasFinalProject
            && ($student->isSuperAdmin() || ($totalItems > 0 && $completionPercentage >= $requiredPercentage));

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'total_required_quizzes' => $totalRequiredQuizzes,
            'passed_required_quizzes' => $passedRequiredQuizzes,
            'total_assignments' => $totalAssignments,
            'submitted_assignments' => $submittedAssignments,
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'completion_percentage' => $completionPercentage,
            'required_final_project_percentage' => $requiredPercentage,
            'has_final_project' => $hasFinalProject,
            'final_project_unlocked' => $finalProjectUnlocked,
        ];
    }
}
