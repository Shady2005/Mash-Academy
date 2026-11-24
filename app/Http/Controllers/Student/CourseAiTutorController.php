<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\AiUsageLog;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseAiTutorController extends Controller
{
    public function __construct(
        private readonly AIService $ai
    ) {}

    /**
     * Student AI Course Tutor endpoint — strictly gated by `enable_ai_student_tutor` (default OFF).
     */
    public function ask(Request $request, Course $course): JsonResponse
    {
        abort_unless(AcademySetting::isAiStudentTutorEnabled(), 404);

        $user = $request->user();
        $dailyLimit = AcademySetting::aiStudentDailyLimit();
        $usedToday = AiUsageLog::studentTutorCountToday($user);

        if (! $user->isSuperAdmin() && $usedToday >= $dailyLimit) {
            return response()->json([
                'ok' => false,
                'message' => "You have reached your daily AI Tutor limit ({$dailyLimit} questions/day). Your quota will reset tomorrow.",
                'remaining_today' => 0,
                'daily_limit' => $dailyLimit,
            ], 429);
        }

        $validated = $request->validate([
            'lesson_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'min:2', 'max:1500'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1500'],
        ]);

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
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson?->id,
            'feature' => AiUsageLog::FEATURE_STUDENT_TUTOR,
            'provider' => $result['provider'],
            'model' => $result['model'],
            'prompt_excerpt' => Str::limit(trim((string) $validated['question']), 250),
            'status' => $result['ok'] ? 'success' : 'error',
        ]);

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $result['error'],
                'remaining_today' => max(0, $dailyLimit - $usedToday),
                'daily_limit' => $dailyLimit,
            ], 422);
        }

        $newRemaining = $user->isSuperAdmin()
            ? $dailyLimit
            : max(0, $dailyLimit - ($usedToday + 1));

        return response()->json([
            'ok' => true,
            'answer' => $result['answer'],
            'remaining_today' => $newRemaining,
            'daily_limit' => $dailyLimit,
        ]);
    }
}
