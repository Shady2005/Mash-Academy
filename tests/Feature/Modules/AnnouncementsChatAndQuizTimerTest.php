<?php

namespace Tests\Feature\Modules;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementsChatAndQuizTimerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin1;
    private User $superAdmin2;
    private User $teacher;
    private User $support;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->superAdmin1 = User::where('role', UserRole::SuperAdmin1)->firstOrFail();
        $this->superAdmin2 = User::where('role', UserRole::SuperAdmin2)->firstOrFail();
        $this->teacher = User::where('role', UserRole::Teacher)->firstOrFail();
        $this->support = User::where('role', UserRole::Support)->firstOrFail();
        $this->student = User::factory()->student()->create(['name' => 'Salma Student']);
    }

    private function createCourse(User $creator, string $title, string $slug): Course
    {
        $course = new Course([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Course overview',
            'price' => 1000,
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

    // =========================================================================
    // FEATURE 1: ANNOUNCEMENTS SYSTEM (GLOBAL & IN-COURSE + ROLE PERMISSIONS)
    // =========================================================================

    public function test_announcements_enforce_role_permissions_and_display_globally_and_in_course(): void
    {
        $adminCourse = $this->createCourse($this->superAdmin1, 'Admin Course', 'admin-course');
        $teacherCourse = $this->createCourse($this->teacher, 'Teacher Course', 'teacher-course');

        Lesson::create([
            'course_id' => $teacherCourse->id,
            'title' => 'Intro Lesson',
            'position' => 1,
            'duration_seconds' => 60,
            'is_published' => true,
        ]);

        $this->enrollStudent($this->student, $teacherCourse);

        // Support and Student cannot access Admin Announcements
        $this->actingAs($this->support)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($this->student)->get(route('admin.announcements.index'))->assertForbidden();

        // Super Admin creates a Global Site-Wide Important Announcement
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.announcements.store'), [
                'course_id' => '',
                'title_en' => 'Ramadan Live Schedule',
                'title_ar' => 'مواعيد شهر رمضان',
                'body_en' => 'New live Q&A sessions every Thursday at 9 PM.',
                'body_ar' => 'جلسات أسئلة مباشرة كل خميس الساعة 9 مساءً.',
                'priority' => 'important',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $globalAnn = Announcement::whereNull('course_id')->firstOrFail();

        // Global announcement renders on Home Page AND Student Dashboard
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ramadan Live Schedule');

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Ramadan Live Schedule');

        // Teacher CANNOT create Global announcements or announcements for Super Admin's course
        $this->actingAs($this->teacher)
            ->post(route('admin.announcements.store'), [
                'course_id' => '',
                'title_en' => 'Illegal Global Post',
                'body_en' => 'Should be forbidden.',
                'priority' => 'normal',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->post(route('admin.announcements.store'), [
                'course_id' => $adminCourse->id,
                'title_en' => 'Illegal Admin Course Post',
                'body_en' => 'Should be forbidden.',
                'priority' => 'normal',
                'is_active' => '1',
            ])
            ->assertForbidden();

        // Teacher CANNOT toggle or delete Super Admin's global announcement
        $this->actingAs($this->teacher)->patch(route('admin.announcements.toggle', $globalAnn))->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('admin.announcements.destroy', $globalAnn))->assertForbidden();

        // Teacher CAN create a Course-Specific Announcement for their own course
        $this->actingAs($this->teacher)
            ->post(route('admin.announcements.store'), [
                'course_id' => $teacherCourse->id,
                'title_en' => 'Project Deadline Extended',
                'body_en' => 'You have 5 extra days to submit Assignment 1.',
                'priority' => 'important',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $courseAnn = Announcement::where('course_id', $teacherCourse->id)->firstOrFail();

        // Enrolled student sees the in-course announcement with Unread ("1 New") badge
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', $teacherCourse))
            ->assertOk()
            ->assertSee('Project Deadline Extended')
            ->assertSee('1 New');

        // Student marks course announcements as read -> "1 New" clears
        $this->actingAs($this->student)
            ->post(route('student.courses.announcements.read', $teacherCourse))
            ->assertRedirect();

        $this->actingAs($this->student)
            ->get(route('student.courses.learn', $teacherCourse))
            ->assertOk()
            ->assertSee('Project Deadline Extended')
            ->assertDontSee('1 New');

        // Teacher toggles their own announcement OFF -> hidden from Student Course view
        $this->actingAs($this->teacher)
            ->patch(route('admin.announcements.toggle', $courseAnn))
            ->assertRedirect();

        $this->actingAs($this->student)
            ->get(route('student.courses.learn', $teacherCourse))
            ->assertOk()
            ->assertDontSee('Project Deadline Extended');
    }

    // =========================================================================
    // FEATURE 2: CUSTOMER SUPPORT LIVE CHAT (WIDGET + ADMIN SUPPORT INBOX)
    // =========================================================================

    public function test_support_live_chat_supports_guests_students_and_staff_inbox_with_role_guards(): void
    {
        // 1. Floating Support Chat widget renders on Public Home
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('ma-support-chat-widget')
            ->assertSee('Support / Need Help?');

        // 2. Guest visitor starts a chat conversation via AJAX (before logging in)
        $guestStart = $this->postJson(route('support-chat.start'), [
            'guest_name' => 'Karim Guest',
            'guest_email' => 'karim.guest@example.com',
            'message' => 'How do I subscribe using InstaPay?',
        ])->assertCreated()
            ->assertJson(['ok' => true]);

        $guestConvId = $guestStart->json('conversation.id');
        $guestConv = SupportConversation::findOrFail($guestConvId);
        $this->assertNull($guestConv->user_id);
        $this->assertSame('Karim Guest', $guestConv->guest_name);

        // 3. Logged-in Student sees widget & starts a chat conversation (auto-linked to student account)
        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('ma-support-chat-widget');

        $studentStart = $this->actingAs($this->student)
            ->postJson(route('support-chat.start'), [
                'message' => 'Hello support, I have a question about my enrollment.',
            ])
            ->assertCreated()
            ->assertJson(['ok' => true]);

        $studentConv = SupportConversation::findOrFail($studentStart->json('conversation.id'));
        $this->assertSame($this->student->id, $studentConv->user_id);

        // 4. Teacher is blocked from Admin Support Chat; Support & Super Admins have access
        $this->actingAs($this->teacher)->get(route('admin.support-chat.index'))->assertForbidden();

        $this->actingAs($this->support)
            ->get(route('admin.support-chat.index', ['conversation' => $studentConv->id]))
            ->assertOk()
            ->assertSee('Salma Student')
            ->assertSee('Hello support, I have a question about my enrollment.');

        // 5. Customer Support replies via AJAX -> Student polls and receives reply
        $this->actingAs($this->support)
            ->postJson(route('admin.support-chat.reply', $studentConv), [
                'message' => 'Hi Salma! Your enrollment is active and ready.',
            ])
            ->assertCreated()
            ->assertJson([
                'ok' => true,
                'message' => [
                    'is_staff' => true,
                    'message' => 'Hi Salma! Your enrollment is active and ready.',
                ],
            ]);

        $this->actingAs($this->student)
            ->getJson(route('support-chat.state', ['mark_read' => 1]))
            ->assertOk()
            ->assertJsonPath('has_conversation', true)
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.message', 'Hi Salma! Your enrollment is active and ready.');

        // 6. Super Admin marks conversation as Resolved, then Reopens it
        $this->actingAs($this->superAdmin1)
            ->patch(route('admin.support-chat.status', $studentConv), ['status' => 'resolved'])
            ->assertRedirect();
        $this->assertSame(SupportConversation::STATUS_RESOLVED, $studentConv->fresh()->status);

        $this->actingAs($this->support)
            ->patch(route('admin.support-chat.status', $studentConv), ['status' => 'open'])
            ->assertRedirect();
        $this->assertSame(SupportConversation::STATUS_OPEN, $studentConv->fresh()->status);
    }

    // =========================================================================
    // FEATURE 3: OPTIONAL QUIZ TIMER + PERSISTENT SERVER COUNTDOWN + AUTO-SUBMIT
    // =========================================================================

    public function test_timed_quiz_persists_started_at_across_refreshes_and_auto_submits_past_deadline(): void
    {
        $course = $this->createCourse($this->superAdmin1, 'Timed Exam Course', 'timed-exam-course');
        $this->enrollStudent($this->student, $course);

        // 1. Validation requires duration_minutes when has_timer is enabled
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.courses.quizzes.store', $course), [
                'title' => 'Invalid Timed Quiz',
                'pass_mark' => 60,
                'has_timer' => '1',
                'duration_minutes' => '',
            ])
            ->assertSessionHasErrors('duration_minutes');

        // 2. Create a valid 10-minute Timed Quiz
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.courses.quizzes.store', $course), [
                'title' => 'Midterm Timed Quiz',
                'pass_mark' => 50,
                'has_timer' => '1',
                'duration_minutes' => 10,
            ])
            ->assertRedirect(route('admin.courses.quizzes.index', $course));

        $quiz = Quiz::where('course_id', $course->id)->firstOrFail();
        $this->assertTrue($quiz->isTimed());
        $this->assertSame(10, $quiz->duration_minutes);

        // Add 2 questions (1 point each)
        $q1 = $quiz->questions()->create(['question_text' => '2 + 2 = ?', 'type' => 'multiple_choice', 'position' => 1, 'points' => 1]);
        $q1Right = $q1->options()->create(['option_text' => '4', 'is_correct' => true]);
        $q1->options()->create(['option_text' => '5', 'is_correct' => false]);

        $q2 = $quiz->questions()->create(['question_text' => '3 + 3 = ?', 'type' => 'multiple_choice', 'position' => 2, 'points' => 1]);
        $q2->options()->create(['option_text' => '6', 'is_correct' => true]);
        $q2->options()->create(['option_text' => '7', 'is_correct' => false]);

        // 3. Student opens timed quiz -> creates in-progress attempt with started_at = now() & shows sticky countdown
        $this->actingAs($this->student)
            ->get(route('student.courses.quizzes.show', [$course, $quiz]))
            ->assertOk()
            ->assertSee('ma-quiz-countdown-bar')
            ->assertSee('Time Remaining');

        $attempt = QuizAttempt::where('quiz_id', $quiz->id)->where('user_id', $this->student->id)->firstOrFail();
        $this->assertNotNull($attempt->started_at);
        $this->assertNull($attempt->submitted_at);
        $originalStartedAt = $attempt->started_at->copy();

        // 4. Advance 4 minutes & refresh page -> server DOES NOT reset started_at (~360s left)
        $this->travel(4)->minutes();

        $refreshResponse = $this->actingAs($this->student)
            ->get(route('student.courses.quizzes.show', [$course, $quiz]))
            ->assertOk()
            ->assertSee('ma-quiz-countdown-bar');

        $this->assertSame(1, QuizAttempt::where('quiz_id', $quiz->id)->count());
        $this->assertTrue($attempt->fresh()->started_at->equalTo($originalStartedAt));
        $remainingAttr = $refreshResponse->viewData('remainingSeconds');
        $this->assertGreaterThanOrEqual(355, $remainingAttr);
        $this->assertLessThanOrEqual(360, $remainingAttr);

        // 5. Advance 7 more minutes (total 11 min > 10 min limit) -> late submission auto-graded with auto_submitted = true!
        $this->travel(7)->minutes();

        $this->actingAs($this->student)
            ->post(route('student.courses.quizzes.submit', [$course, $quiz]), [
                // Student only answered Q1 before time ran out; Q2 left unanswered
                'answers' => [
                    $q1->id => $q1Right->id,
                ],
            ])
            ->assertRedirect(route('student.courses.quizzes.show', [$course, $quiz]));

        $finalized = $attempt->fresh();
        $this->assertTrue($finalized->auto_submitted);
        $this->assertNotNull($finalized->submitted_at);
        $this->assertSame(1, $finalized->score);
        $this->assertSame(2, $finalized->max_score);
        $this->assertTrue($finalized->passed); // 50% >= 50% pass mark
        // submitted_at is capped at the exact server deadline (started_at + 10 min)
        $this->assertTrue($finalized->submitted_at->equalTo($originalStartedAt->copy()->addMinutes(10)));

        // Re-opening quiz shows the Auto-Submitted badge and "Start Timed Quiz" retake button
        $this->actingAs($this->student)
            ->get(route('student.courses.quizzes.show', [$course, $quiz]))
            ->assertOk()
            ->assertSee('Auto-Submitted')
            ->assertSee('Start Timed Quiz (10 min)');
    }
}
