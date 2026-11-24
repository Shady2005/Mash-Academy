<?php

namespace Tests\Feature\Modules;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Certificate;
use App\Models\Consultation;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\Enrollment;
use App\Models\FinalProject;
use App\Models\FinalProjectSubmission;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\PaymentRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngagementAndStreamingFeaturesTest extends TestCase
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
        $this->student = User::factory()->student()->create([
            'name' => 'Student Tester',
            'phone' => '01012345678',
        ]);
    }

    private function fakePng(string $name = 'proof.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createCourse(User $creator, array $attributes): Course
    {
        $course = new Course(array_merge([
            'currency' => 'EGP',
            'is_published' => true,
        ], $attributes));
        $course->created_by = $creator->id;
        $course->save();

        return $course;
    }

    private function createEnrollment(User $student, Course $course, int $daysRemaining = 60): Enrollment
    {
        $enrollment = new Enrollment([
            'duration_value' => 3,
            'duration_unit' => 'months',
            'starts_at' => now()->subDays(5),
            'expires_at' => now()->addDays($daysRemaining),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $enrollment->user_id = $student->id;
        $enrollment->course_id = $course->id;
        $enrollment->created_by = $this->superAdmin1->id;
        $enrollment->save();

        return $enrollment;
    }

    private function createFinalProject(Course $course, User $creator): FinalProject
    {
        $fp = new FinalProject([
            'title' => 'Capstone App',
            'instructions' => 'Upload zip.',
            'required_completion_percentage' => 95,
            'is_published' => true,
        ]);
        $fp->course_id = $course->id;
        $fp->created_by = $creator->id;
        $fp->save();

        return $fp;
    }

    // =========================================================================
    // FEATURE 1: OPTIONAL FREE DEMO / PREVIEW VIDEOS + DASHBOARD TOGGLES
    // =========================================================================

    public function test_demo_videos_show_on_course_and_home_when_enabled_and_hide_when_toggled_off(): void
    {
        $course = $this->createCourse($this->superAdmin1, [
            'title' => 'Laravel Mastery',
            'slug' => 'laravel-mastery',
            'description' => 'Build production apps.',
            'price' => 1500,
            'has_demo_video' => true,
            'demo_video_type' => 'youtube',
            'demo_video_value' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        // Enabled by default
        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Watch Free Demo')
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Free Demo Videos')
            ->assertSee('Laravel Mastery');

        // Super Admin toggles enable_demo_videos OFF from Settings
        $this->actingAs($this->superAdmin1)
            ->put(route('admin.settings.update'), [
                'academy_name' => 'Mash Academy',
                'toggles_present' => '1',
                'enable_demo_videos' => '0',
                'enable_certificate_verification' => '1',
                'enable_course_qa' => '1',
                'enable_course_reviews' => '1',
                'enable_consultations' => '1',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertFalse(AcademySetting::isFeatureEnabled('enable_demo_videos'));

        // Hidden on both public Course Details and Home
        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertDontSee('Watch Free Demo')
            ->assertDontSee('courseDemoVideoModal');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Free Demo Videos');
    }

    // =========================================================================
    // FEATURE 2: IN-APP NOTIFICATION BELL + 3-DAY EXPIRY + WHATSAPP BUTTONS
    // =========================================================================

    public function test_in_app_notifications_fire_across_payment_project_expiry_and_mark_read(): void
    {
        Storage::fake('local');

        $course = $this->createCourse($this->teacher, [
            'title' => 'Full-Stack PHP',
            'slug' => 'full-stack-php',
            'description' => 'Complete course',
            'price' => 2000,
        ]);

        // 1. Student submits payment -> notifies Super Admins 1 & 2
        $this->actingAs($this->student)
            ->post(route('student.courses.subscribe.store', $course), [
                'phone' => '01099887766',
                'screenshot' => $this->fakePng('transfer.png'),
            ])
            ->assertRedirect(route('student.payments.index'));

        $payment = PaymentRequest::firstOrFail();
        $this->assertGreaterThanOrEqual(1, $this->superAdmin1->fresh()->unreadNotifications()->count());
        $this->assertGreaterThanOrEqual(1, $this->superAdmin2->fresh()->unreadNotifications()->count());

        // Super Admin marks all notifications read
        $this->actingAs($this->superAdmin1)
            ->post(route('notifications.read-all'))
            ->assertRedirect();
        $this->assertSame(0, $this->superAdmin1->fresh()->unreadNotifications()->count());

        // 2. Super Admin approves payment -> notifies Student
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.payments.approve', $payment), ['admin_note' => 'Verified'])
            ->assertRedirect();

        $studentNotif = $this->student->fresh()->unreadNotifications()->first();
        $this->assertNotNull($studentNotif);
        $this->assertSame('Payment Approved', $studentNotif->data['title']);

        // Student marks single notification as read & follows target URL
        $this->actingAs($this->student)
            ->post(route('notifications.read', $studentNotif->id))
            ->assertRedirect();
        $this->assertSame(0, $this->student->fresh()->unreadNotifications()->count());

        // 3. Final Project Submitted -> notifies Super Admins + Course Teacher; Reviewed -> notifies Student
        $this->createFinalProject($course, $this->teacher);

        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Lesson 1',
            'position' => 1,
            'duration_seconds' => 60,
            'is_published' => true,
        ]);

        // Expiring within 2 days (inside 3-day reminder window)
        $this->createEnrollment($this->student, $course, 2);

        LessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 60,
            'last_position_seconds' => 60,
            'max_reached_seconds' => 60,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.courses.final-project.submit', $course), [
                'file' => UploadedFile::fake()->create('project.zip', 100, 'application/zip'),
                'notes' => 'Ready for review',
            ])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(1, $this->teacher->fresh()->unreadNotifications()->count());
        $this->assertGreaterThanOrEqual(1, $this->superAdmin1->fresh()->unreadNotifications()->count());

        $fpSub = FinalProjectSubmission::firstOrFail();
        $this->actingAs($this->teacher)
            ->put(route('admin.courses.final-project-submissions.review', [$course, $fpSub]), [
                'grade' => 98,
                'feedback' => 'Excellent capstone!',
            ])
            ->assertRedirect();

        $this->assertTrue(
            $this->student->fresh()->unreadNotifications()->get()->contains(fn ($n) => $n->data['title'] === 'Final Project Reviewed')
        );

        // 4. Scheduled 3-day expiring enrollment notification command (fires once, never duplicates)
        Artisan::call('enrollments:notify-expiring');

        $expiryNotifs = $this->student->fresh()->unreadNotifications()->get()
            ->filter(fn ($n) => $n->data['title'] === 'Course Access Expiring Soon');
        $this->assertCount(1, $expiryNotifs);

        // Running command again must NOT double-notify
        Artisan::call('enrollments:notify-expiring');
        $expiryNotifsAfter = $this->student->fresh()->unreadNotifications()->get()
            ->filter(fn ($n) => $n->data['title'] === 'Course Access Expiring Soon');
        $this->assertCount(1, $expiryNotifsAfter);
    }

    public function test_whatsapp_one_click_buttons_render_in_enrollments_payments_and_support(): void
    {
        $course = $this->createCourse($this->superAdmin1, [
            'title' => 'UI Engineering',
            'slug' => 'ui-engineering',
            'description' => 'Design systems',
            'price' => 900,
        ]);

        $this->createEnrollment($this->student, $course, 90);

        $payment = new PaymentRequest([
            'original_price' => 900,
            'discount_amount' => 0,
            'final_price' => 900,
            'currency' => 'EGP',
            'name' => $this->student->name,
            'email' => $this->student->email,
            'phone' => '01012345678',
            'screenshot_path' => 'payment-proofs/sample.png',
            'status' => PaymentRequest::STATUS_PENDING,
        ]);
        $payment->user_id = $this->student->id;
        $payment->course_id = $course->id;
        $payment->save();

        // Enrollments page
        $this->actingAs($this->superAdmin1)
            ->get(route('admin.enrollments.index'))
            ->assertOk()
            ->assertSee('https://wa.me/201012345678?text=', false);

        // Payments Inbox
        $this->actingAs($this->superAdmin1)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('https://wa.me/201012345678?text=', false);

        // Support Students Directory & Detail (read-only)
        $this->actingAs($this->support)
            ->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('https://wa.me/201012345678?text=', false);

        $this->actingAs($this->support)
            ->get(route('admin.students.show', $this->student))
            ->assertOk()
            ->assertSee('https://wa.me/201012345678?text=', false);
    }

    // =========================================================================
    // FEATURE 3: PUBLIC CERTIFICATE VERIFICATION PAGE + TOGGLE
    // =========================================================================

    public function test_public_verify_certificate_route_works_when_enabled_and_returns_404_when_disabled(): void
    {
        $course = $this->createCourse($this->superAdmin1, [
            'title' => 'Cloud Architecture',
            'slug' => 'cloud-architecture',
            'description' => 'Cloud fundamentals',
            'price' => 1200,
        ]);

        $fp = $this->createFinalProject($course, $this->superAdmin1);
        $fpSub = new FinalProjectSubmission([
            'file_path' => 'final-projects/sample.zip',
            'file_original_name' => 'sample.zip',
            'status' => FinalProjectSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        $fpSub->final_project_id = $fp->id;
        $fpSub->user_id = $this->student->id;
        $fpSub->save();

        $cert = new Certificate([
            'certificate_code' => 'MASH-2026-VERIFY01',
            'issued_at' => now(),
        ]);
        $cert->user_id = $this->student->id;
        $cert->course_id = $course->id;
        $cert->final_project_submission_id = $fpSub->id;
        $cert->save();

        // Public /verify-certificate works without login
        $this->get('/verify-certificate?code='.$cert->certificate_code)
            ->assertOk()
            ->assertSee('MASH-2026-VERIFY01')
            ->assertSee($this->student->name)
            ->assertSee('Cloud Architecture');

        // Toggle OFF
        AcademySetting::setValue('enable_certificate_verification', '0');

        $this->get('/verify-certificate?code='.$cert->certificate_code)->assertNotFound();
        $this->get(route('home'))->assertOk()->assertDontSee('/verify-certificate');
    }

    // =========================================================================
    // FEATURE 4: COURSE Q&A, STUDENT STAR REVIEWS (>= 50%) & 1-ON-1 CONSULTATIONS
    // =========================================================================

    public function test_course_qa_and_star_reviews_enforce_progress_gate_and_toggles(): void
    {
        $course = $this->createCourse($this->teacher, [
            'title' => 'Backend Engineering',
            'slug' => 'backend-engineering',
            'description' => 'APIs and databases',
            'price' => 1000,
        ]);

        $l1 = Lesson::create(['course_id' => $course->id, 'title' => 'Lesson 1', 'position' => 1, 'duration_seconds' => 60, 'is_published' => true]);
        Lesson::create(['course_id' => $course->id, 'title' => 'Lesson 2', 'position' => 2, 'duration_seconds' => 60, 'is_published' => true]);

        $this->createEnrollment($this->student, $course, 60);

        // 1. Enrolled student posts a Q&A question
        $this->actingAs($this->student)
            ->post(route('student.courses.questions.store', $course), [
                'lesson_id' => $l1->id,
                'question' => 'How do I optimize database indexes for this query?',
            ])
            ->assertRedirect();

        $question = CourseQuestion::firstOrFail();

        // Teacher (owner) replies via Admin route -> resolves question & notifies student
        $this->actingAs($this->teacher)
            ->post(route('admin.courses.questions.reply', [$course, $question]), [
                'reply' => 'Use a composite index on (course_id, status).',
            ])
            ->assertRedirect();

        $this->assertTrue($question->fresh()->is_resolved);
        $this->assertTrue(
            $this->student->fresh()->unreadNotifications()->get()->contains(fn ($n) => $n->data['title'] === 'New Q&A Reply')
        );

        // 2. Review blocked below 50% completion
        $this->actingAs($this->student)
            ->post(route('student.courses.reviews.store', $course), [
                'rating' => 5,
                'comment' => 'Great course!',
            ])
            ->assertForbidden();

        // Complete 1 of 2 lessons => 50% progress -> review allowed!
        LessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $l1->id,
            'watched_seconds' => 60,
            'last_position_seconds' => 60,
            'max_reached_seconds' => 60,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post(route('student.courses.reviews.store', $course), [
                'rating' => 5,
                'comment' => 'Super clear explanations and real-world projects!',
            ])
            ->assertRedirect();

        // Review renders publicly on Course Details
        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Super clear explanations and real-world projects!')
            ->assertSee('5.0 / 5.0');

        // Toggle Q&A and Reviews OFF -> 404 on submission
        AcademySetting::setMany([
            'enable_course_qa' => '0',
            'enable_course_reviews' => '0',
        ]);

        $this->actingAs($this->student)
            ->post(route('student.courses.questions.store', $course), ['question' => 'Disabled question test'])
            ->assertNotFound();

        $this->actingAs($this->student)
            ->post(route('student.courses.reviews.store', $course), ['rating' => 4])
            ->assertNotFound();
    }

    public function test_consultations_support_free_and_paid_instapay_workflow_and_toggle(): void
    {
        Storage::fake('local');

        // 1. Free Consultation booking (default pricing mode is free)
        $this->actingAs($this->student)
            ->get(route('consultations.index'))
            ->assertOk()
            ->assertSee('Book a Consultation');

        $this->actingAs($this->student)
            ->post(route('consultations.store'), [
                'instructor_id' => $this->superAdmin1->id,
                'preferred_date' => now()->addDays(2)->toDateString(),
                'preferred_time' => '18:00',
                'topic' => 'Career Roadmap & Portfolio Review',
                'notes' => 'Looking for feedback on my Laravel apps.',
            ])
            ->assertRedirect(route('consultations.index'));

        $freeBooking = Consultation::firstOrFail();
        $this->assertSame(Consultation::PRICING_FREE, $freeBooking->pricing_mode);
        $this->assertSame(Consultation::STATUS_PENDING, $freeBooking->status);
        $this->assertNull($freeBooking->payment_request_id);

        // 2. Super Admin switches Consultations to Paid (45 min, 350 EGP)
        $this->actingAs($this->superAdmin1)
            ->put(route('admin.consultations.settings.update'), [
                'enable_consultations' => '1',
                'consultation_duration_minutes' => 45,
                'consultation_pricing_mode' => 'paid',
                'consultation_price' => 350,
                'consultation_currency' => 'EGP',
                'consultation_availability_note' => 'Sun–Thu 6PM–9PM',
            ])
            ->assertRedirect(route('admin.consultations.index'));

        // Student books Paid Consultation with InstaPay screenshot
        $this->actingAs($this->student)
            ->post(route('consultations.store'), [
                'instructor_id' => $this->superAdmin1->id,
                'preferred_date' => now()->addDays(4)->toDateString(),
                'preferred_time' => '20:00',
                'topic' => 'Deep Architecture Review',
                'phone' => '01055443322',
                'screenshot' => $this->fakePng('consult-transfer.png'),
            ])
            ->assertRedirect(route('consultations.index'));

        $paidBooking = Consultation::latest('id')->firstOrFail();
        $this->assertSame(Consultation::PRICING_PAID, $paidBooking->pricing_mode);
        $this->assertSame(45, $paidBooking->duration_minutes);
        $this->assertNotNull($paidBooking->payment_request_id);

        $payment = PaymentRequest::findOrFail($paidBooking->payment_request_id);
        $this->assertSame($paidBooking->id, $payment->consultation_id);

        // Approving the consultation payment automatically confirms the consultation & notifies student!
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.payments.approve', $payment), ['admin_note' => 'Transfer received'])
            ->assertRedirect();

        $this->assertSame(Consultation::STATUS_CONFIRMED, $paidBooking->fresh()->status);

        // Toggling Consultations OFF hides /consultations (404)
        AcademySetting::setValue('enable_consultations', '0');
        $this->get(route('consultations.index'))->assertNotFound();
    }

    // =========================================================================
    // FEATURE 5: BUNNY.NET STREAM VIDEO ID SUPPORT + SEQUENTIAL DRIP-LOCK
    // =========================================================================

    public function test_bunny_stream_lesson_creation_rendering_and_sequential_drip_lock(): void
    {
        // Save default global Bunny Library ID in Academy Settings
        AcademySetting::setValue('bunny_library_id', '321456');

        $course = $this->createCourse($this->superAdmin1, [
            'title' => 'Bunny Video Course',
            'slug' => 'bunny-video-course',
            'description' => 'Streaming via Bunny.net',
            'price' => 1100,
        ]);

        // Create Lesson 1 using Bunny.net Stream (inheriting global library ID)
        $this->actingAs($this->superAdmin1)
            ->post(route('admin.courses.lessons.store', $course), [
                'title' => 'Bunny Intro Video',
                'description' => 'Hosted on Bunny Stream',
                'video_source_type' => Lesson::SOURCE_BUNNY,
                'bunny_video_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
                'duration_seconds' => 30,
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.courses.lessons.index', $course));

        $lesson1 = Lesson::where('course_id', $course->id)->firstOrFail();
        $this->assertTrue($lesson1->isBunnyStream());
        $this->assertSame(
            'https://iframe.mediadelivery.net/embed/321456/a1b2c3d4-e5f6-7890-abcd-ef1234567890?autoplay=false&preload=true',
            $lesson1->bunnyEmbedUrl()
        );

        // Create Lesson 2 (locked until Lesson 1 reaches 100%)
        $lesson2 = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Bunny Part 2',
            'position' => 2,
            'video_source_type' => Lesson::SOURCE_BUNNY,
            'bunny_library_id' => '321456',
            'bunny_video_id' => '11111111-2222-3333-4444-555555555555',
            'duration_seconds' => 40,
            'is_published' => true,
        ]);

        $this->createEnrollment($this->student, $course, 30);

        // Student Course Player renders Bunny.net iframe inside 16:9 .ma-video-shell for Lesson 1
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$course, $lesson1]))
            ->assertOk()
            ->assertSee('data-bunny-player', false)
            ->assertSee('https://iframe.mediadelivery.net/embed/321456/a1b2c3d4-e5f6-7890-abcd-ef1234567890', false);

        // Lesson 2 is strictly drip-locked (403) before Lesson 1 finishes
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$course, $lesson2]))
            ->assertForbidden();

        // Bunny progress heartbeats complete Lesson 1 at 100% -> unlocks Lesson 2!
        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$course, $lesson1]), [
                'current_time' => 15,
                'duration' => 30,
                'delta_seconds' => 15,
                'ended' => false,
            ])
            ->assertOk();

        $this->travel(15)->seconds();

        $this->actingAs($this->student)
            ->postJson(route('student.courses.lessons.progress', [$course, $lesson1]), [
                'current_time' => 30,
                'duration' => 30,
                'delta_seconds' => 15,
                'ended' => true,
            ])
            ->assertOk()
            ->assertJson(['completed' => true, 'just_completed' => true]);

        // Lesson 2 is now unlocked!
        $this->actingAs($this->student)
            ->get(route('student.courses.learn', [$course, $lesson2]))
            ->assertOk()
            ->assertSee('11111111-2222-3333-4444-555555555555', false);
    }
}
