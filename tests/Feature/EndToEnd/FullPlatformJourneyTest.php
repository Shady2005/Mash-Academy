<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullPlatformJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_end_to_end_student_and_admin_lifecycle_across_all_five_phases(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::fake('public');

        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $course = Course::query()->where('created_by', $sa1->id)->firstOrFail();

        // Give each demo lesson a short 20-second video for realistic heartbeat simulation
        $mp4 = base64_decode('AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQ==').str_repeat("\x00", 64);
        foreach ($course->publishedLessons()->get() as $lesson) {
            $path = "courses/{$course->id}/videos/e2e-{$lesson->id}.mp4";
            Storage::disk('local')->put($path, $mp4);
            $lesson->update([
                'video_path' => $path,
                'video_mime' => 'video/mp4',
                'duration_seconds' => 20,
            ]);
        }

        // Phase 3 setup: Promo code MASH25 (25% off)
        $promo = new PromoCode([
            'code' => 'MASH25',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 25,
            'is_active' => true,
        ]);
        $promo->created_by = $sa1->id;
        $promo->save();

        // 1. Phase 1: Student registers with mandatory Policy checkbox
        $this->post(route('register.store'), [
            'name' => 'E2E Graduate',
            'email' => 'graduate@example.com',
            'phone' => '+201055556666',
            'password' => 'Graduate@2026',
            'password_confirmation' => 'Graduate@2026',
            'agree_policy' => '1',
        ])->assertRedirect(route('student.dashboard'));

        $student = User::query()->where('email', 'graduate@example.com')->firstOrFail();

        // 2. Phase 2: Browse shows course locked by default
        $this->actingAs($student)->get(route('student.courses.learn', $course))->assertForbidden();

        // 3. Phase 3: Student subscribes with Promo Code + InstaPay screenshot
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $this->actingAs($student)->post(route('student.courses.subscribe.store', $course), [
            'phone' => '+201055556666',
            'promo_code' => 'MASH25',
            'screenshot' => UploadedFile::fake()->createWithContent('instapay.png', $png),
        ])->assertRedirect(route('student.payments.index'));

        $payment = PaymentRequest::query()->firstOrFail();
        $this->assertSame('1350.00', (string) $payment->final_price);

        // Super Admin approves & enrolls student for 3 months
        $startTime = CarbonImmutable::parse('2026-06-01 10:00:00');
        $this->travelTo($startTime);

        $this->actingAs($sa1)->post(route('admin.payments.approve', $payment), [
            'enroll_now' => true,
            'duration_value' => 3,
            'duration_unit' => Enrollment::UNIT_MONTHS,
        ])->assertRedirect(route('admin.payments.index'));

        // 4. Phase 4: Student watches all lessons sequentially (100% full watch)
        $clock = $startTime;
        foreach ($course->publishedLessons()->get() as $lesson) {
            $this->travelTo($clock);
            $this->actingAs($student)->postJson(route('student.courses.lessons.progress', [$course, $lesson]), [
                'current_time' => 10, 'duration' => 20, 'delta_seconds' => 10, 'ended' => false,
            ])->assertOk();

            $clock = $clock->addSeconds(10);
            $this->travelTo($clock);
            $this->actingAs($student)->postJson(route('student.courses.lessons.progress', [$course, $lesson]), [
                'current_time' => 20, 'duration' => 20, 'delta_seconds' => 10, 'ended' => true,
            ])->assertOk()->assertJson(['completed' => true]);

            $clock = $clock->addSeconds(5);
        }

        // Student passes quiz & submits flexible assignment
        $quiz = $course->quizzes()->with('questions.options')->firstOrFail();
        $q = $quiz->questions->first();
        $rightOpt = $q->options->firstWhere('is_correct', true);
        $this->actingAs($student)->post(route('student.courses.quizzes.submit', [$course, $quiz]), [
            'answers' => [$q->id => $rightOpt->id],
        ])->assertRedirect();

        $assignment = $course->assignments()->with('questions.options')->firstOrFail();
        $aq = $assignment->questions->first();
        $aRight = $aq->options->firstWhere('is_correct', true);
        $this->actingAs($student)->post(route('student.courses.assignments.submit', [$course, $assignment]), [
            'file' => UploadedFile::fake()->createWithContent('work.pdf', '%PDF-1.4 work'),
            'answers' => [$aq->id => $aRight->id],
        ])->assertRedirect();

        // 5. Phase 4 & 5: Final Project unlocked at 100% -> submit -> Certificate unlocks & verifies!
        $this->actingAs($student)->post(route('student.courses.final-project.submit', $course), [
            'file' => UploadedFile::fake()->createWithContent('capstone.pdf', '%PDF-1.4 capstone'),
        ])->assertRedirect();

        $cert = Certificate::query()->where('user_id', $student->id)->firstOrFail();
        $this->actingAs($student)->get(route('student.certificates.show', $course))->assertOk()->assertSee($cert->certificate_code);
        $this->get(route('certificates.verify', ['code' => $cert->certificate_code]))->assertOk()->assertSee('E2E Graduate');

        // Support inspects Analytics & Student Dossier (read-only)
        $this->actingAs($support)->get(route('admin.analytics.index'))->assertOk()->assertSee('E2E Graduate')->assertSee('100%');
        $this->actingAs($support)->get(route('admin.students.show', $student))->assertOk()->assertSee($cert->certificate_code);

        // 6. Expiry after 3 months immediately re-locks course!
        $this->travelTo($startTime->addMonths(3)->addSecond());
        $this->actingAs($student)->get(route('student.courses.learn', $course))->assertForbidden();

        $this->artisan('enrollments:expire')->expectsOutputToContain('Expired 1 overdue enrollment(s).')->assertExitCode(0);
    }
}
