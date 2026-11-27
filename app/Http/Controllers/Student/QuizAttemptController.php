<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DripLockService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly DripLockService $dripLock
    ) {}

    public function show(Request $request, Course $course, Quiz $quiz): View
    {
        abort_unless($quiz->course_id === $course->id, 404);
        $this->ensureLinkedLessonUnlocked($request, $course, $quiz);

        $student = $request->user();
        $quiz->load(['lesson', 'questions.options']);

        // Check for an active in-progress attempt on timed quizzes
        $activeAttempt = null;
        $remainingSeconds = null;
        $deadlineIso = null;

        if ($quiz->isTimed() && $quiz->questions->isNotEmpty()) {
            $activeAttempt = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $student->id)
                ->whereNull('submitted_at')
                ->latest('id')
                ->first();

            // If an existing in-progress timed attempt already passed its server deadline, auto-finalize it
            if ($activeAttempt && $activeAttempt->started_at) {
                $deadline = $activeAttempt->started_at->copy()->addMinutes((int) $quiz->duration_minutes);
                if (now()->greaterThanOrEqualTo($deadline)) {
                    $this->gradeAndFinalizeAttempt(
                        quiz: $quiz,
                        student: $student,
                        submittedAnswers: [],
                        existingAttempt: $activeAttempt,
                        submittedAt: $deadline,
                        autoSubmitted: true,
                        ip: $request->ip()
                    );
                    $activeAttempt = null;
                }
            }

            $completedCount = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $student->id)
                ->whereNotNull('submitted_at')
                ->count();

            // Auto-start a timed attempt on first visit OR when explicitly clicking Retake (?retake=1)
            if (! $activeAttempt && ($completedCount === 0 || $request->boolean('retake'))) {
                $activeAttempt = QuizAttempt::query()->create([
                    'quiz_id' => $quiz->id,
                    'user_id' => $student->id,
                    'score' => 0,
                    'max_score' => (int) $quiz->questions->sum('points'),
                    'percentage' => 0,
                    'passed' => false,
                    'started_at' => now(),
                    'submitted_at' => null,
                    'auto_submitted' => false,
                ]);
            }

            if ($activeAttempt && $activeAttempt->started_at) {
                $deadline = $activeAttempt->started_at->copy()->addMinutes((int) $quiz->duration_minutes);
                $remainingSeconds = max(0, (int) now()->diffInSeconds($deadline, false));
                $deadlineIso = $deadline->toIso8601String();
            }
        }

        $attempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
            ->whereNotNull('submitted_at')
            ->with('answers')
            ->latest('submitted_at')
            ->get();

        $latestAttempt = $attempts->first();
        $bestAttempt = $attempts->sortByDesc('percentage')->first();

        return view('student.quizzes.show', [
            'course' => $course,
            'quiz' => $quiz,
            'attempts' => $attempts,
            'latestAttempt' => $latestAttempt,
            'bestAttempt' => $bestAttempt,
            'activeAttempt' => $activeAttempt,
            'remainingSeconds' => $remainingSeconds,
            'deadlineIso' => $deadlineIso,
        ]);
    }

    public function submit(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        abort_unless($quiz->course_id === $course->id, 404);
        $this->ensureLinkedLessonUnlocked($request, $course, $quiz);

        $quiz->load('questions.options');
        if ($quiz->questions->isEmpty()) {
            return back()->withErrors(['quiz' => 'This quiz has no questions configured yet.']);
        }

        $isTimed = $quiz->isTimed();
        $rules = [
            'answers' => [$isTimed ? 'nullable' : 'required', 'array'],
            'answers.*' => ['nullable', 'integer'],
            'auto_submitted' => ['nullable', 'boolean'],
        ];

        $validated = $request->validate($rules, [
            'answers.required' => 'Please answer the quiz questions before submitting.',
        ]);

        $student = $request->user();
        $submittedAnswers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];

        $activeAttempt = null;
        $autoSubmitted = $request->boolean('auto_submitted');
        $effectiveSubmittedAt = now();

        if ($isTimed) {
            $activeAttempt = QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('user_id', $student->id)
                ->whereNull('submitted_at')
                ->latest('id')
                ->first();

            if ($activeAttempt && $activeAttempt->started_at) {
                $deadline = $activeAttempt->started_at->copy()->addMinutes((int) $quiz->duration_minutes);
                if (now()->greaterThanOrEqualTo($deadline)) {
                    $autoSubmitted = true;
                    $effectiveSubmittedAt = $deadline;
                }
            }
        }

        $attempt = $this->gradeAndFinalizeAttempt(
            quiz: $quiz,
            student: $student,
            submittedAnswers: $submittedAnswers,
            existingAttempt: $activeAttempt,
            submittedAt: $effectiveSubmittedAt,
            autoSubmitted: $autoSubmitted,
            ip: $request->ip()
        );

        if ($attempt->auto_submitted) {
            $statusMessage = "Time expired — your quiz was automatically submitted and graded. Score: {$attempt->score}/{$attempt->max_score} ({$attempt->percentage}%).";
        } elseif ($attempt->passed) {
            $statusMessage = "Quiz passed! Score: {$attempt->score}/{$attempt->max_score} ({$attempt->percentage}%).";
        } else {
            $statusMessage = "Quiz submitted. Score: {$attempt->score}/{$attempt->max_score} ({$attempt->percentage}%). Required pass mark: {$quiz->pass_mark}%. You may retake the quiz anytime.";
        }

        return redirect()
            ->route('student.courses.quizzes.show', [$course, $quiz])
            ->with($attempt->passed ? 'status' : 'error', $statusMessage);
    }

    /**
     * @param  array<int|string, mixed>  $submittedAnswers
     */
    private function gradeAndFinalizeAttempt(
        Quiz $quiz,
        User $student,
        array $submittedAnswers,
        ?QuizAttempt $existingAttempt,
        CarbonInterface $submittedAt,
        bool $autoSubmitted,
        ?string $ip
    ): QuizAttempt {
        return DB::transaction(function () use ($quiz, $student, $submittedAnswers, $existingAttempt, $submittedAt, $autoSubmitted, $ip): QuizAttempt {
            $score = 0;
            $maxScore = 0;
            $answerRows = [];

            foreach ($quiz->questions as $question) {
                $maxScore += (int) $question->points;
                $chosenOptionId = isset($submittedAnswers[$question->id]) ? (int) $submittedAnswers[$question->id] : null;
                $chosenOption = $chosenOptionId
                    ? $question->options->firstWhere('id', $chosenOptionId)
                    : null;

                $isCorrect = $chosenOption !== null && $chosenOption->is_correct;
                $earned = $isCorrect ? (int) $question->points : 0;
                $score += $earned;

                $answerRows[] = [
                    'question_id' => $question->id,
                    'option_id' => $chosenOption?->id,
                    'is_correct' => $isCorrect,
                    'points_earned' => $earned,
                ];
            }

            $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0;
            $passed = $percentage >= $quiz->pass_mark;

            if ($existingAttempt) {
                $existingAttempt->update([
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'passed' => $passed,
                    'submitted_at' => $submittedAt,
                    'auto_submitted' => $autoSubmitted,
                ]);
                $existingAttempt->answers()->delete();
                $attempt = $existingAttempt;
            } else {
                $attempt = QuizAttempt::query()->create([
                    'quiz_id' => $quiz->id,
                    'user_id' => $student->id,
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'passed' => $passed,
                    'started_at' => $submittedAt,
                    'submitted_at' => $submittedAt,
                    'auto_submitted' => $autoSubmitted,
                ]);
            }

            foreach ($answerRows as $row) {
                $attempt->answers()->create($row);
            }

            ActivityLogger::log(
                action: $autoSubmitted ? 'quiz.auto_submitted' : 'quiz.submitted',
                subject: $attempt,
                meta: [
                    'quiz_id' => $quiz->id,
                    'course_id' => $quiz->course_id,
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'passed' => $passed,
                    'auto_submitted' => $autoSubmitted,
                ],
                actor: $student,
                ip: $ip
            );

            return $attempt;
        });
    }

    private function ensureLinkedLessonUnlocked(Request $request, Course $course, Quiz $quiz): void
    {
        if (! $quiz->lesson_id) {
            return;
        }

        $lesson = $quiz->lesson;
        if (! $lesson) {
            return;
        }

        $check = $this->dripLock->checkLessonAccess($request->user(), $course, $lesson);
        if (! $check['unlocked']) {
            abort(403, $check['reason'] ?? 'Unlock the linked lesson first before taking this quiz.');
        }
    }
}
