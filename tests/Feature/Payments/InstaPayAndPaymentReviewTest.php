<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstaPayAndPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $teacher;

    private User $support;

    private User $student;

    private User $otherStudent;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create([
            'name' => 'Real Student Name',
            'email' => 'real.student@example.com',
        ]);
        $this->otherStudent = User::factory()->student()->create();
        $this->course = Course::query()->published()->firstOrFail();
    }

    private function fakePng(string $name = 'proof.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_super_admin_can_upload_instapay_qr_and_students_see_it_on_subscribe_page(): void
    {
        Storage::fake('public');

        $this->actingAs($this->sa1)
            ->put(route('admin.settings.instapay-qr.update'), [
                'instapay_qr' => $this->fakePng('qr.png'),
                'instapay_account_note' => 'Transfer to mashacademy@instapay',
            ])
            ->assertRedirect();

        $qrPath = AcademySetting::getValue('instapay_qr_path');
        $this->assertNotNull($qrPath);
        Storage::disk('public')->assertExists($qrPath);

        $this->actingAs($this->student)
            ->get(route('student.courses.subscribe', $this->course))
            ->assertOk()
            ->assertSee($qrPath)
            ->assertSee('Transfer to mashacademy@instapay')
            ->assertSee('Real Student Name')
            ->assertSee('real.student@example.com');
    }

    public function test_payment_form_requires_phone_and_valid_screenshot_and_prevents_name_email_tampering(): void
    {
        Storage::fake('local');

        // Missing phone and screenshot -> validation error
        $this->actingAs($this->student)
            ->from(route('student.courses.subscribe', $this->course))
            ->post(route('student.courses.subscribe.store', $this->course), [])
            ->assertSessionHasErrors(['phone', 'screenshot']);

        // Non-image screenshot rejected
        $badFile = UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;');
        $this->actingAs($this->student)
            ->from(route('student.courses.subscribe', $this->course))
            ->post(route('student.courses.subscribe.store', $this->course), [
                'phone' => '+201099887766',
                'screenshot' => $badFile,
            ])
            ->assertSessionHasErrors(['screenshot']);

        // Tampering with name/email/status/final_price in POST body must be ignored
        $this->actingAs($this->student)
            ->post(route('student.courses.subscribe.store', $this->course), [
                'name' => 'Spoofed Hacker Name',
                'email' => 'spoofed@evil.com',
                'status' => 'approved',
                'final_price' => 0,
                'phone' => '+201099887766',
                'screenshot' => $this->fakePng('transfer.png'),
            ])
            ->assertRedirect(route('student.payments.index'));

        $payment = PaymentRequest::query()->firstOrFail();
        $this->assertSame('Real Student Name', $payment->name);
        $this->assertSame('real.student@example.com', $payment->email);
        $this->assertSame(PaymentRequest::STATUS_PENDING, $payment->status);
        $this->assertSame('1800.00', (string) $payment->final_price);
        Storage::disk('local')->assertExists($payment->screenshot_path);

        // Duplicate pending submission for the same course must be blocked
        $this->actingAs($this->student)
            ->post(route('student.courses.subscribe.store', $this->course), [
                'phone' => '+201099887766',
                'screenshot' => $this->fakePng('duplicate.png'),
            ])
            ->assertRedirect(route('student.payments.index'))
            ->assertSessionHasErrors('course');

        $this->assertSame(1, PaymentRequest::count());
    }

    public function test_only_super_admins_can_review_payments_and_view_private_screenshots(): void
    {
        Storage::fake('local');

        $this->actingAs($this->student)
            ->post(route('student.courses.subscribe.store', $this->course), [
                'phone' => '+201099887766',
                'screenshot' => $this->fakePng(),
            ]);

        $payment = PaymentRequest::query()->firstOrFail();

        // Owner student can view own screenshot; other student gets 403
        $this->actingAs($this->student)->get(route('student.payments.screenshot', $payment))->assertOk();
        $this->actingAs($this->otherStudent)->get(route('student.payments.screenshot', $payment))->assertForbidden();

        // Teacher, Support, Student blocked from admin payments & approve/reject
        foreach ([$this->teacher, $this->support, $this->student] as $actor) {
            $this->actingAs($actor)->get(route('admin.payments.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.payments.screenshot', $payment))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.payments.approve', $payment))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.payments.reject', $payment), ['admin_note' => 'No'])->assertForbidden();
        }

        // Super Admin can view screenshot and approve + immediately enroll for 3 months
        $this->actingAs($this->sa1)->get(route('admin.payments.screenshot', $payment))->assertOk();

        $this->actingAs($this->sa1)
            ->post(route('admin.payments.approve', $payment), [
                'admin_note' => 'Verified InstaPay transfer.',
                'enroll_now' => true,
                'duration_value' => 3,
                'duration_unit' => Enrollment::UNIT_MONTHS,
            ])
            ->assertRedirect(route('admin.payments.index'));

        $payment->refresh();
        $this->assertTrue($payment->isApproved());
        $this->assertNotNull($payment->enrollment);
        $this->assertTrue($payment->enrollment->isCurrentlyActive());
    }
}
