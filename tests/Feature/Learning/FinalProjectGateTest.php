<?php

namespace Tests\Feature\Learning;

use App\Enums\UserRole;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FinalProjectSubmission;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalProjectGateTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
        $this->course = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();

        // Attach a private reference brief to the course's Final Project
        $fp = $this->course->finalProject;
        $briefPath = "courses/{$this->course->id}/final-project/brief.pdf";
        Storage::disk('local')->put($briefPath, '%PDF-1.4 brief');
        $fp->attachment_path = $briefPath;
        $fp->attachment_original_name = 'brief.pdf';
        $fp->required_completion_percentage = 95;
        $fp->save();

        app(EnrollmentService::class)->create(
            student: $this->student,
            course: $this->course,
            durationValue: 3,
            durationUnit: Enrollment::UNIT_MONTHS,
            actor: $this->sa1
        );
    }

    public function test_final_project_is_strictly_locked_below_95_percent_and_unlocks_at_or_above_95_percent(): void
    {
        $lessons = $this->course->publishedLessons()->get();
        $assignments = $this->course->assignments()->get();

        // Complete all lessons EXCEPT leaving the course assignment pending (3/4 = 75% < 95%)
        foreach ($lessons as $lesson) {
            LessonProgress::query()->create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'watched_seconds' => 100,
                'max_reached_seconds' => 100,
                'last_position_seconds' => 100,
                'completed_at' => now(),
            ]);
        }

        // Below 95% -> Final Project view, brief download, and submission MUST return 403
        $this->actingAs($this->student)
            ->get(route('student.courses.final-project.show', $this->course))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('student.courses.final-project.brief', $this->course))
            ->assertForbidden();

        $deliverable = UploadedFile::fake()->createWithContent('capstone.zip', 'PK-zip-bytes');
        $this->actingAs($this->student)
            ->post(route('student.courses.final-project.submit', $this->course), [
                'file' => $deliverable,
            ])
            ->assertForbidden();

        // Now complete the course assignment so completion hits 100% (>= 95%)
        foreach ($assignments as $assignment) {
            AssignmentSubmission::query()->create([
                'assignment_id' => $assignment->id,
                'user_id' => $this->student->id,
                'status' => AssignmentSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
        }

        // Final Project is now UNLOCKED!
        $this->actingAs($this->student)
            ->get(route('student.courses.final-project.show', $this->course))
            ->assertOk()
            ->assertSee($this->course->finalProject->title);

        $this->actingAs($this->student)
            ->get(route('student.courses.final-project.brief', $this->course))
            ->assertOk();

        $pdfDeliverable = UploadedFile::fake()->createWithContent('capstone-final.pdf', '%PDF-1.4 final capstone');
        $this->actingAs($this->student)
            ->post(route('student.courses.final-project.submit', $this->course), [
                'file' => $pdfDeliverable,
                'notes' => 'Full capstone deliverable attached.',
            ])
            ->assertRedirect(route('student.courses.final-project.show', $this->course));

        $submission = FinalProjectSubmission::query()
            ->where('final_project_id', $this->course->finalProject->id)
            ->where('user_id', $this->student->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($submission->file_path);

        // Student downloads own deliverable; Super Admin downloads & reviews it
        $this->actingAs($this->student)
            ->get(route('student.courses.final-project.submission-file', $this->course))
            ->assertOk();

        $this->actingAs($this->sa1)
            ->get(route('admin.courses.final-project-submissions.download', [$this->course, $submission]))
            ->assertOk();

        $this->actingAs($this->sa1)
            ->put(route('admin.courses.final-project-submissions.review', [$this->course, $submission]), [
                'grade' => 98,
                'feedback' => 'Outstanding Capstone Project!',
            ])
            ->assertRedirect();

        $this->assertSame(FinalProjectSubmission::STATUS_REVIEWED, $submission->fresh()->status);
    }
}
