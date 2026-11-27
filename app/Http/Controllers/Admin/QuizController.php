<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuizQuestionRequest;
use App\Http\Requests\Admin\QuizRequest;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('manageContent', $course);

        $quizzes = $course->quizzes()
            ->with(['lesson', 'questions.options'])
            ->get();

        $lessons = $course->lessons()->get();

        return view('admin.courses.quizzes.index', [
            'course' => $course,
            'quizzes' => $quizzes,
            'lessons' => $lessons,
        ]);
    }

    public function store(QuizRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('manageContent', $course);

        $validated = $request->validated();

        $quiz = new Quiz();
        $quiz->course_id = $course->id;
        $quiz->lesson_id = $validated['lesson_id'] ?? null;
        $quiz->title = trim((string) $validated['title']);
        $quiz->description = isset($validated['description']) && $validated['description'] !== '' ? trim((string) $validated['description']) : null;
        $quiz->pass_mark = (int) $validated['pass_mark'];
        $quiz->is_required_for_next = $request->boolean('is_required_for_next');
        $quiz->has_timer = $request->boolean('has_timer');
        $quiz->duration_minutes = $quiz->has_timer ? (int) $validated['duration_minutes'] : null;
        $quiz->created_by = $request->user()->id;
        $quiz->save();

        ActivityLogger::log(
            action: 'quiz.created',
            subject: $quiz,
            meta: ['course_id' => $course->id, 'title' => $quiz->title],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', "Quiz \"{$quiz->title}\" created. Add multiple-choice questions below.");
    }

    public function update(QuizRequest $request, Course $course, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id, 404);

        $validated = $request->validated();

        $quiz->lesson_id = $validated['lesson_id'] ?? null;
        $quiz->title = trim((string) $validated['title']);
        $quiz->description = isset($validated['description']) && $validated['description'] !== '' ? trim((string) $validated['description']) : null;
        $quiz->pass_mark = (int) $validated['pass_mark'];
        $quiz->is_required_for_next = $request->boolean('is_required_for_next');
        $quiz->has_timer = $request->boolean('has_timer');
        $quiz->duration_minutes = $quiz->has_timer ? (int) $validated['duration_minutes'] : null;
        $quiz->save();

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', "Quiz \"{$quiz->title}\" updated.");
    }

    public function destroy(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id, 404);

        $title = $quiz->title;
        $quiz->delete();

        ActivityLogger::log(
            action: 'quiz.deleted',
            subject: $course,
            meta: ['course_id' => $course->id, 'quiz_title' => $title],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', "Quiz \"{$title}\" deleted.");
    }

    public function storeQuestion(QuizQuestionRequest $request, Course $course, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($quiz, $validated): void {
            $nextPosition = ((int) $quiz->questions()->max('position')) + 1;

            $question = $quiz->questions()->create([
                'question_text' => trim((string) $validated['question_text']),
                'type' => 'multiple_choice',
                'position' => $nextPosition,
                'points' => (int) $validated['points'],
            ]);

            $correctIndex = (int) $validated['correct_option'];

            foreach ($validated['options'] as $idx => $text) {
                $clean = trim((string) $text);
                if ($clean === '') {
                    continue;
                }

                $question->options()->create([
                    'option_text' => $clean,
                    'is_correct' => ((int) $idx) === $correctIndex,
                ]);
            }
        });

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', 'Question added to quiz.');
    }

    public function destroyQuestion(Course $course, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id && $question->quiz_id === $quiz->id, 404);

        $question->delete();

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', 'Quiz question removed.');
    }
}
