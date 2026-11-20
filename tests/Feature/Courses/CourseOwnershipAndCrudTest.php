<?php

namespace Tests\Feature\Courses;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\FinalProject;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseOwnershipAndCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $sa2;

    private User $teacher;

    private User $support;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
    }

    public function test_teacher_can_create_edit_and_delete_own_course(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('admin.courses.store'), [
            'title' => 'Teacher Owned Course',
            'title_ar' => 'دورة المعلم',
            'description' => 'Detailed curriculum created by Teacher Admin.',
            'price' => 950,
            'currency' => 'EGP',
            'is_published' => true,
            'is_featured' => false,
        ]);

        $course = Course::query()->where('title', 'Teacher Owned Course')->firstOrFail();
        $response->assertRedirect(route('admin.courses.lessons.index', $course));
        $this->assertSame($this->teacher->id, $course->created_by);

        $this->actingAs($this->teacher)
            ->put(route('admin.courses.update', $course), [
                'title' => 'Teacher Owned Course Updated',
                'description' => 'Updated detailed description.',
                'price' => 1100,
                'currency' => 'EGP',
                'is_published' => true,
                'is_featured' => false,
            ])
            ->assertRedirect(route('admin.courses.edit', $course));

        $this->actingAs($this->teacher)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
    }

    public function test_teacher_cannot_edit_manage_or_delete_super_admin_course(): void
    {
        $saCourse = Course::query()->where('created_by', $this->sa1->id)->firstOrFail();

        $this->actingAs($this->teacher)->get(route('admin.courses.edit', $saCourse))->assertForbidden();
        $this->actingAs($this->teacher)->put(route('admin.courses.update', $saCourse), [
            'title' => 'Hijacked',
            'description' => 'Attempting hijack of Super Admin course.',
            'price' => 10,
        ])->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('admin.courses.destroy', $saCourse))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.courses.lessons.index', $saCourse))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.courses.quizzes.index', $saCourse))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.courses.assignments.index', $saCourse))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('admin.courses.final-project.edit', $saCourse))->assertForbidden();

        $this->assertDatabaseHas('courses', ['id' => $saCourse->id, 'deleted_at' => null]);
    }

    public function test_super_admins_can_edit_and_delete_teacher_course(): void
    {
        $teacherCourse = Course::query()->where('created_by', $this->teacher->id)->firstOrFail();

        $this->actingAs($this->sa2)->get(route('admin.courses.edit', $teacherCourse))->assertOk();
        $this->actingAs($this->sa2)->delete(route('admin.courses.destroy', $teacherCourse))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertSoftDeleted('courses', ['id' => $teacherCourse->id]);
    }

    public function test_support_and_student_cannot_access_course_management(): void
    {
        $course = Course::query()->firstOrFail();

        foreach ([$this->support, $this->student] as $actor) {
            $this->actingAs($actor)->get(route('admin.courses.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.courses.create'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.courses.store'), [
                'title' => 'Unauthorized',
                'description' => 'Unauthorized course attempt.',
                'price' => 500,
            ])->assertForbidden();
            $this->actingAs($actor)->delete(route('admin.courses.destroy', $course))->assertForbidden();
        }
    }

    public function test_max_three_featured_courses_is_enforced_server_side(): void
    {
        // DemoCoursesSeeder already seeded 3 featured courses
        $this->assertSame(3, Course::query()->where('is_featured', true)->count());

        $this->actingAs($this->sa1)
            ->from(route('admin.courses.create'))
            ->post(route('admin.courses.store'), [
                'title' => 'Fourth Featured Attempt',
                'description' => 'Attempting to feature a fourth course on the home page.',
                'price' => 1200,
                'is_published' => true,
                'is_featured' => true,
            ])
            ->assertRedirect(route('admin.courses.create'))
            ->assertSessionHasErrors('is_featured');

        $this->assertSame(3, Course::query()->where('is_featured', true)->count());
    }

    public function test_only_published_courses_appear_publicly_and_student_browse_shows_locked(): void
    {
        $draftCourse = new Course();
        $draftCourse->title = 'Hidden Draft Course';
        $draftCourse->slug = Course::generateUniqueSlug('Hidden Draft Course');
        $draftCourse->description = 'Should not appear publicly until published.';
        $draftCourse->price = 800;
        $draftCourse->is_published = false;
        $draftCourse->created_by = $this->sa1->id;
        $draftCourse->save();

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertDontSee('Hidden Draft Course');

        $this->get(route('courses.show', $draftCourse))->assertNotFound();

        $publishedCourse = Course::query()->published()->firstOrFail();
        $this->get(route('courses.show', $publishedCourse))
            ->assertOk()
            ->assertSee($publishedCourse->title);

        $this->actingAs($this->student)
            ->get(route('student.courses.index'))
            ->assertOk()
            ->assertSee($publishedCourse->title)
            ->assertSee('Locked')
            ->assertDontSee('Hidden Draft Course');
    }

    public function test_deleting_course_cleans_up_children_and_stored_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $course = new Course();
        $course->title = 'Cleanup Target Course';
        $course->slug = Course::generateUniqueSlug('Cleanup Target Course');
        $course->description = 'Course with video, thumbnail, quiz, assignment, and final project.';
        $course->price = 1000;
        $course->is_published = true;
        $course->created_by = $this->sa1->id;
        $course->thumbnail_path = 'courses/thumbnails/test-thumb.png';
        $course->save();

        Storage::disk('public')->put('courses/thumbnails/test-thumb.png', 'png-data');
        Storage::disk('local')->put("courses/{$course->id}/videos/lesson1.mp4", 'video-bytes');
        Storage::disk('local')->put("courses/{$course->id}/final-project/brief.pdf", 'pdf-bytes');

        $lesson = Lesson::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'position' => 1,
            'video_path' => "courses/{$course->id}/videos/lesson1.mp4",
        ]);

        $quiz = new Quiz();
        $quiz->course_id = $course->id;
        $quiz->title = 'Quiz 1';
        $quiz->pass_mark = 70;
        $quiz->created_by = $this->sa1->id;
        $quiz->save();

        $assignment = new Assignment();
        $assignment->course_id = $course->id;
        $assignment->title = 'Assignment 1';
        $assignment->submission_type = Assignment::TYPE_FILE;
        $assignment->instructions = 'Upload your file.';
        $assignment->created_by = $this->sa1->id;
        $assignment->save();

        $fp = new FinalProject();
        $fp->course_id = $course->id;
        $fp->title = 'Capstone';
        $fp->instructions = 'Complete the capstone project thoroughly.';
        $fp->attachment_path = "courses/{$course->id}/final-project/brief.pdf";
        $fp->required_completion_percentage = 95;
        $fp->created_by = $this->sa1->id;
        $fp->save();

        $this->actingAs($this->sa1)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect(route('admin.courses.index'));

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
        $this->assertDatabaseMissing('final_projects', ['id' => $fp->id]);

        Storage::disk('public')->assertMissing('courses/thumbnails/test-thumb.png');
        Storage::disk('local')->assertMissing("courses/{$course->id}/videos/lesson1.mp4");
        Storage::disk('local')->assertMissing("courses/{$course->id}/final-project/brief.pdf");
    }
}
