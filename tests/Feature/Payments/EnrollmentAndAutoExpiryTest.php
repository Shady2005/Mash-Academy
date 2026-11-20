<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentAndAutoExpiryTest extends TestCase
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
        $this->student = User::factory()->student()->create();
        $this->course = Course::query()->published()->firstOrFail();
    }

    public function test_enrollment_date_math_handles_days_months_and_month_end_edge_cases(): void
    {
        $service = app(EnrollmentService::class);

        // Non-leap year: Jan 31 + 1 month -> Feb 28 (no overflow into March)
        $jan31 = CarbonImmutable::parse('2026-01-31 12:00:00');
        $this->assertSame('2026-02-28 12:00:00', $service->calculateExpiresAt($jan31, 1, Enrollment::UNIT_MONTHS)->format('Y-m-d H:i:s'));

        // Leap year: Jan 31, 2028 + 1 month -> Feb 29, 2028
        $leapJan31 = CarbonImmutable::parse('2028-01-31 12:00:00');
        $this->assertSame('2028-02-29 12:00:00', $service->calculateExpiresAt($leapJan31, 1, Enrollment::UNIT_MONTHS)->format('Y-m-d H:i:s'));

        // Jan 31 + 3 months -> Apr 30
        $this->assertSame('2026-04-30 12:00:00', $service->calculateExpiresAt($jan31, 3, Enrollment::UNIT_MONTHS)->format('Y-m-d H:i:s'));

        // Days math
        $this->assertSame('2026-02-10 12:00:00', $service->calculateExpiresAt($jan31, 10, Enrollment::UNIT_DAYS)->format('Y-m-d H:i:s'));
    }

    public function test_only_super_admins_can_manage_enrollments(): void
    {
        foreach ([$this->teacher, $this->support, $this->student] as $actor) {
            $this->actingAs($actor)->get(route('admin.enrollments.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.enrollments.store'), [
                'user_id' => $this->student->id,
                'course_id' => $this->course->id,
                'duration_value' => 3,
                'duration_unit' => Enrollment::UNIT_MONTHS,
            ])->assertForbidden();
        }
    }

    public function test_course_access_middleware_allows_active_denies_missing_revoked_and_immediately_blocks_expired_before_scheduler(): void
    {
        // 1. Missing enrollment -> 403
        $this->actingAs($this->student)
            ->get(route('student.courses.access', $this->course))
            ->assertForbidden();

        // 2. Super Admin grants 3-month enrollment starting on 2026-03-01 10:00:00
        $this->travelTo(CarbonImmutable::parse('2026-03-01 10:00:00'));

        $this->actingAs($this->sa1)
            ->post(route('admin.enrollments.store'), [
                'user_id' => $this->student->id,
                'course_id' => $this->course->id,
                'duration_value' => 3,
                'duration_unit' => Enrollment::UNIT_MONTHS,
            ])
            ->assertRedirect(route('admin.enrollments.index'));

        $enrollment = Enrollment::query()->firstOrFail();
        $this->assertSame('2026-06-01 10:00:00', $enrollment->expires_at->format('Y-m-d H:i:s'));

        // Active access allowed (200) and course browse shows Unlocked
        $this->actingAs($this->student)
            ->get(route('student.courses.access', $this->course))
            ->assertOk()
            ->assertSee($this->course->title);

        $this->actingAs($this->student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee('Unlocked');

        // 3. Time travel to 1 second AFTER 3-month expiry (2026-06-01 10:00:01)
        // WITHOUT running the scheduler yet -> access MUST be denied immediately!
        $this->travelTo(CarbonImmutable::parse('2026-06-01 10:00:01'));

        $this->assertSame(Enrollment::STATUS_ACTIVE, $enrollment->fresh()->status);

        $this->actingAs($this->student)
            ->get(route('student.courses.access', $this->course))
            ->assertForbidden();

        // 4. Now run `php artisan enrollments:expire` -> flips ONLY overdue rows to `expired`
        $this->artisan('enrollments:expire')
            ->expectsOutputToContain('Expired 1 overdue enrollment(s).')
            ->assertExitCode(0);

        $this->assertSame(Enrollment::STATUS_EXPIRED, $enrollment->fresh()->status);

        // 5. Extending reactivates access; revoking immediately denies access again
        $this->actingAs($this->sa1)
            ->post(route('admin.enrollments.extend', $enrollment), [
                'duration_value' => 15,
                'duration_unit' => Enrollment::UNIT_DAYS,
            ])
            ->assertRedirect(route('admin.enrollments.index'));

        $this->actingAs($this->student)
            ->get(route('student.courses.access', $this->course))
            ->assertOk();

        $this->actingAs($this->sa1)
            ->post(route('admin.enrollments.revoke', $enrollment))
            ->assertRedirect(route('admin.enrollments.index'));

        $this->assertSame(Enrollment::STATUS_REVOKED, $enrollment->fresh()->status);

        $this->actingAs($this->student)
            ->get(route('student.courses.access', $this->course))
            ->assertForbidden();
    }

    public function test_expire_command_only_flips_overdue_active_enrollments(): void
    {
        $service = app(EnrollmentService::class);
        $otherCourse = Course::query()->where('id', '!=', $this->course->id)->firstOrFail();

        $overdue = $service->create(
            student: $this->student,
            course: $this->course,
            durationValue: 1,
            durationUnit: Enrollment::UNIT_DAYS,
            actor: $this->sa1,
            startsAt: now()->subDays(5)
        );

        $stillActive = $service->create(
            student: $this->student,
            course: $otherCourse,
            durationValue: 30,
            durationUnit: Enrollment::UNIT_DAYS,
            actor: $this->sa1,
            startsAt: now()
        );

        $this->artisan('enrollments:expire')
            ->expectsOutputToContain('Expired 1 overdue enrollment(s).')
            ->assertExitCode(0);

        $this->assertSame(Enrollment::STATUS_EXPIRED, $overdue->fresh()->status);
        $this->assertSame(Enrollment::STATUS_ACTIVE, $stillActive->fresh()->status);
    }
}
