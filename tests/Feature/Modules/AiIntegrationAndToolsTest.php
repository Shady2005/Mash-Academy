<?php

namespace Tests\Feature\Modules;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\AiUsageLog;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiIntegrationAndToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin1;
    private User $teacher;
    private User $support;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->superAdmin1 = User::where('role', UserRole::SuperAdmin1)->firstOrFail();
        $this->teacher = User::where('role', UserRole::Teacher)->firstOrFail();
        $this->support = User::where('role', UserRole::Support)->firstOrFail();
        $this->student = User::factory()->student()->create();
    }

    private function createCourse(User $creator, string $title = 'Laravel API Engineering', string $slug = 'laravel-api-engineering'): Course
    {
        $course = new Course([
            'title' => $title,
            'title_ar' => 'هندسة واجهات لارافيل',
            'slug' => $slug,
            'description' => 'Master modern API development in Laravel.',
            'price' => 1500,
            'currency' => 'EGP',
            'is_published' => true,
        ]);
        $course->created_by = $creator->id;
        $course->save();

        return $course;
    }

    private function enrollStudent(User $student, Course $course): Enrollment
    {
        $enrollment = new Enrollment([
            'duration_value' => 3,
            'duration_unit' => 'months',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $enrollment->user_id = $student->id;
        $enrollment->course_id = $course->id;
        $enrollment->created_by = $this->superAdmin1->id;
        $enrollment->save();

        return $enrollment;
    }

    public function test_ai_settings_defaults_encrypted_api_key_and_role_permissions(): void
    {
        // Defaults: Admin AI Tools ON, Student AI Tutor strictly OFF
        $this->assertTrue(AcademySetting::isAiAdminToolsEnabled());
        $this->assertFalse(AcademySetting::isAiStudentTutorEnabled());
        $this->assertSame('gemini', AcademySetting::aiProvider());
        $this->assertSame('gemini-2.5-flash', AcademySetting::aiModel());

        // Teacher, Support, Student cannot update AI settings
        foreach ([$this->teacher, $this->support, $this->student] as $blocked) {
            $this->actingAs($blocked)
                ->put(route('admin.settings.ai.update'), [
                    'ai_provider' => 'openai',
                    'ai_model' => 'gpt-4o-mini',
                ])
                ->assertForbidden();
        }

        // Super Admin saves encrypted API key & provider config
        $this->actingAs($this->superAdmin1)
            ->put(route('admin.settings.ai.update'), [
                'ai_provider' => 'gemini',
                'ai_model' => 'gemini-2.5-flash',
                'ai_api_key' => 'AIzaSyTestSecretKey1234567890',
                'enable_ai_admin_tools' => '1',
                'enable_ai_student_tutor' => '0',
                'ai_student_daily_limit' => 15,
            ])
            ->assertRedirect();

        // Raw DB value must be encrypted (not plaintext)
        $rawStored = AcademySetting::where('key', 'ai_api_key')->value('value');
        $this->assertStringStartsWith('enc:', (string) $rawStored);
        $this->assertStringNotContainsString('AIzaSyTestSecretKey1234567890', (string) $rawStored);

        // Decrypted helper returns exact key; masked helper hides middle characters
        $this->assertSame('AIzaSyTestSecretKey1234567890', AcademySetting::aiApiKey());
        $this->assertSame('AIza••••••••7890', AcademySetting::maskedAiApiKey());

        // Clearing stored key works cleanly
        $this->actingAs($this->superAdmin1)
            ->delete(route('admin.settings.ai-key.destroy'))
            ->assertRedirect();

        $this->assertFalse(AcademySetting::hasStoredAiApiKey());
    }

    public function test_missing_or_invalid_api_key_returns_graceful_error_without_crashing(): void
    {
        // No key configured
        AcademySetting::setEncryptedAiApiKey(null);

        $this->actingAs($this->superAdmin1)
            ->postJson(route('admin.ai.course-description'), ['title' => 'Docker Fundamentals'])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'API key is not configured'));

        // Invalid API key returning HTTP 401 from Gemini
        AcademySetting::setEncryptedAiApiKey('bad-key-123');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'API key not valid.'],
            ], 401),
        ]);

        $this->actingAs($this->superAdmin1)
            ->postJson(route('admin.ai.course-description'), ['title' => 'Docker Fundamentals'])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'rejected the API key'));
    }

    public function test_bilingual_course_description_generator_supports_gemini_and_openai_and_admin_toggle(): void
    {
        AcademySetting::setEncryptedAiApiKey('fake-gemini-key');

        $mockJson = json_encode([
            'short_description' => 'Build scalable APIs with Laravel.',
            'short_description_ar' => 'ابنِ واجهات برمجية قابلة للتوسع باستخدام لارافيل.',
            'description' => 'In this hands-on course, you will build production REST APIs.',
            'description_ar' => 'في هذه الدورة العملية ستتعلم بناء واجهات برمجية احترافية.',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $mockJson]]],
                ]],
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => $mockJson],
                ]],
            ], 200),
        ]);

        // Button visible on Create Course when enable_ai_admin_tools is ON
        $this->actingAs($this->superAdmin1)
            ->get(route('admin.courses.create'))
            ->assertOk()
            ->assertSee('Generate Description with AI');

        // 1. Test Google Gemini provider
        $this->actingAs($this->teacher)
            ->postJson(route('admin.ai.course-description'), [
                'title' => 'Laravel REST APIs',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'provider' => 'gemini',
                'data' => [
                    'short_description' => 'Build scalable APIs with Laravel.',
                    'short_description_ar' => 'ابنِ واجهات برمجية قابلة للتوسع باستخدام لارافيل.',
                ],
            ]);

        // 2. Switch to OpenAI provider and verify OpenAI Chat Completions integration
        AcademySetting::setMany([
            'ai_provider' => 'openai',
            'ai_model' => 'gpt-4o-mini',
        ]);

        $this->actingAs($this->superAdmin1)
            ->postJson(route('admin.ai.course-description'), [
                'title' => 'Laravel REST APIs',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
            ]);

        // 3. Toggle enable_ai_admin_tools OFF -> UI hidden and endpoint returns 403
        AcademySetting::setValue('enable_ai_admin_tools', '0');

        $this->actingAs($this->superAdmin1)
            ->get(route('admin.courses.create'))
            ->assertOk()
            ->assertDontSee('Generate Description with AI');

        $this->actingAs($this->superAdmin1)
            ->postJson(route('admin.ai.course-description'), ['title' => 'Blocked Test'])
            ->assertForbidden();
    }

    public function test_ai_quiz_generator_drafts_and_saves_selected_questions_respecting_ownership(): void
    {
        AcademySetting::setEncryptedAiApiKey('fake-gemini-key');

        $teacherCourse = $this->createCourse($this->teacher, 'Teacher PHP Course', 'teacher-php-course');
        $adminCourse = $this->createCourse($this->superAdmin1, 'Admin DevOps Course', 'admin-devops-course');

        $teacherQuiz = new Quiz([
            'title' => 'Routing Quiz',
            'pass_mark' => 70,
            'is_required_for_next' => false,
        ]);
        $teacherQuiz->course_id = $teacherCourse->id;
        $teacherQuiz->created_by = $this->teacher->id;
        $teacherQuiz->save();

        $adminQuiz = new Quiz([
            'title' => 'Docker Quiz',
            'pass_mark' => 70,
            'is_required_for_next' => false,
        ]);
        $adminQuiz->course_id = $adminCourse->id;
        $adminQuiz->created_by = $this->superAdmin1->id;
        $adminQuiz->save();

        $quizJson = json_encode([
            'questions' => [
                [
                    'question_text' => 'Which HTTP verb is idempotent for full resource replacement?',
                    'points' => 2,
                    'options' => ['POST', 'PUT', 'PATCH', 'CONNECT'],
                    'correct_option' => 1,
                ],
                [
                    'question_text' => 'Which Artisan command lists all registered routes?',
                    'points' => 1,
                    'options' => ['php artisan route:list', 'php artisan routes:all', 'php artisan list:routes', 'php artisan make:route'],
                    'correct_option' => 0,
                ],
            ],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $quizJson]]],
                ]],
            ], 200),
        ]);

        // Teacher cannot generate AI questions for Super Admin's course
        $this->actingAs($this->teacher)
            ->postJson(route('admin.courses.quizzes.ai-generate', [$adminCourse, $adminQuiz]), [
                'topic' => 'Docker containers and images',
                'count' => 3,
            ])
            ->assertForbidden();

        // Teacher generates drafts for own course quiz
        $response = $this->actingAs($this->teacher)
            ->postJson(route('admin.courses.quizzes.ai-generate', [$teacherCourse, $teacherQuiz]), [
                'topic' => 'Laravel HTTP routing and controllers',
                'count' => 2,
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonCount(2, 'questions');

        $generated = $response->json('questions');

        // Teacher saves only the first selected question (skips second)
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.quizzes.ai-save', [$teacherCourse, $teacherQuiz]), [
                'questions' => [
                    array_merge($generated[0], ['selected' => '1']),
                    array_merge($generated[1], ['selected' => '0']),
                ],
            ])
            ->assertRedirect(route('admin.courses.quizzes.index', $teacherCourse));

        $this->assertSame(1, $teacherQuiz->questions()->count());
        $savedQuestion = $teacherQuiz->questions()->with('options')->firstOrFail();
        $this->assertSame('Which HTTP verb is idempotent for full resource replacement?', $savedQuestion->question_text);
        $this->assertSame(4, $savedQuestion->options->count());
        $this->assertSame('PUT', $savedQuestion->options->firstWhere('is_correct', true)?->option_text);
    }

    public function test_admin_ai_sandbox_works_while_student_tutor_remains_completely_hidden_then_activates_when_enabled(): void
    {
        AcademySetting::setEncryptedAiApiKey('fake-gemini-key');
        AcademySetting::setValue('ai_student_daily_limit', '2');

        $course = $this->createCourse($this->superAdmin1, 'Clean Laravel Architecture', 'clean-laravel-architecture');
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Service Layer Pattern',
            'description' => 'Keeping controllers thin and testable.',
            'position' => 1,
            'duration_seconds' => 120,
            'is_published' => true,
        ]);
        $this->enrollStudent($this->student, $course);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Service classes encapsulate reusable business logic outside controllers.']]],
                ]],
            ], 200),
        ]);

        // 1. Default state: enable_ai_student_tutor is FALSE!
        $this->assertFalse(AcademySetting::isAiStudentTutorEnabled());

        // ZERO AI buttons/widgets/links appear in Student Course Player, Student Dashboard, or Public Home
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$course, $lesson]))
            ->assertOk()
            ->assertDontSee('ma-student-ai-tutor-trigger')
            ->assertDontSee('studentAiTutorDrawer')
            ->assertDontSee('Ask AI Tutor');

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('AI Tutor');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('AI Tutor');

        // Student AI Tutor endpoint strictly returns 404 when OFF
        $this->actingAs($this->student)
            ->postJson(route('student.courses.ai-tutor.ask', $course), [
                'lesson_id' => $lesson->id,
                'question' => 'Why should I use a service class?',
            ])
            ->assertNotFound();

        // 2. Super Admin AI Sandbox works right away while Student Tutor is still OFF!
        $this->actingAs($this->teacher)->get(route('admin.ai-assistant.index'))->assertForbidden();
        $this->actingAs($this->support)->get(route('admin.ai-assistant.index'))->assertForbidden();

        $this->actingAs($this->superAdmin1)
            ->get(route('admin.ai-assistant.index'))
            ->assertOk()
            ->assertSee('Student Preview Chat');

        $this->actingAs($this->superAdmin1)
            ->postJson(route('admin.ai-assistant.ask'), [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'question' => 'Explain the service layer pattern.',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'answer' => 'Service classes encapsulate reusable business logic outside controllers.',
            ]);

        // 3. Super Admin explicitly toggles Student AI Tutor ON
        $this->actingAs($this->superAdmin1)
            ->put(route('admin.settings.ai.update'), [
                'ai_provider' => 'gemini',
                'ai_model' => 'gemini-2.5-flash',
                'enable_ai_admin_tools' => '1',
                'enable_ai_student_tutor' => '1',
                'ai_student_daily_limit' => 2,
            ])
            ->assertRedirect();

        $this->assertTrue(AcademySetting::isAiStudentTutorEnabled());

        // Student Course Player now renders the AI Course Tutor drawer
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$course, $lesson]))
            ->assertOk()
            ->assertSee('ma-student-ai-tutor-trigger')
            ->assertSee('studentAiTutorDrawer')
            ->assertSee('2 / 2 left today');

        // Student asks 2 questions -> succeeds and enforces daily quota of 2
        $this->actingAs($this->student)
            ->postJson(route('student.courses.ai-tutor.ask', $course), [
                'lesson_id' => $lesson->id,
                'question' => 'Question 1: What is a service class?',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'remaining_today' => 1,
                'daily_limit' => 2,
            ]);

        $this->actingAs($this->student)
            ->postJson(route('student.courses.ai-tutor.ask', $course), [
                'lesson_id' => $lesson->id,
                'question' => 'Question 2: Give an example.',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'remaining_today' => 0,
                'daily_limit' => 2,
            ]);

        // 3rd question on same day hits daily quota (429)
        $this->actingAs($this->student)
            ->postJson(route('student.courses.ai-tutor.ask', $course), [
                'lesson_id' => $lesson->id,
                'question' => 'Question 3: Should be rate limited.',
            ])
            ->assertStatus(429)
            ->assertJson([
                'ok' => false,
                'remaining_today' => 0,
            ]);

        $this->assertSame(2, AiUsageLog::studentTutorCountToday($this->student));
    }
}
