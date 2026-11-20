<?php

namespace Tests\Feature\Learning;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\User;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuizAndFlexibleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $teacher;

    private User $support;

    private User $student;

    private User $otherStudent;

    private Course $teacherCourse;

    private Lesson $lesson1;

    private Lesson $lesson2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
        $this->otherStudent = User::factory()->student()->create();

        $this->teacherCourse = Course::query()->where('created_by', $this->teacher->id)->firstOrFail();
        $lessons = $this->teacherCourse->publishedLessons()->get();
        $this->lesson1 = $lessons[0];
        $this->lesson2 = $lessons[1];

        foreach ([$this->student, $this->otherStudent] as $s) {
            app(EnrollmentService::class)->create(
                student: $s,
                course: $this->teacherCourse,
                durationValue: 3,
                durationUnit: Enrollment::UNIT_MONTHS,
                actor: $this->sa1
            );
        }
    }

    public function test_quiz_grading_and_required_quiz_gates_next_lesson(): void
    {
        // Mark Lesson 1 as 100% completed
        LessonProgress::query()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson1->id,
            'watched_seconds' => 100,
            'max_reached_seconds' => 100,
            'last_position_seconds' => 100,
            'completed_at' => now(),
        ]);

        // Set Lesson 1's quiz as required for unlocking Lesson 2
        $quiz = $this->teacherCourse->quizzes()->where('lesson_id', $this->lesson1->id)->firstOrFail();
        $quiz->is_required_for_next = true;
        $quiz->pass_mark = 100;
        $quiz->save();

        $question = $quiz->questions()->with('options')->firstOrFail();
        $wrongOption = $question->options->firstWhere('is_correct', false);
        $correctOption = $question->options->firstWhere('is_correct', true);

        // Lesson 2 is still locked because required quiz has not been passed
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->teacherCourse, $this->lesson2]))
            ->assertForbidden();

        // Fail the quiz first
        $this->actingAs($this->student)
            ->post(route('student.courses.quizzes.submit', [$this->teacherCourse, $quiz]), [
                'answers' => [$question->id => $wrongOption->id],
            ])
            ->assertRedirect(route('student.courses.quizzes.show', [$this->teacherCourse, $quiz]));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
            'score' => 0,
            'passed' => false,
        ]);

        // Still locked after failing
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->teacherCourse, $this->lesson2]))
            ->assertForbidden();

        // Retake and pass the quiz
        $this->actingAs($this->student)
            ->post(route('student.courses.quizzes.submit', [$this->teacherCourse, $quiz]), [
                'answers' => [$question->id => $correctOption->id],
            ])
            ->assertRedirect(route('student.courses.quizzes.show', [$this->teacherCourse, $quiz]));

        $this->assertDatabaseHas('quiz_attempts', [
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
            'percentage' => 100,
            'passed' => true,
        ]);

        // Lesson 2 is now UNLOCKED!
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->teacherCourse, $this->lesson2]))
            ->assertOk();
    }

    public function test_flexible_assignment_combined_file_and_mcq_validation_and_staff_review(): void
    {
        $assignment = new Assignment();
        $assignment->course_id = $this->teacherCourse->id;
        $assignment->lesson_id = $this->lesson1->id;
        $assignment->title = 'Combined Practical & MCQ';
        $assignment->submission_type = Assignment::TYPE_BOTH;
        $assignment->instructions = 'Upload PDF and answer MCQ.';
        $assignment->pass_mark = 100;
        $assignment->created_by = $this->teacher->id;
        $assignment->save();

        $q = $assignment->questions()->create([
            'question_text' => 'Primary key uniqueness?',
            'position' => 1,
            'points' => 5,
        ]);
        $rightOpt = $q->options()->create(['option_text' => 'Always unique', 'is_correct' => true]);
        $q->options()->create(['option_text' => 'Always null', 'is_correct' => false]);

        // Reject disallowed file type (.php)
        $badFile = UploadedFile::fake()->createWithContent('hack.php', '<?php echo 1;');
        $this->actingAs($this->student)
            ->post(route('student.courses.assignments.submit', [$this->teacherCourse, $assignment]), [
                'file' => $badFile,
                'answers' => [$q->id => $rightOpt->id],
            ])
            ->assertSessionHasErrors('file');

        // Valid combined submission
        $pdf = UploadedFile::fake()->createWithContent('solution.pdf', '%PDF-1.4 valid solution');
        $this->actingAs($this->student)
            ->post(route('student.courses.assignments.submit', [$this->teacherCourse, $assignment]), [
                'file' => $pdf,
                'notes' => 'Completed both sections.',
                'answers' => [$q->id => $rightOpt->id],
            ])
            ->assertRedirect(route('student.courses.assignments.show', [$this->teacherCourse, $assignment]));

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->student->id)
            ->firstOrFail();

        $this->assertSame(5, $submission->mcq_score);
        $this->assertTrue($submission->mcq_passed);
        Storage::disk('local')->assertExists($submission->file_path);

        // Owning student can download own file; another student gets 404/403 (no access to others' files)
        $this->actingAs($this->student)
            ->get(route('student.courses.assignments.file', [$this->teacherCourse, $assignment]))
            ->assertOk();

        $this->actingAs($this->otherStudent)
            ->get(route('student.courses.assignments.file', [$this->teacherCourse, $assignment]))
            ->assertNotFound();

        // Teacher (owner) and Super Admin can download and review; Support gets 403
        $this->actingAs($this->teacher)
            ->get(route('admin.courses.assignment-submissions.download', [$this->teacherCourse, $submission]))
            ->assertOk();

        $this->actingAs($this->sa1)
            ->put(route('admin.courses.assignment-submissions.review', [$this->teacherCourse, $submission]), [
                'grade' => 96,
                'feedback' => 'Great structured work.',
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame(AssignmentSubmission::STATUS_REVIEWED, $submission->status);
        $this->assertSame('96.00', (string) $submission->grade);

        $this->actingAs($this->support)
            ->get(route('admin.courses.submissions.index', $this->teacherCourse))
            ->assertForbidden();
    }
}
