<?php

namespace Tests\Feature\Analytics;

use App\Enums\UserRole;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsAndSupportReadOnlyTest extends TestCase
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

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create(['name' => 'Omar Khaled', 'email' => 'omar@example.com']);
        $this->course = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();

        app(EnrollmentService::class)->create(
            student: $this->student,
            course: $this->course,
            durationValue: 3,
            durationUnit: Enrollment::UNIT_MONTHS,
            actor: $this->sa1
        );
    }

    public function test_analytics_metrics_match_known_fixtures_and_security_headers_are_set(): void
    {
        // Course has 3 published lessons + 1 assignment = 4 trackable items (quiz is optional)
        $lessons = $this->course->publishedLessons()->get();
        $quiz = $this->course->quizzes()->firstOrFail();
        $assignment = $this->course->assignments()->firstOrFail();

        // Complete 2 of 3 lessons
        foreach ($lessons->take(2) as $lesson) {
            LessonProgress::query()->create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'watched_seconds' => 100,
                'max_reached_seconds' => 100,
                'last_position_seconds' => 100,
                'completed_at' => now(),
            ]);
        }

        // Two quiz attempts: 60% and 100% -> Avg 80%, Best 100%
        QuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
            'score' => 6,
            'max_score' => 10,
            'percentage' => 60,
            'passed' => false,
            'submitted_at' => now()->subMinute(),
        ]);
        QuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'user_id' => $this->student->id,
            'score' => 10,
            'max_score' => 10,
            'percentage' => 100,
            'passed' => true,
            'submitted_at' => now(),
        ]);

        // Submit the 1 assignment -> 3/4 items = 75.0%
        AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'status' => AssignmentSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $rows = app(AnalyticsService::class)->studentProgressTable()->getCollection();
        $row = $rows->first();

        $this->assertSame(2, $row['videos_watched']);
        $this->assertSame(3, $row['total_videos']);
        $this->assertSame(75.0, $row['completion_percentage']);
        $this->assertSame(80.0, $row['quiz_avg']);
        $this->assertSame(100.0, $row['quiz_best']);
        $this->assertSame('Submitted', $row['assignment_status']);
        $this->assertSame('Locked', $row['final_project_status']);

        // Super Admin and Support see Analytics + Security Headers; Teacher gets 403
        $response = $this->actingAs($this->sa1)->get(route('admin.analytics.index'));
        $response->assertOk()
            ->assertSee('Omar Khaled')
            ->assertSee('75%')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->actingAs($this->support)->get(route('admin.analytics.index'))->assertOk()->assertSee('Read-Only Access');
        $this->actingAs($this->support)->get(route('admin.analytics.export'))->assertOk();
        $this->actingAs($this->support)->get(route('admin.students.index'))->assertOk()->assertSee('Omar Khaled');
        $this->actingAs($this->support)->get(route('admin.students.show', $this->student))->assertOk()->assertSee('omar@example.com');

        // Teacher is excluded from Analytics & Support Student directory
        $this->actingAs($this->teacher)->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.students.show', $this->student))->assertForbidden();
    }

    public function test_customer_support_is_strictly_read_only_across_every_write_route(): void
    {
        $enrollment = Enrollment::query()->firstOrFail();

        $this->actingAs($this->support)->put(route('admin.settings.update'), ['academy_name' => 'X'])->assertForbidden();
        $this->actingAs($this->support)->post(route('admin.settings.policy.store'), ['title' => 'X', 'body' => str_repeat('a', 30)])->assertForbidden();
        $this->actingAs($this->support)->delete(route('admin.staff.destroy', $this->teacher->id))->assertForbidden();
        $this->actingAs($this->support)->post(route('admin.courses.store'), ['title' => 'X', 'description' => 'Testing write', 'price' => 10])->assertForbidden();
        $this->actingAs($this->support)->delete(route('admin.courses.destroy', $this->course))->assertForbidden();
        $this->actingAs($this->support)->post(route('admin.promo-codes.store'), ['code' => 'SUP', 'discount_type' => 'fixed', 'discount_value' => 10])->assertForbidden();
        $this->actingAs($this->support)->post(route('admin.enrollments.store'), ['user_id' => $this->student->id, 'course_id' => $this->course->id, 'duration_value' => 1, 'duration_unit' => 'months'])->assertForbidden();
        $this->actingAs($this->support)->post(route('admin.enrollments.revoke', $enrollment))->assertForbidden();
    }

    public function test_analytics_page_has_no_n_plus_one_query_hotspots(): void
    {
        // Enroll 12 additional students to verify constant query count regardless of student rows
        $service = app(EnrollmentService::class);
        $extraStudents = User::factory()->student()->count(12)->create();
        foreach ($extraStudents as $st) {
            $service->create($st, $this->course, 1, Enrollment::UNIT_MONTHS, $this->sa1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->sa1)->get(route('admin.analytics.index'))->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Batched analytics must stay well under 30 queries even with 13+ enrolled students
        $this->assertLessThan(30, $queryCount, "Analytics page executed too many queries ({$queryCount}); check for N+1.");
    }
}
