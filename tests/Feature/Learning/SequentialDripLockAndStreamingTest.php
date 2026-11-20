<?php

namespace Tests\Feature\Learning;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SequentialDripLockAndStreamingTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $student;

    private User $unenrolledStudent;

    private Course $course;

    /** @var list<Lesson> */
    private array $lessons = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
        $this->unenrolledStudent = User::factory()->student()->create();

        // Build a dedicated 5-video course for strict sequential drip-lock testing
        $course = new Course();
        $course->title = 'Five Video Sequential Course';
        $course->slug = 'five-video-sequential-course';
        $course->description = 'Course with 5 sequential video lessons.';
        $course->price = 1000;
        $course->is_published = true;
        $course->created_by = $this->sa1->id;
        $course->save();
        $this->course = $course;

        $mp4Bytes = base64_decode('AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQ==').str_repeat("\x00", 128);

        for ($i = 1; $i <= 5; $i++) {
            $path = "courses/{$course->id}/videos/lesson-{$i}.mp4";
            Storage::disk('local')->put($path, $mp4Bytes);

            $this->lessons[$i] = Lesson::query()->create([
                'course_id' => $course->id,
                'title' => "Video Lesson {$i}",
                'position' => $i,
                'video_path' => $path,
                'video_original_name' => "lesson-{$i}.mp4",
                'video_mime' => 'video/mp4',
                'video_size_bytes' => strlen($mp4Bytes),
                'duration_seconds' => 30, // 30-second video for deterministic heartbeat tests
                'is_published' => true,
            ]);
        }

        app(EnrollmentService::class)->create(
            student: $this->student,
            course: $this->course,
            durationValue: 3,
            durationUnit: Enrollment::UNIT_MONTHS,
            actor: $this->sa1
        );
    }

    private function watchLessonCompletely(Lesson $lesson): void
    {
        $base = CarbonImmutable::now();

        // Simulate realistic 10-second heartbeats across a 30-second video
        $this->travelTo($base);
        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$this->course, $lesson]), [
                'current_time' => 10,
                'duration' => 30,
                'delta_seconds' => 10,
                'ended' => false,
            ])
            ->assertOk()
            ->assertJson(['completed' => false, 'skipped_blocked' => false]);

        $this->travelTo($base->addSeconds(10));
        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$this->course, $lesson]), [
                'current_time' => 20,
                'duration' => 30,
                'delta_seconds' => 10,
                'ended' => false,
            ])
            ->assertOk()
            ->assertJson(['completed' => false]);

        $this->travelTo($base->addSeconds(20));
        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$this->course, $lesson]), [
                'current_time' => 30,
                'duration' => 30,
                'delta_seconds' => 10,
                'ended' => true,
            ])
            ->assertOk()
            ->assertJson(['completed' => true, 'just_completed' => true]);
    }

    public function test_strict_sequential_locking_across_five_videos_on_pages_and_stream(): void
    {
        // Initially: ONLY Lesson 1 is open; Lessons 2..5 return 403 on both page and direct video stream URL
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lessons[1]]))
            ->assertOk();

        $this->actingAs($this->student)
            ->withHeaders(['Range' => 'bytes=0-31'])
            ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[1]]))
            ->assertStatus(206)
            ->assertHeader('Content-Length', '32');

        $this->flushHeaders();

        for ($i = 2; $i <= 5; $i++) {
            $this->actingAs($this->student)
                ->get(route('student.courses.learn', [$this->course, $this->lessons[$i]]))
                ->assertForbidden();

            $this->actingAs($this->student)
                ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[$i]]))
                ->assertForbidden();
        }

        // Complete Videos 1 -> 4 one by one and confirm Video N+1 unlocks ONLY when Video N hits 100%
        for ($i = 1; $i <= 4; $i++) {
            $this->watchLessonCompletely($this->lessons[$i]);

            $this->actingAs($this->student)
                ->get(route('student.courses.learn', [$this->course, $this->lessons[$i + 1]]))
                ->assertOk();

            $this->actingAs($this->student)
                ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[$i + 1]]))
                ->assertOk();

            if ($i + 2 <= 5) {
                $this->actingAs($this->student)
                    ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[$i + 2]]))
                    ->assertForbidden();
            }
        }
    }

    public function test_anti_skip_blocks_instant_completion_cheat(): void
    {
        // Student tries to spoof `ended = true` immediately on a 30-second video without watching
        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$this->course, $this->lessons[1]]), [
                'current_time' => 30,
                'duration' => 30,
                'delta_seconds' => 30,
                'ended' => true,
            ])
            ->assertStatus(422)
            ->assertJson([
                'completed' => false,
                'skipped_blocked' => true,
            ]);

        $progress = LessonProgress::query()
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $this->lessons[1]->id)
            ->firstOrFail();

        $this->assertNull($progress->completed_at);

        // Lesson 2 MUST remain locked (403)
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lessons[2]]))
            ->assertForbidden();
    }

    public function test_direct_video_stream_denied_without_enrollment_or_after_expiry_and_progress_persists_on_renewal(): void
    {
        // Unenrolled student denied
        $this->actingAs($this->unenrolledStudent)
            ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[1]]))
            ->assertForbidden();

        // Complete Lesson 1 while enrolled
        $this->watchLessonCompletely($this->lessons[1]);

        // Expire enrollment mid-session
        $enrollment = Enrollment::query()->where('user_id', $this->student->id)->firstOrFail();
        $enrollment->expires_at = now()->subMinute();
        $enrollment->save();

        // Stream returns 403 and AJAX progress returns 403 with expired=true & redirect URL
        $this->actingAs($this->student)
            ->get(route('student.courses.lessons.stream', [$this->course, $this->lessons[1]]))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$this->course, $this->lessons[2]]), [
                'current_time' => 5,
                'duration' => 30,
                'delta_seconds' => 5,
                'ended' => false,
            ])
            ->assertForbidden()
            ->assertJson([
                'expired' => true,
                'redirect' => route('student.courses.subscribe', $this->course),
            ]);

        // Renew enrollment -> previous progress is preserved and Lesson 2 is still unlocked!
        app(EnrollmentService::class)->extend($enrollment, 1, Enrollment::UNIT_MONTHS, $this->sa1);

        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$this->course, $this->lessons[2]]))
            ->assertOk();
    }
}
