<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\AiUsageLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\ActivityLogger;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiToolsController extends Controller
{
    public function __construct(
        private readonly AIService $ai
    ) {}

    /**
     * Tool 1: AI Bilingual Course Description Generator (AJAX JSON).
     */
    public function generateCourseDescription(Request $request): JsonResponse
    {
        abort_unless(AcademySetting::isAiAdminToolsEnabled(), 403, 'Dashboard AI Tools are currently disabled in Academy Settings.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->ai->generateCourseDescriptions(
            title: trim((string) $validated['title']),
            titleAr: $validated['title_ar'] ?? null,
            notes: $validated['notes'] ?? null
        );

        AiUsageLog::query()->create([
            'user_id' => $request->user()->id,
            'feature' => AiUsageLog::FEATURE_COURSE_DESCRIPTION,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_excerpt' => Str::limit(trim((string) $validated['title']), 250),
            'status' => $result['ok'] ? 'success' : 'error',
        ]);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'data' => $result['data'],
        ]);
    }

    /**
     * Tool 2A: AI Quiz Multiple-Choice Questions Generator (AJAX JSON preview).
     */
    public function generateQuizQuestions(Request $request, Course $course, Quiz $quiz): JsonResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id, 404);
        abort_unless(AcademySetting::isAiAdminToolsEnabled(), 403, 'Dashboard AI Tools are currently disabled in Academy Settings.');

        $validated = $request->validate([
            'topic' => ['required', 'string', 'min:5', 'max:3000'],
            'count' => ['required', 'integer', 'min:1', 'max:10'],
            'language' => ['nullable', 'in:en,ar,bilingual'],
        ]);

        $result = $this->ai->generateQuizQuestions(
            courseTitle: $course->title,
            topic: trim((string) $validated['topic']),
            count: (int) $validated['count'],
            language: (string) ($validated['language'] ?? 'en')
        );

        AiUsageLog::query()->create([
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'feature' => AiUsageLog::FEATURE_QUIZ_GENERATOR,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_excerpt' => Str::limit(trim((string) $validated['topic']), 250),
            'status' => $result['ok'] ? 'success' : 'error',
        ]);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'questions' => $result['questions'],
        ]);
    }

    /**
     * Tool 2B: Save Reviewed AI-Generated Questions into the Quiz.
     */
    public function saveGeneratedQuizQuestions(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($quiz->course_id === $course->id, 404);
        abort_unless(AcademySetting::isAiAdminToolsEnabled(), 403, 'Dashboard AI Tools are currently disabled in Academy Settings.');

        $validated = $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:15'],
            'questions.*.selected' => ['nullable', 'boolean'],
            'questions.*.question_text' => ['required', 'string', 'min:3', 'max:500'],
            'questions.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:4'],
            'questions.*.options.*' => ['required', 'string', 'max:255'],
            'questions.*.correct_option' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        $savedCount = 0;

        DB::transaction(function () use ($quiz, $validated, &$savedCount): void {
            $position = (int) $quiz->questions()->max('position');

            foreach ($validated['questions'] as $item) {
                if (array_key_exists('selected', $item) && ! $item['selected']) {
                    continue;
                }

                $position++;
                $question = $quiz->questions()->create([
                    'question_text' => trim((string) $item['question_text']),
                    'type' => 'multiple_choice',
                    'position' => $position,
                    'points' => (int) $item['points'],
                ]);

                $correctIndex = (int) $item['correct_option'];
                foreach (array_values($item['options']) as $idx => $optText) {
                    $clean = trim((string) $optText);
                    if ($clean === '') {
                        continue;
                    }
                    $question->options()->create([
                        'option_text' => $clean,
                        'is_correct' => $idx === $correctIndex,
                    ]);
                }

                $savedCount++;
            }
        });

        ActivityLogger::log(
            action: 'quiz.ai_questions_saved',
            subject: $quiz,
            meta: ['course_id' => $course->id, 'saved_count' => $savedCount],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.quizzes.index', $course)
            ->with('status', $savedCount > 0
                ? "{$savedCount} AI-generated question(s) added to \"{$quiz->title}\"."
                : 'No questions were selected to save.');
    }

    /**
     * Tool 3A: Super Admin AI Sandbox / Preview Tester Page.
     */
    public function sandboxIndex(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $courses = Course::query()
            ->with(['publishedLessons' => fn ($q) => $q->orderBy('position')])
            ->orderBy('title')
            ->get();

        $selectedCourseId = (int) $request->query('course_id', $courses->first()?->id ?? 0);

        return view('admin.ai-assistant.index', [
            'courses' => $courses,
            'selectedCourseId' => $selectedCourseId,
            'aiAdminEnabled' => AcademySetting::isAiAdminToolsEnabled(),
            'aiStudentEnabled' => AcademySetting::isAiStudentTutorEnabled(),
            'aiProvider' => AcademySetting::aiProvider(),
            'aiModel' => AcademySetting::aiModel(),
            'hasApiKey' => AcademySetting::aiApiKey() !== null,
        ]);
    }

    /**
     * Tool 3B: Super Admin AI Sandbox Ask Endpoint (AJAX JSON).
     */
    public function sandboxAsk(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(AcademySetting::isAiAdminToolsEnabled(), 403, 'Dashboard AI Tools are currently disabled in Academy Settings.');

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'min:2', 'max:1500'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1500'],
        ]);

        $course = Course::query()->with('publishedLessons')->findOrFail((int) $validated['course_id']);
        $lesson = null;
        if (! empty($validated['lesson_id'])) {
            $lesson = Lesson::query()
                ->where('course_id', $course->id)
                ->where('id', (int) $validated['lesson_id'])
                ->first();
        }

        $result = $this->ai->answerCourseQuestion(
            course: $course,
            lesson: $lesson,
            question: trim((string) $validated['question']),
            history: $validated['history'] ?? []
        );

        AiUsageLog::query()->create([
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson?->id,
            'feature' => AiUsageLog::FEATURE_ADMIN_SANDBOX,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_excerpt' => Str::limit(trim((string) $validated['question']), 250),
            'status' => $result['ok'] ? 'success' : 'error',
        ]);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'answer' => $result['answer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ]);
    }
}
