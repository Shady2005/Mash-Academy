<?php

namespace Tests\Feature\Enhancements;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FinalProject;
use App\Models\Lesson;
use App\Models\PaymentRequest;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreSignOffEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const TINY_MP4_B64 = 'AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQ==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function fakePng(string $name = 'qr.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::TINY_PNG_B64));
    }

    private function createCourse(User $creator, string $title, float $price = 1500, ?string $shortDescription = null, string $description = 'Full course description for automated verification.'): Course
    {
        $course = new Course([
            'title' => $title,
            'slug' => Course::generateUniqueSlug($title),
            'short_description' => $shortDescription,
            'description' => $description,
            'price' => $price,
            'currency' => 'EGP',
            'is_published' => true,
            'is_featured' => false,
        ]);
        $course->created_by = $creator->id;
        $course->save();

        return $course;
    }

    private function createPublishedCourseWithFullContent(User $creator, string $title = 'Enhancement Test Course'): array
    {
        Storage::fake('local');

        $course = $this->createCourse($creator, $title, 1500, 'Short summary for testing.');

        $videoBytes = base64_decode(self::TINY_MP4_B64).str_repeat("\0", 512);
        Storage::disk('local')->put("courses/{$course->id}/lessons/l1.mp4", $videoBytes);
        Storage::disk('local')->put("courses/{$course->id}/lessons/l2.mp4", $videoBytes);

        $lesson1 = Lesson::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 1: Intro',
            'position' => 1,
            'video_path' => "courses/{$course->id}/lessons/l1.mp4",
            'video_mime' => 'video/mp4',
            'duration_seconds' => 60,
            'is_published' => true,
        ]);

        $lesson2 = Lesson::query()->create([
            'course_id' => $course->id,
            'title' => 'Lesson 2: Advanced',
            'position' => 2,
            'video_path' => "courses/{$course->id}/lessons/l2.mp4",
            'video_mime' => 'video/mp4',
            'duration_seconds' => 60,
            'is_published' => true,
        ]);

        $quiz = new Quiz([
            'course_id' => $course->id,
            'lesson_id' => $lesson2->id,
            'title' => 'Lesson 2 Quiz',
            'pass_mark' => 60,
        ]);
        $quiz->created_by = $creator->id;
        $quiz->save();

        $assignment = new Assignment([
            'course_id' => $course->id,
            'lesson_id' => $lesson2->id,
            'title' => 'Lesson 2 Assignment',
            'instructions' => 'Upload your solution.',
            'submission_type' => Assignment::TYPE_FILE,
        ]);
        $assignment->created_by = $creator->id;
        $assignment->save();

        $finalProject = new FinalProject([
            'course_id' => $course->id,
            'title' => 'Capstone Final Project',
            'instructions' => 'Build and submit your final project.',
            'required_completion_percentage' => 95,
            'is_published' => true,
        ]);
        $finalProject->created_by = $creator->id;
        $finalProject->save();

        return compact('course', 'lesson1', 'lesson2', 'quiz', 'assignment', 'finalProject');
    }

    /**
     * 1. Unrestricted Student-Area Access for Super Admins (1 & 2) + Navbar Switcher.
     */
    public function test_super_admins_have_unrestricted_access_to_all_student_routes_and_navbar_switcher(): void
    {
        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();

        $bundle = $this->createPublishedCourseWithFullContent($teacher);
        /** @var Course $course */
        $course = $bundle['course'];
        /** @var Lesson $lesson2 */
        $lesson2 = $bundle['lesson2'];
        /** @var Quiz $quiz */
        $quiz = $bundle['quiz'];
        /** @var Assignment $assignment */
        $assignment = $bundle['assignment'];

        foreach ([$sa1, $sa2] as $admin) {
            // Public Home & Admin Dashboard show Student View switcher
            $this->actingAs($admin)->get(route('home'))
                ->assertOk()
                ->assertSee(route('admin.dashboard'))
                ->assertSee(route('student.dashboard'));

            $this->actingAs($admin)->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee(route('student.dashboard'));

            // Student Portal shows Admin Panel switcher & loads without 403
            $this->actingAs($admin)->get(route('student.dashboard'))
                ->assertOk()
                ->assertSee(route('admin.dashboard'));

            $this->actingAs($admin)->get(route('student.my-courses.index'))->assertOk()->assertSee($course->title);
            $this->actingAs($admin)->get(route('student.courses.index'))->assertOk()->assertSee($course->title);
            $this->actingAs($admin)->get(route('student.courses.subscribe', $course))->assertOk();
            $this->actingAs($admin)->get(route('student.courses.access', $course))->assertOk();

            // Bypass drip-lock directly to Lesson 2 & stream Lesson 2 video
            $this->actingAs($admin)->get(route('student.courses.learn', [$course, $lesson2]))->assertOk();
            $this->actingAs($admin)->get(route('student.courses.lessons.stream', [$course, $lesson2]))->assertOk();

            // Bypass linked-lesson lock on Quiz & Assignment
            $this->actingAs($admin)->get(route('student.courses.quizzes.show', [$course, $quiz]))->assertOk();
            $this->actingAs($admin)->get(route('student.courses.assignments.show', [$course, $assignment]))->assertOk();

            // Bypass 95% Final Project gate
            $this->actingAs($admin)->get(route('student.courses.final-project.show', $course))->assertOk();

            // Bypass Certificate lock (index, view & download)
            $this->actingAs($admin)->get(route('student.certificates.index'))->assertOk()->assertSee($course->title);
            $this->actingAs($admin)->get(route('student.certificates.show', $course))->assertOk();
            $this->actingAs($admin)->get(route('student.certificates.download', $course))->assertOk();
        }

        // Teacher & Customer Support remain blocked from Student Portal
        $this->actingAs($teacher)->get(route('student.courses.subscribe', $course))->assertForbidden();
        $this->actingAs($support)->get(route('student.courses.subscribe', $course))->assertForbidden();
    }

    /**
     * 2. Customer Support Student & Enrollment Visibility (including Shady, active, expired, pending, none) + Strict Read-Only.
     */
    public function test_customer_support_sees_all_registered_students_and_statuses_read_only(): void
    {
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();

        $course = $this->createCourse($sa1, 'Support Visibility Course', 1000, null, 'Course for testing support directory visibility.');

        // 1. Existing student "Shady" with ZERO enrollments
        $shady = User::factory()->student()->create([
            'name' => 'Shady',
            'email' => 'shady@example.com',
            'phone' => '+201001112233',
        ]);

        // 2. Student with active enrollment
        $activeStudent = User::factory()->student()->create([
            'name' => 'Active Learner',
            'email' => 'active@example.com',
        ]);
        $activeEnrollment = new Enrollment([
            'user_id' => $activeStudent->id,
            'course_id' => $course->id,
            'duration_value' => 30,
            'duration_unit' => 'days',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addDays(28),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $activeEnrollment->created_by = $sa1->id;
        $activeEnrollment->save();

        // 3. Student with expired enrollment (originally marked active with past expires_at -> auto-expired on load)
        $expiredStudent = User::factory()->student()->create([
            'name' => 'Expired Learner',
            'email' => 'expired@example.com',
        ]);
        $expiredEnrollment = new Enrollment([
            'user_id' => $expiredStudent->id,
            'course_id' => $course->id,
            'duration_value' => 30,
            'duration_unit' => 'days',
            'starts_at' => now()->subDays(40),
            'expires_at' => now()->subDays(5),
            'status' => Enrollment::STATUS_ACTIVE,
        ]);
        $expiredEnrollment->created_by = $sa1->id;
        $expiredEnrollment->save();

        // 4. Student with pending payment request
        $pendingStudent = User::factory()->student()->create([
            'name' => 'Pending Payer',
            'email' => 'pending@example.com',
        ]);
        $pendingPayment = new PaymentRequest([
            'course_id' => $course->id,
            'original_price' => 1000,
            'discount_amount' => 0,
            'final_price' => 1000,
            'currency' => 'EGP',
            'name' => $pendingStudent->name,
            'email' => $pendingStudent->email,
            'phone' => '+201099998888',
            'screenshot_path' => 'payment-proofs/dummy.png',
        ]);
        $pendingPayment->user_id = $pendingStudent->id;
        $pendingPayment->status = PaymentRequest::STATUS_PENDING;
        $pendingPayment->save();

        // All 4 students appear in Customer Support directory
        $indexRes = $this->actingAs($support)->get(route('admin.students.index'));
        $indexRes->assertOk()
            ->assertSee('Shady')
            ->assertSee('Active Learner')
            ->assertSee('Expired Learner')
            ->assertSee('Pending Payer')
            ->assertSee('1 Active')
            ->assertSee('1 Expired/Revoked')
            ->assertSee('1 Pending Payment')
            ->assertSee('Not Enrolled')
            ->assertDontSee('Delete');

        // Search finds Shady accurately
        $this->actingAs($support)
            ->get(route('admin.students.index', ['q' => 'Shady']))
            ->assertOk()
            ->assertSee('Shady')
            ->assertDontSee('Active Learner');

        // Filter tests
        $this->actingAs($support)
            ->get(route('admin.students.index', ['filter' => 'pending_payment']))
            ->assertOk()
            ->assertSee('Pending Payer')
            ->assertDontSee('Shady');

        $this->actingAs($support)
            ->get(route('admin.students.index', ['filter' => 'none']))
            ->assertOk()
            ->assertSee('Shady')
            ->assertDontSee('Active Learner');

        // Detail views work for all students including Shady & Pending Payer
        $this->actingAs($support)->get(route('admin.students.show', $shady->id))
            ->assertOk()
            ->assertSee('Shady')
            ->assertSee('+201001112233');

        $this->actingAs($support)->get(route('admin.students.show', $pendingStudent->id))
            ->assertOk()
            ->assertSee('Pending Payer')
            ->assertSee('Pending');

        // Strict read-only enforcement on write routes
        $this->actingAs($support)->post(route('admin.enrollments.store'), [])->assertForbidden();
        $this->actingAs($support)->get(route('admin.instructor-profile.edit'))->assertForbidden();
    }

    /**
     * 3. Per-Course & Per-Instructor InstaPay QR Code System with 3-tier fallback.
     */
    public function test_per_course_and_per_instructor_instapay_qr_resolution_and_permissions(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $student = User::factory()->student()->create();

        // Set Global Academy QR
        AcademySetting::setValue('instapay_qr_path', 'settings/global-qr.png');
        AcademySetting::setValue('instapay_account_note', 'global@instapay');

        // Create Teacher's course & Super Admin's course
        $teacherCourse = $this->createCourse($teacher, 'Teacher Python Course', 1200, null, 'Teacher owned course for QR resolution tests.');
        $saCourse = $this->createCourse($sa1, 'SA1 Architecture Course', 2000, null, 'Super Admin owned course.');

        // Tier 3 (Fallback to Global QR when neither course nor teacher has QR)
        $this->actingAs($student)
            ->get(route('student.courses.subscribe', $teacherCourse))
            ->assertOk()
            ->assertSee('global-qr.png')
            ->assertSee('global@instapay');

        // Tier 2: Teacher uploads their personal default InstaPay QR
        $this->actingAs($teacher)
            ->put(route('admin.instructor-profile.update'), [
                'name' => $teacher->name,
                'instapay_qr' => $this->fakePng('teacher-personal.png'),
                'instapay_note' => 'teacher.personal@instapay',
            ])
            ->assertRedirect(route('admin.instructor-profile.edit'));

        $teacher->refresh();
        $this->assertNotNull($teacher->instapay_qr_path);
        Storage::disk('public')->assertExists($teacher->instapay_qr_path);

        // Subscribe page now resolves Teacher's personal QR!
        $this->actingAs($student)
            ->get(route('student.courses.subscribe', $teacherCourse))
            ->assertOk()
            ->assertSee($teacher->instapay_qr_path)
            ->assertSee('teacher.personal@instapay');

        // Tier 1: Teacher uploads a course-specific InstaPay QR on their own course
        $this->actingAs($teacher)
            ->put(route('admin.courses.update', $teacherCourse), [
                'title' => $teacherCourse->title,
                'description' => $teacherCourse->description,
                'price' => 1200,
                'currency' => 'EGP',
                'is_published' => 1,
                'instapay_qr' => $this->fakePng('course-specific.png'),
                'instapay_note' => 'course.override@instapay',
            ])
            ->assertRedirect(route('admin.courses.edit', $teacherCourse));

        $teacherCourse->refresh();
        $this->assertNotNull($teacherCourse->instapay_qr_path);

        // Subscribe page now resolves the Course-specific QR override!
        $this->actingAs($student)
            ->get(route('student.courses.subscribe', $teacherCourse))
            ->assertOk()
            ->assertSee($teacherCourse->instapay_qr_path)
            ->assertSee('course.override@instapay');

        // Teacher CANNOT modify or delete InstaPay QR on Super Admin's course (403)
        $this->actingAs($teacher)
            ->put(route('admin.courses.update', $saCourse), [
                'title' => $saCourse->title,
                'description' => $saCourse->description,
                'price' => 2000,
                'currency' => 'EGP',
                'instapay_qr' => $this->fakePng('hacked.png'),
            ])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->delete(route('admin.courses.instapay-qr.destroy', $saCourse))
            ->assertForbidden();

        // Student submits payment proof against Teacher's course -> saved as pending for Super Admin review
        $this->actingAs($student)
            ->post(route('student.courses.subscribe.store', $teacherCourse), [
                'phone' => '+201055554444',
                'screenshot' => $this->fakePng('transfer.png'),
            ])
            ->assertRedirect(route('student.payments.index'));

        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $student->id,
            'course_id' => $teacherCourse->id,
            'status' => PaymentRequest::STATUS_PENDING,
        ]);

        // Removing per-course QR reverts cleanly to Instructor personal QR
        $this->actingAs($teacher)
            ->delete(route('admin.courses.instapay-qr.destroy', $teacherCourse))
            ->assertRedirect(route('admin.courses.edit', $teacherCourse));

        $teacherCourse->refresh();
        $this->assertNull($teacherCourse->instapay_qr_path);
        $this->assertSame('instructor', $teacherCourse->resolveInstaPayDetails()['source']);
    }

    /**
     * 4. Instructor Portfolio Profiles & "Meet Our Instructors" section on Public Home Page.
     */
    public function test_instructors_update_profiles_and_home_page_displays_meet_our_instructors_bilingually(): void
    {
        Storage::fake('public');

        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $student = User::factory()->student()->create();

        // Non-instructors cannot edit instructor profiles
        $this->actingAs($support)->put(route('admin.instructor-profile.update'), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($student)->put(route('admin.instructor-profile.update'), ['name' => 'Nope'])->assertForbidden();

        // Update SA1 profile with avatar, EN/AR title & bio, and external Portfolio URL
        $this->actingAs($sa1)
            ->put(route('admin.instructor-profile.update'), [
                'name' => 'Eng. Mahmoud Mash',
                'instructor_title' => 'Co-Founder & Principal Architect',
                'instructor_title_ar' => 'شريك مؤسس ومهندس برمجيات رئيسي',
                'instructor_bio' => 'Leading full-stack architecture and mentoring engineers.',
                'instructor_bio_ar' => 'قيادة هندسة البرمجيات وتوجيه المطورين.',
                'portfolio_url' => 'https://mahmoud-mash.example.com',
                'linkedin_url' => 'https://linkedin.com/in/mahmoud-mash',
                'github_url' => 'https://github.com/mahmoud-mash',
                'avatar' => $this->fakePng('sa1-avatar.png'),
            ])
            ->assertRedirect(route('admin.instructor-profile.edit'));

        // Update Teacher profile with portfolio URL
        $this->actingAs($teacher)
            ->put(route('admin.instructor-profile.update'), [
                'name' => 'Dr. Sara Instructor',
                'instructor_title' => 'Senior Backend Instructor',
                'instructor_title_ar' => 'محاضرة باك إند أولى',
                'instructor_bio' => 'Specialized in Laravel and database performance.',
                'instructor_bio_ar' => 'متخصصة في لارافيل وأداء قواعد البيانات.',
                'portfolio_url' => 'https://sara-dev.example.com',
            ])
            ->assertRedirect(route('admin.instructor-profile.edit'));

        $sa1->refresh();
        $this->assertNotNull($sa1->avatar_path);
        Storage::disk('public')->assertExists($sa1->avatar_path);

        // Public Home Page (English LTR) shows all 3 instructors & external portfolio buttons
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Meet Our Instructors')
            ->assertSee('Eng. Mahmoud Mash')
            ->assertSee('Co-Founder &amp; Principal Architect', false)
            ->assertSee('https://mahmoud-mash.example.com')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee($sa2->name)
            ->assertSee('Dr. Sara Instructor')
            ->assertSee('https://sara-dev.example.com');

        // Public Home Page (Arabic RTL) shows Arabic localized titles & bios
        $this->withSession(['locale' => 'ar'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('تعرّف على محاضرينا')
            ->assertSee('شريك مؤسس ومهندس برمجيات رئيسي')
            ->assertSee('محاضرة باك إند أولى')
            ->assertSee('عرض البورتفوليو / الموقع');
    }
}
