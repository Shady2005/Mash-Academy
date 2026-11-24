<?php

namespace App\Services;

use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_OPENAI = 'openai';

    /**
     * Execute a raw chat/completion request against the configured AI provider (Google Gemini or OpenAI).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, text: ?string, error: ?string, provider: string, model: string}
     */
    public function complete(
        string $systemPrompt,
        array $messages,
        bool $expectJson = false,
        float $temperature = 0.6,
        int $maxOutputTokens = 1600
    ): array {
        $provider = AcademySetting::aiProvider();
        $model = AcademySetting::aiModel($provider);
        $apiKey = AcademySetting::aiApiKey($provider);

        if (! $apiKey) {
            $envName = $provider === self::PROVIDER_OPENAI ? 'OPENAI_API_KEY' : 'GEMINI_API_KEY';

            return [
                'ok' => false,
                'text' => null,
                'error' => "AI API key is not configured yet. Please add your ".($provider === self::PROVIDER_OPENAI ? 'OpenAI' : 'Google Gemini')." API key in Admin → Academy Settings → AI Integration (or set {$envName} in .env).",
                'provider' => $provider,
                'model' => $model,
            ];
        }

        try {
            if ($provider === self::PROVIDER_OPENAI) {
                return $this->callOpenAi($apiKey, $model, $systemPrompt, $messages, $expectJson, $temperature, $maxOutputTokens);
            }

            return $this->callGemini($apiKey, $model, $systemPrompt, $messages, $expectJson, $temperature, $maxOutputTokens);
        } catch (\Throwable $e) {
            Log::warning('AI service request failed', [
                'provider' => $provider,
                'model' => $model,
                'exception' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'text' => null,
                'error' => 'Could not reach the AI provider right now. Please check your internet connection, API key, and model name, then try again.',
                'provider' => $provider,
                'model' => $model,
            ];
        }
    }

    /**
     * Generate polished bilingual (English & Arabic) short and full descriptions for a course.
     *
     * @return array{
     *   ok: bool,
     *   error: ?string,
     *   provider: string,
     *   model: string,
     *   data?: array{
     *     short_description: string,
     *     short_description_ar: string,
     *     description: string,
     *     description_ar: string
     *   }
     * }
     */
    public function generateCourseDescriptions(string $title, ?string $titleAr = null, ?string $notes = null): array
    {
        $systemPrompt = <<<'PROMPT'
You are a senior e-learning curriculum copywriter for Mash Academy.
Given a course title (and optional Arabic title or notes), generate compelling, clear, practical course copy in BOTH English and Modern Standard Arabic.
Return ONLY a valid JSON object with these exact 4 string keys (no markdown fences, no commentary):
{
  "short_description": "1-2 concise sentences in English (max 240 chars)",
  "short_description_ar": "1-2 concise sentences in Arabic (max 240 chars)",
  "description": "Well-structured 2-3 paragraphs in English covering what students will learn, hands-on outcomes, and who it is for.",
  "description_ar": "Well-structured 2-3 paragraphs in Arabic covering what students will learn, hands-on outcomes, and who it is for."
}
PROMPT;

        $userPrompt = "Course Title (English): {$title}";
        if ($titleAr && trim($titleAr) !== '') {
            $userPrompt .= "\nCourse Title (Arabic): ".trim($titleAr);
        }
        if ($notes && trim($notes) !== '') {
            $userPrompt .= "\nAdditional Instructor Notes / Key Topics: ".trim($notes);
        }

        $res = $this->complete(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            expectJson: true,
            temperature: 0.6,
            maxOutputTokens: 1600
        );

        if (! $res['ok'] || ! $res['text']) {
            return $res;
        }

        $parsed = $this->extractJson($res['text']);
        if (! is_array($parsed) || empty($parsed['description'])) {
            return [
                'ok' => false,
                'error' => 'The AI response could not be parsed cleanly into bilingual descriptions. Please try generating again.',
                'provider' => $res['provider'],
                'model' => $res['model'],
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'provider' => $res['provider'],
            'model' => $res['model'],
            'data' => [
                'short_description' => Str::limit(trim((string) ($parsed['short_description'] ?? '')), 500, ''),
                'short_description_ar' => Str::limit(trim((string) ($parsed['short_description_ar'] ?? '')), 500, ''),
                'description' => trim((string) ($parsed['description'] ?? '')),
                'description_ar' => trim((string) ($parsed['description_ar'] ?? '')),
            ],
        ];
    }

    /**
     * Generate multiple-choice quiz questions (4 options each + correct_option index 0..3).
     *
     * @return array{
     *   ok: bool,
     *   error: ?string,
     *   provider: string,
     *   model: string,
     *   questions?: list<array{question_text: string, points: int, options: list<string>, correct_option: int}>
     * }
     */
    public function generateQuizQuestions(string $courseTitle, string $topic, int $count = 5, string $language = 'en'): array
    {
        $count = max(1, min(10, $count));
        $langInstruction = match ($language) {
            'ar' => 'Write all questions and answer options in clear Modern Standard Arabic.',
            'bilingual' => 'Write questions clearly in English with concise Arabic translation in parentheses where helpful.',
            default => 'Write all questions and answer options in clear, accurate English.',
        };

        $systemPrompt = <<<PROMPT
You are an instructional designer creating multiple-choice quiz questions for Mash Academy.
{$langInstruction}
Generate exactly {$count} high-quality multiple-choice questions with 4 distinct options each and exactly 1 correct answer.
Return ONLY a valid JSON object matching this exact schema (no markdown fences, no extra text):
{
  "questions": [
    {
      "question_text": "Clear question stem?",
      "points": 1,
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_option": 0
    }
  ]
}
Where "correct_option" is the 0-based integer index (0, 1, 2, or 3) of the single correct option in "options".
PROMPT;

        $userPrompt = "Course: {$courseTitle}\nTopic / Lesson Notes:\n{$topic}\nNumber of Questions: {$count}";

        $res = $this->complete(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            expectJson: true,
            temperature: 0.5,
            maxOutputTokens: 2400
        );

        if (! $res['ok'] || ! $res['text']) {
            return $res;
        }

        $parsed = $this->extractJson($res['text']);
        $rawList = is_array($parsed) ? ($parsed['questions'] ?? (array_is_list($parsed) ? $parsed : null)) : null;

        if (! is_array($rawList) || $rawList === []) {
            return [
                'ok' => false,
                'error' => 'The AI response did not contain valid multiple-choice questions. Please refine your topic and try again.',
                'provider' => $res['provider'],
                'model' => $res['model'],
            ];
        }

        $normalized = [];
        foreach ($rawList as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qText = trim((string) ($item['question_text'] ?? $item['question'] ?? ''));
            $rawOpts = is_array($item['options'] ?? null) ? array_values($item['options']) : [];
            $options = [];
            foreach ($rawOpts as $opt) {
                $clean = trim((string) $opt);
                if ($clean !== '') {
                    $options[] = $clean;
                }
                if (count($options) === 4) {
                    break;
                }
            }

            if ($qText === '' || count($options) < 2) {
                continue;
            }
            while (count($options) < 4) {
                $options[] = 'None of the above';
            }

            $correctIndex = (int) ($item['correct_option'] ?? $item['correct_index'] ?? 0);
            if ($correctIndex < 0 || $correctIndex >= count($options)) {
                $correctIndex = 0;
            }

            $normalized[] = [
                'question_text' => Str::limit($qText, 500, ''),
                'points' => max(1, min(100, (int) ($item['points'] ?? 1))),
                'options' => $options,
                'correct_option' => $correctIndex,
            ];

            if (count($normalized) >= $count) {
                break;
            }
        }

        if ($normalized === []) {
            return [
                'ok' => false,
                'error' => 'Could not extract valid quiz questions from the AI response. Please try again.',
                'provider' => $res['provider'],
                'model' => $res['model'],
            ];
        }

        return [
            'ok' => true,
            'error' => null,
            'provider' => $res['provider'],
            'model' => $res['model'],
            'questions' => $normalized,
        ];
    }

    /**
     * Answer a student (or admin sandbox) question strictly scoped to the active course & lesson context.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{ok: bool, answer: ?string, error: ?string, provider: string, model: string}
     */
    public function answerCourseQuestion(Course $course, ?Lesson $lesson, string $question, array $history = []): array
    {
        $course->loadMissing('publishedLessons');

        $outline = $course->publishedLessons
            ->map(fn (Lesson $l): string => "- Lesson {$l->position}: {$l->title}".($l->description ? " ({$l->description})" : ''))
            ->implode("\n");

        $lessonContext = $lesson
            ? "Currently Viewing Lesson #{$lesson->position}: {$lesson->title}\nLesson Notes: ".($lesson->description ?: 'N/A')
            : 'Currently Viewing: General Course Overview';

        $systemPrompt = <<<PROMPT
You are the official Mash Academy AI Course Tutor for the course "{$course->title}".
Rules:
1. Stay strictly focused on this course's subject matter, curriculum, and concepts. If the student asks something completely unrelated to the course, politely steer them back to the course topics.
2. Reply in the exact language the student used (Arabic if they write in Arabic, English if they write in English).
3. Be encouraging, structured, concise, and practical. Use bullet points or short code/example snippets when helpful.
4. Never give direct answers to cheat graded quizzes or final projects; instead, guide the student through the underlying concept step by step.

Course Context:
- Course Title: {$course->title}
- Arabic Title: {$course->title_ar}
- Course Description: {$course->description}
- {$lessonContext}
- Course Curriculum Outline:
{$outline}
PROMPT;

        $messages = [];
        foreach (array_slice($history, -6) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => Str::limit($content, 1500)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => trim($question)];

        $res = $this->complete(
            systemPrompt: $systemPrompt,
            messages: $messages,
            expectJson: false,
            temperature: 0.5,
            maxOutputTokens: 1200
        );

        return [
            'ok' => $res['ok'],
            'answer' => $res['text'],
            'error' => $res['error'],
            'provider' => $res['provider'],
            'model' => $res['model'],
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, text: ?string, error: ?string, provider: string, model: string}
     */
    private function callGemini(
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        bool $expectJson,
        float $temperature,
        int $maxOutputTokens
    ): array {
        $safeModel = preg_replace('/[^A-Za-z0-9._-]/', '', $model) ?: 'gemini-2.5-flash';
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$safeModel}:generateContent";

        $contents = [];
        foreach ($messages as $msg) {
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $generationConfig = [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxOutputTokens,
        ];
        if ($expectJson) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $response = Http::timeout(45)
            ->acceptJson()
            ->withQueryParameters(['key' => $apiKey])
            ->post($endpoint, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => $contents,
                'generationConfig' => $generationConfig,
            ]);

        if (! $response->successful()) {
            $apiMsg = (string) ($response->json('error.message') ?? '');
            $friendly = $this->friendlyHttpError('Google Gemini', $response->status(), $apiMsg);

            return [
                'ok' => false,
                'text' => null,
                'error' => $friendly,
                'provider' => self::PROVIDER_GEMINI,
                'model' => $safeModel,
            ];
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $text = collect($parts)->pluck('text')->filter()->implode("\n");
        $text = trim($text);

        if ($text === '') {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'Google Gemini returned an empty response. Please rephrase and try again.',
                'provider' => self::PROVIDER_GEMINI,
                'model' => $safeModel,
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'error' => null,
            'provider' => self::PROVIDER_GEMINI,
            'model' => $safeModel,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{ok: bool, text: ?string, error: ?string, provider: string, model: string}
     */
    private function callOpenAi(
        string $apiKey,
        string $model,
        string $systemPrompt,
        array $messages,
        bool $expectJson,
        float $temperature,
        int $maxOutputTokens
    ): array {
        $safeModel = preg_replace('/[^A-Za-z0-9._:-]/', '', $model) ?: 'gpt-4o-mini';

        $payloadMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        $payload = [
            'model' => $safeModel,
            'messages' => $payloadMessages,
            'temperature' => $temperature,
            'max_tokens' => $maxOutputTokens,
        ];
        if ($expectJson) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(45)
            ->acceptJson()
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            $apiMsg = (string) ($response->json('error.message') ?? '');
            $friendly = $this->friendlyHttpError('OpenAI', $response->status(), $apiMsg);

            return [
                'ok' => false,
                'text' => null,
                'error' => $friendly,
                'provider' => self::PROVIDER_OPENAI,
                'model' => $safeModel,
            ];
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));
        if ($text === '') {
            return [
                'ok' => false,
                'text' => null,
                'error' => 'OpenAI returned an empty response. Please try again.',
                'provider' => self::PROVIDER_OPENAI,
                'model' => $safeModel,
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'error' => null,
            'provider' => self::PROVIDER_OPENAI,
            'model' => $safeModel,
        ];
    }

    private function friendlyHttpError(string $providerName, int $status, string $apiMessage): string
    {
        if (in_array($status, [401, 403], true)) {
            return "{$providerName} rejected the API key (HTTP {$status}). Please verify your API key in Admin → Academy Settings → AI Integration.";
        }
        if ($status === 429) {
            return "{$providerName} rate limit or quota exceeded (HTTP 429). Please wait a moment or check your API plan.";
        }
        if ($status === 404) {
            return "{$providerName} model was not found (HTTP 404). Please check the configured Model Name in AI Integration settings.";
        }

        $cleanMsg = Str::limit(trim($apiMessage), 160);

        return $cleanMsg !== ''
            ? "{$providerName} error (HTTP {$status}): {$cleanMsg}"
            : "{$providerName} returned an unexpected error (HTTP {$status}). Please try again later.";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $clean = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $clean, $m)) {
            $clean = trim($m[1]);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Attempt to slice outermost JSON object/array if leading/trailing prose exists
        $firstBrace = strpos($clean, '{');
        $lastBrace = strrpos($clean, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $decoded = json_decode(substr($clean, $firstBrace, $lastBrace - $firstBrace + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
