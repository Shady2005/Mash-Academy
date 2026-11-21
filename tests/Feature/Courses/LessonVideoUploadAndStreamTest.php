<?php

namespace Tests\Feature\Courses;

use App\Enums\UserRole;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonVideoUploadAndStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $teacher;

    private User $support;

    private User $student;

    private Course $teacherCourse;

    private Course $saCourse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
        $this->teacherCourse = Course::query()->where('created_by', $this->teacher->id)->firstOrFail();
        $this->saCourse = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();
    }

    private function fakeMp4Content(int $paddingBytes = 64): string
    {
        // Standard ISO Base Media MP4 header (ftypisom + avc1 + mp41) recognized by finfo as video/mp4
        $header = base64_decode('AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQ==');

        return $header.str_repeat("\x00", $paddingBytes);
    }

    public function test_teacher_can_upload_mp4_video_and_stream_with_range_support(): void
    {
        Storage::fake('local');

        $videoFile = UploadedFile::fake()->createWithContent('intro.mp4', $this->fakeMp4Content(100));

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.lessons.store', $this->teacherCourse), [
                'title' => 'Uploaded Video Lesson',
                'description' => 'Private MP4 recording.',
                'duration_seconds' => 300,
                'is_published' => true,
                'video' => $videoFile,
            ])
            ->assertRedirect(route('admin.courses.lessons.index', $this->teacherCourse));

        $lesson = Lesson::query()->where('title', 'Uploaded Video Lesson')->firstOrFail();
        $this->assertNotNull($lesson->video_path);
        Storage::disk('local')->assertExists($lesson->video_path);

        // Full preview stream (200)
        $this->actingAs($this->teacher)
            ->get(route('admin.courses.lessons.stream', [$this->teacherCourse, $lesson]))
            ->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes');

        // HTTP Range request (206 Partial Content)
        $this->actingAs($this->sa1)
            ->withHeaders(['Range' => 'bytes=0-15'])
            ->get(route('admin.courses.lessons.stream', [$this->teacherCourse, $lesson]))
            ->assertStatus(206)
            ->assertHeader('Content-Length', '16');

        // Support and Student must be denied (403)
        $this->actingAs($this->support)
            ->get(route('admin.courses.lessons.stream', [$this->teacherCourse, $lesson]))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->get(route('admin.courses.lessons.stream', [$this->teacherCourse, $lesson]))
            ->assertForbidden();
    }

    public function test_non_video_and_oversized_video_uploads_are_rejected(): void
    {
        Storage::fake('local');

        // Reject non-video file
        $badFile = UploadedFile::fake()->createWithContent('malicious.php', '<?php echo "bad";');

        $this->actingAs($this->teacher)
            ->from(route('admin.courses.lessons.index', $this->teacherCourse))
            ->post(route('admin.courses.lessons.store', $this->teacherCourse), [
                'title' => 'Bad Upload',
                'video' => $badFile,
            ])
            ->assertRedirect(route('admin.courses.lessons.index', $this->teacherCourse))
            ->assertSessionHasErrors('video');

        // Reject oversized video using app max config (set to 1 KB for test)
        config()->set('mash.max_video_upload_kb', 1);
        $oversizedVideo = UploadedFile::fake()->createWithContent('big.mp4', $this->fakeMp4Content(4096));

        $this->actingAs($this->teacher)
            ->from(route('admin.courses.lessons.index', $this->teacherCourse))
            ->post(route('admin.courses.lessons.store', $this->teacherCourse), [
                'title' => 'Oversized Upload',
                'video' => $oversizedVideo,
            ])
            ->assertRedirect(route('admin.courses.lessons.index', $this->teacherCourse))
            ->assertSessionHasErrors('video');
    }

    public function test_lessons_can_be_reordered_up_and_down(): void
    {
        $lessons = $this->teacherCourse->lessons()->get();
        $first = $lessons[0];
        $second = $lessons[1];

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.lessons.move', [$this->teacherCourse, $second]), [
                'direction' => 'up',
            ])
            ->assertRedirect(route('admin.courses.lessons.index', $this->teacherCourse));

        $first->refresh();
        $second->refresh();

        $this->assertSame(1, $second->position);
        $this->assertSame(2, $first->position);
    }

    public function test_teacher_cannot_upload_or_stream_lessons_in_super_admin_course(): void
    {
        $saLesson = $this->saCourse->lessons()->firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('admin.courses.lessons.store', $this->saCourse), [
                'title' => 'Unauthorized Lesson',
            ])
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->get(route('admin.courses.lessons.stream', [$this->saCourse, $saLesson]))
            ->assertForbidden();
    }

    public function test_max_limit_is_2gb_and_mov_m4v_octet_stream_videos_are_accepted_while_ajax_errors_return_json(): void
    {
        Storage::fake('local');

        $this->assertSame(2097152, LessonRequest::maxVideoKilobytes());

        // Binary payload detected by finfo as application/octet-stream with valid .mov / .m4v / .mp4 extensions
        $octetPayload = random_bytes(128);

        foreach (['lecture.mov' => 'video/quicktime', 'lesson.m4v' => 'video/mp4', 'recording.mp4' => 'video/mp4'] as $filename => $expectedMime) {
            $file = UploadedFile::fake()->createWithContent($filename, $octetPayload);

            $this->actingAs($this->teacher)
                ->postJson(route('admin.courses.lessons.store', $this->teacherCourse), [
                    'title' => "Video {$filename}",
                    'is_published' => true,
                    'video' => $file,
                ])
                ->assertStatus(201)
                ->assertJsonStructure(['message', 'redirect']);

            $lesson = Lesson::query()->where('title', "Video {$filename}")->firstOrFail();
            $this->assertSame($expectedMime, $lesson->video_mime);
            Storage::disk('local')->assertExists($lesson->video_path);
        }

        // Binary application/octet-stream with disallowed extension must still be rejected with JSON 422
        $badBinary = UploadedFile::fake()->createWithContent('payload.exe', $octetPayload);

        $this->actingAs($this->teacher)
            ->postJson(route('admin.courses.lessons.store', $this->teacherCourse), [
                'title' => 'Disallowed Binary',
                'video' => $badBinary,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('video');
    }
}
