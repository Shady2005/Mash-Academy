<?php

namespace Tests\Feature\Courses;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\FinalProject;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentsAndFinalProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $teacher;

    private Course $teacherCourse;

    private Course $saCourse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->teacherCourse = Course::query()->where('created_by', $this->teacher->id)->firstOrFail();
        $this->saCourse = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();
    }

    public function test_teacher_can_build_standalone_quiz_with_mcq_questions(): void
    {
        $lesson = $this->teacherCourse->lessons()->firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.quizzes.store', $this->teacherCourse), [
                'title' => 'SQL Mastery Quiz',
                'lesson_id' => $lesson->id,
                'pass_mark' => 80,
                'is_required_for_next' => true,
            ])
            ->assertRedirect(route('admin.courses.quizzes.index', $this->teacherCourse));

        $quiz = Quiz::query()->where('title', 'SQL Mastery Quiz')->firstOrFail();
        $this->assertTrue($quiz->is_required_for_next);
        $this->assertSame(80, $quiz->pass_mark);

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.quizzes.questions.store', [$this->teacherCourse, $quiz]), [
                'question_text' => 'Which clause aggregates grouped rows?',
                'points' => 2,
                'options' => ['GROUP BY', 'ORDER BY', 'LIMIT', ''],
                'correct_option' => 0,
            ])
            ->assertRedirect(route('admin.courses.quizzes.index', $this->teacherCourse));

        $this->assertSame(1, $quiz->questions()->count());
        $this->assertSame(3, $quiz->questions()->first()->options()->count());
        $this->assertSame(1, $quiz->questions()->first()->options()->where('is_correct', true)->count());
    }

    public function test_flexible_assignments_support_file_mcq_and_both_modes(): void
    {
        // 1. File Upload Only
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.store', $this->teacherCourse), [
                'title' => 'File Only Assignment',
                'submission_type' => Assignment::TYPE_FILE,
                'instructions' => 'Upload your SQL schema PDF.',
            ])
            ->assertRedirect(route('admin.courses.assignments.index', $this->teacherCourse));

        $fileAssignment = Assignment::query()->where('title', 'File Only Assignment')->firstOrFail();
        $this->assertTrue($fileAssignment->supportsFile());
        $this->assertFalse($fileAssignment->supportsMcq());

        // Attempting to add MCQ question to File-only assignment should fail cleanly
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.questions.store', [$this->teacherCourse, $fileAssignment]), [
                'question_text' => 'Should not be added',
                'points' => 1,
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ])
            ->assertSessionHasErrors('submission_type');

        // 2. Interactive MCQ Only
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.store', $this->teacherCourse), [
                'title' => 'MCQ Only Assignment',
                'submission_type' => Assignment::TYPE_MCQ,
                'pass_mark' => 75,
            ])
            ->assertRedirect(route('admin.courses.assignments.index', $this->teacherCourse));

        $mcqAssignment = Assignment::query()->where('title', 'MCQ Only Assignment')->firstOrFail();
        $this->assertFalse($mcqAssignment->supportsFile());
        $this->assertTrue($mcqAssignment->supportsMcq());

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.questions.store', [$this->teacherCourse, $mcqAssignment]), [
                'question_text' => 'What does COUNT(*) return?',
                'points' => 3,
                'options' => ['Total row count', 'Sum of values'],
                'correct_option' => 0,
            ])
            ->assertRedirect(route('admin.courses.assignments.index', $this->teacherCourse));

        $this->assertSame(1, $mcqAssignment->questions()->count());

        // 3. Combined (File + MCQ)
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.store', $this->teacherCourse), [
                'title' => 'Combined Assignment',
                'submission_type' => Assignment::TYPE_BOTH,
                'instructions' => 'Upload your workbook AND complete the MCQ verification.',
                'pass_mark' => 70,
            ])
            ->assertRedirect(route('admin.courses.assignments.index', $this->teacherCourse));

        $bothAssignment = Assignment::query()->where('title', 'Combined Assignment')->firstOrFail();
        $this->assertTrue($bothAssignment->supportsFile());
        $this->assertTrue($bothAssignment->supportsMcq());

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.assignments.questions.store', [$this->teacherCourse, $bothAssignment]), [
                'question_text' => 'Which KPI measures retention?',
                'points' => 2,
                'options' => ['Cohort retention rate', 'Server uptime'],
                'correct_option' => 0,
            ])
            ->assertRedirect(route('admin.courses.assignments.index', $this->teacherCourse));

        $this->assertSame(1, $bothAssignment->questions()->count());
    }

    public function test_final_project_configuration_with_95_percent_gate_and_attachment(): void
    {
        Storage::fake('local');

        $briefFile = UploadedFile::fake()->createWithContent('capstone-brief.pdf', '%PDF-1.4 sample brief');

        $this->actingAs($this->teacher)
            ->put(route('admin.courses.final-project.upsert', $this->teacherCourse), [
                'title' => 'Executive Analytics Capstone',
                'instructions' => 'Design a complete KPI warehouse and submit your executive deck to unlock your certificate.',
                'required_completion_percentage' => 95,
                'is_published' => true,
                'attachment' => $briefFile,
            ])
            ->assertRedirect(route('admin.courses.final-project.edit', $this->teacherCourse));

        $fp = FinalProject::query()->where('course_id', $this->teacherCourse->id)->firstOrFail();
        $this->assertSame('Executive Analytics Capstone', $fp->title);
        $this->assertSame(95, $fp->required_completion_percentage);
        $this->assertNotNull($fp->attachment_path);
        Storage::disk('local')->assertExists($fp->attachment_path);

        // Owner can download private attachment
        $this->actingAs($this->teacher)
            ->get(route('admin.courses.final-project.attachment', $this->teacherCourse))
            ->assertOk();

        // Public course page reflects the 95% Final Project & Certificate section
        $this->get(route('courses.show', $this->teacherCourse))
            ->assertOk()
            ->assertSee('Executive Analytics Capstone')
            ->assertSee('95%');

        // Teacher cannot modify Super Admin's Final Project
        $this->actingAs($this->teacher)
            ->put(route('admin.courses.final-project.upsert', $this->saCourse), [
                'title' => 'Unauthorized',
                'instructions' => 'Attempting to overwrite Super Admin course final project.',
                'required_completion_percentage' => 95,
            ])
            ->assertForbidden();
    }
}
