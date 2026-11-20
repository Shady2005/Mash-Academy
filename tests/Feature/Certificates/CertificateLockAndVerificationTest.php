<?php

namespace Tests\Feature\Certificates;

use App\Enums\UserRole;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateLockAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $teacher;

    private User $support;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create(['name' => 'Salma El-Sayed']);
        $this->course = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();

        app(EnrollmentService::class)->create(
            student: $this->student,
            course: $this->course,
            durationValue: 3,
            durationUnit: Enrollment::UNIT_MONTHS,
            actor: $this->sa1
        );
    }

    public function test_certificate_is_strictly_locked_until_final_project_is_submitted_then_unlocks_and_verifies(): void
    {
        // 1. Before any completion -> 403 on view & download
        $this->actingAs($this->student)->get(route('student.certificates.show', $this->course))->assertForbidden();
        $this->actingAs($this->student)->get(route('student.certificates.download', $this->course))->assertForbidden();

        // 2. Complete 100% of lessons and assignments WITHOUT submitting Final Project -> STILL 403!
        foreach ($this->course->publishedLessons()->get() as $lesson) {
            LessonProgress::query()->create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'watched_seconds' => 100,
                'max_reached_seconds' => 100,
                'last_position_seconds' => 100,
                'completed_at' => now(),
            ]);
        }

        foreach ($this->course->assignments()->get() as $assignment) {
            AssignmentSubmission::query()->create([
                'assignment_id' => $assignment->id,
                'user_id' => $this->student->id,
                'status' => AssignmentSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
        }

        $this->actingAs($this->student)->get(route('student.certificates.show', $this->course))->assertForbidden();
        $this->actingAs($this->student)->get(route('student.certificates.download', $this->course))->assertForbidden();
        $this->assertSame(0, Certificate::count());

        // 3. Submit Final Project -> Certificate unlocks & issues immediately!
        $deliverable = UploadedFile::fake()->createWithContent('capstone.pdf', '%PDF-1.4 capstone deliverable');
        $this->actingAs($this->student)
            ->post(route('student.courses.final-project.submit', $this->course), [
                'file' => $deliverable,
                'notes' => 'Ready for review.',
            ])
            ->assertRedirect(route('student.courses.final-project.show', $this->course));

        $certificate = Certificate::query()
            ->where('user_id', $this->student->id)
            ->where('course_id', $this->course->id)
            ->firstOrFail();

        $this->assertStringStartsWith('MA-', $certificate->certificate_code);

        // Student can view My Certificates, Certificate page, and Download HTML certificate
        $this->actingAs($this->student)
            ->get(route('student.certificates.index'))
            ->assertOk()
            ->assertSee($certificate->certificate_code);

        $this->actingAs($this->student)
            ->get(route('student.certificates.show', $this->course))
            ->assertOk()
            ->assertSee('Salma El-Sayed')
            ->assertSee($certificate->certificate_code);

        $this->actingAs($this->student)
            ->get(route('student.certificates.download', $this->course))
            ->assertOk()
            ->assertHeader('Content-Disposition', "attachment; filename=\"Mash-Academy-Certificate-{$certificate->certificate_code}.html\"");

        // Public verification page validates code
        $this->get(route('certificates.verify', ['code' => $certificate->certificate_code]))
            ->assertOk()
            ->assertSee('Verified Authentic Mash Academy Certificate')
            ->assertSee('Salma El-Sayed');

        // Support can view registry (read-only), CANNOT revoke; Teacher gets 403 on registry
        $this->actingAs($this->support)->get(route('admin.certificates.index'))->assertOk()->assertSee($certificate->certificate_code);
        $this->actingAs($this->support)->post(route('admin.certificates.revoke', $certificate), ['revocation_reason' => 'No'])->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.certificates.index'))->assertForbidden();

        // Super Admin revokes -> student blocked (403) and public verify shows Revoked
        $this->actingAs($this->sa1)
            ->post(route('admin.certificates.revoke', $certificate), ['revocation_reason' => 'Plagiarism detected'])
            ->assertRedirect();

        $this->actingAs($this->student)->get(route('student.certificates.show', $this->course))->assertForbidden();
        $this->get(route('certificates.verify', ['code' => $certificate->certificate_code]))
            ->assertOk()
            ->assertSee('Certificate Revoked');

        // Super Admin reinstates -> valid again
        $this->actingAs($this->sa1)->post(route('admin.certificates.reinstate', $certificate))->assertRedirect();
        $this->actingAs($this->student)->get(route('student.certificates.show', $this->course))->assertOk();
    }
}
