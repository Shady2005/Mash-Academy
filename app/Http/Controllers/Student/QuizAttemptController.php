<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\ActivityLogger;
use App\Services\DripLockService;
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

        $attempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $student->id)
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

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'integer'],
        ], [
            'answers.required' => 'Please answer the quiz questions before submitting.',
        ]);

        $student = $request->user();
        $submittedAnswers = $validated['answers'];

        $attempt = DB::transaction(function () use ($quiz, $student, $submittedAnswers, $request): QuizAttempt {
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

            $attempt = QuizAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'user_id' => $student->id,
                'score' => $score,
                'max_score' => $maxScore,
                'percentage' => $percentage,
                'passed' => $passed,
                'started_at' => now(),
                'submitted_at' => now(),
            ]);

            foreach ($answerRows as $row) {
                $attempt->answers()->create($row);
            }

            ActivityLogger::log(
                action: 'quiz.submitted',
                subject: $attempt,
                meta: [
                    'quiz_id' => $quiz->id,
                    'course_id' => $quiz->course_id,
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'passed' => $passed,
                ],
                actor: $student,
                ip: $request->ip()
            );

            return $attempt;
        });

        $statusMessage = $attempt->passed
            ? "Quiz passed! Score: {$attempt->score}/{$attempt->max_score} ({$attempt->percentage}%)."
            : "Quiz submitted. Score: {$attempt->score}/{$attempt->max_score} ({$attempt->percentage}%). Required pass mark: {$quiz->pass_mark}%. You may retake the quiz anytime.";

        return redirect()
            ->route('student.courses.quizzes.show', [$course, $quiz])
            ->with($attempt->passed ? 'status' : 'error', $statusMessage);
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
