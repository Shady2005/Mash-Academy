<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromoCodeManagerAndCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $sa2;

    private User $teacher;

    private User $support;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
        $this->course = Course::query()->where('created_by', $this->sa1->id)->published()->firstOrFail(); // 1800 EGP
    }

    private function fakePng(string $name = 'proof.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function createCourseFor(User $owner, string $title, string $slug, float $price = 1000): Course
    {
        $course = new Course([
            'title' => $title,
            'slug' => $slug,
            'description' => 'Instructor course description',
            'price' => $price,
            'currency' => 'EGP',
            'is_published' => true,
        ]);
        $course->created_by = $owner->id;
        $course->save();

        return $course;
    }

    public function test_super_admin_can_crud_and_toggle_promo_codes(): void
    {
        $this->actingAs($this->sa1)
            ->post(route('admin.promo-codes.store'), [
                'code' => 'mash20',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 20,
                'max_uses' => 50,
                'expires_at' => now()->addMonth()->format('Y-m-d\TH:i'),
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.promo-codes.index'));

        $promo = PromoCode::query()->where('code', 'MASH20')->firstOrFail();
        $this->assertTrue($promo->is_active);
        $this->assertSame($this->sa1->id, $promo->created_by);

        // Toggle disable
        $this->actingAs($this->sa1)
            ->patch(route('admin.promo-codes.toggle', $promo))
            ->assertRedirect(route('admin.promo-codes.index'));
        $this->assertFalse($promo->fresh()->is_active);

        // Edit to fixed 300 EGP & enable
        $this->actingAs($this->sa1)
            ->put(route('admin.promo-codes.update', $promo), [
                'code' => 'MASH300',
                'discount_type' => PromoCode::TYPE_FIXED,
                'discount_value' => 300,
                'max_uses' => 10,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.promo-codes.index'));

        $promo->refresh();
        $this->assertSame('MASH300', $promo->code);
        $this->assertSame(PromoCode::TYPE_FIXED, $promo->discount_type);

        // Delete
        $this->actingAs($this->sa1)
            ->delete(route('admin.promo-codes.destroy', $promo))
            ->assertRedirect(route('admin.promo-codes.index'));
        $this->assertDatabaseMissing('promo_codes', ['id' => $promo->id]);
    }

    public function test_support_and_student_cannot_manage_promo_codes_while_teacher_manages_only_own_codes(): void
    {
        // Support & Student remain strictly forbidden
        foreach ([$this->support, $this->student] as $actor) {
            $this->actingAs($actor)->get(route('admin.promo-codes.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.promo-codes.store'), [
                'code' => 'FREE99',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 99,
            ])->assertForbidden();
        }

        // Super Admin 1 creates a code
        $saPromo = new PromoCode([
            'code' => 'SHADY10',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'is_active' => true,
        ]);
        $saPromo->created_by = $this->sa1->id;
        $saPromo->save();

        // Teacher can create & manage their own promo code
        $this->actingAs($this->teacher)
            ->post(route('admin.promo-codes.store'), [
                'code' => 'MARIAM25',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 25,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.promo-codes.index'));

        $teacherPromo = PromoCode::where('code', 'MARIAM25')->firstOrFail();
        $this->assertSame($this->teacher->id, $teacherPromo->created_by);

        // Teacher's dashboard lists ONLY their own code, NOT Super Admin's code
        $this->actingAs($this->teacher)
            ->get(route('admin.promo-codes.index'))
            ->assertOk()
            ->assertSee('MARIAM25')
            ->assertDontSee('SHADY10');

        // Teacher can toggle their own code, but gets 403 on Super Admin's code
        $this->actingAs($this->teacher)->patch(route('admin.promo-codes.toggle', $teacherPromo))->assertRedirect();
        $this->actingAs($this->teacher)->patch(route('admin.promo-codes.toggle', $saPromo))->assertForbidden();
        $this->actingAs($this->teacher)->put(route('admin.promo-codes.update', $saPromo), [
            'code' => 'HACKED',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 99,
        ])->assertForbidden();
        $this->actingAs($this->teacher)->delete(route('admin.promo-codes.destroy', $saPromo))->assertForbidden();
    }

    public function test_promo_codes_are_strictly_isolated_per_instructor_across_courses_packages_and_consultations(): void
    {
        Storage::fake('local');

        $shadyExisting = $this->course;
        $abdullahCourse = $this->createCourseFor($this->sa2, 'Abdullah Cloud Course', 'abdullah-cloud', 1200);
        $mariamCourse = $this->createCourseFor($this->teacher, 'Mariam Design Course', 'mariam-design', 800);

        // Shady creates promo code MMM (20% off)
        $this->actingAs($this->sa1)
            ->post(route('admin.promo-codes.store'), [
                'code' => 'MMM',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 20,
                'is_active' => true,
            ])
            ->assertRedirect();

        // Mariam creates promo code MARIAM25 (25% off)
        $this->actingAs($this->teacher)
            ->post(route('admin.promo-codes.store'), [
                'code' => 'MARIAM25',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 25,
                'is_active' => true,
            ])
            ->assertRedirect();

        // Shady later adds a brand-new future course -> MMM must automatically work on it!
        $shadyFutureCourse = $this->createCourseFor($this->sa1, 'Shady Future AI Course', 'shady-future-ai', 2000);

        // 1. Shady's MMM works on ALL of Shady's courses (existing + future)
        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $shadyExisting), ['code' => 'MMM'])
            ->assertOk()
            ->assertJson(['valid' => true, 'code' => 'MMM']);

        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $shadyFutureCourse), ['code' => 'MMM'])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount_amount' => 400, 'final_price' => 1600]);

        // 2. Shady's MMM is REJECTED on Abdullah's and Mariam's courses (AJAX + Form submit)
        foreach ([$abdullahCourse, $mariamCourse] as $otherCourse) {
            $this->actingAs($this->student)
                ->postJson(route('student.courses.promo-check', $otherCourse), ['code' => 'MMM'])
                ->assertStatus(422)
                ->assertJson([
                    'valid' => false,
                    'message' => "This promo code is not valid for this instructor's course.",
                ]);

            $this->actingAs($this->student)
                ->post(route('student.courses.subscribe.store', $otherCourse), [
                    'phone' => '01012345678',
                    'promo_code' => 'MMM',
                    'screenshot' => $this->fakePng(),
                ])
                ->assertSessionHasErrors('promo_code');
        }

        // 3. Mariam's MARIAM25 works ONLY on Mariam's course, rejected on Shady's & Abdullah's courses
        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $mariamCourse), ['code' => 'MARIAM25'])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount_amount' => 200, 'final_price' => 600]);

        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $shadyExisting), ['code' => 'MARIAM25'])
            ->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => "This promo code is not valid for this instructor's course."]);

        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $abdullahCourse), ['code' => 'MARIAM25'])
            ->assertStatus(422)
            ->assertJson(['valid' => false]);

        // 4. Packages: valid ONLY when package.created_by AND all included courses belong to promo creator
        $shadyPackage = new CoursePackage([
            'title' => 'Shady Full Bundle',
            'slug' => 'shady-full-bundle',
            'description' => 'Shady courses only',
            'price' => 3000,
            'currency' => 'EGP',
            'is_published' => true,
        ]);
        $shadyPackage->created_by = $this->sa1->id;
        $shadyPackage->save();
        $shadyPackage->courses()->sync([$shadyExisting->id, $shadyFutureCourse->id]);

        $mixedPackage = new CoursePackage([
            'title' => 'Mixed Instructor Bundle',
            'slug' => 'mixed-instructor-bundle',
            'description' => 'Shady + Mariam',
            'price' => 2500,
            'currency' => 'EGP',
            'is_published' => true,
        ]);
        $mixedPackage->created_by = $this->sa1->id;
        $mixedPackage->save();
        $mixedPackage->courses()->sync([$shadyExisting->id, $mariamCourse->id]);

        // MMM works on Shady-only package, REJECTED on Mixed package
        $this->actingAs($this->student)
            ->postJson(route('student.packages.promo-check', $shadyPackage), ['code' => 'MMM'])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount_amount' => 600, 'final_price' => 2400]);

        $this->actingAs($this->student)
            ->postJson(route('student.packages.promo-check', $mixedPackage), ['code' => 'MMM'])
            ->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => "This promo code is not valid for this instructor's course."]);

        // 5. Paid Consultation: valid ONLY when consultation instructor matches promo creator
        AcademySetting::setMany([
            'enable_consultations' => '1',
            'consultation_pricing_mode' => 'paid',
            'consultation_price' => '500',
            'consultation_currency' => 'EGP',
        ]);

        // MMM with Shady -> valid (20% off 500 = 100 -> 400 EGP)
        $this->actingAs($this->student)
            ->postJson(route('consultations.promo-check'), [
                'code' => 'MMM',
                'instructor_id' => $this->sa1->id,
            ])
            ->assertOk()
            ->assertJson(['valid' => true, 'discount_amount' => 100, 'final_price' => 400]);

        // MMM with Mariam or no instructor -> rejected
        $this->actingAs($this->student)
            ->postJson(route('consultations.promo-check'), [
                'code' => 'MMM',
                'instructor_id' => $this->teacher->id,
            ])
            ->assertStatus(422)
            ->assertJson(['valid' => false]);

        $this->actingAs($this->student)
            ->postJson(route('consultations.promo-check'), [
                'code' => 'MMM',
            ])
            ->assertStatus(422)
            ->assertJson(['valid' => false]);
    }

    public function test_promo_code_validation_calculates_percentage_and_fixed_discounts_and_rejects_invalid_states(): void
    {
        $pctPromo = new PromoCode([
            'code' => 'PCT25',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 25,
            'is_active' => true,
        ]);
        $pctPromo->created_by = $this->sa1->id;
        $pctPromo->save();

        $fixedPromo = new PromoCode([
            'code' => 'FIXED500',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 500,
            'is_active' => true,
        ]);
        $fixedPromo->created_by = $this->sa1->id;
        $fixedPromo->save();

        $expiredPromo = new PromoCode([
            'code' => 'OLD10',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'expires_at' => now()->subHour(),
            'is_active' => true,
        ]);
        $expiredPromo->created_by = $this->sa1->id;
        $expiredPromo->save();

        $maxedPromo = new PromoCode([
            'code' => 'ONCE1',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 100,
            'max_uses' => 1,
            'used_count' => 1,
            'is_active' => true,
        ]);
        $maxedPromo->created_by = $this->sa1->id;
        $maxedPromo->save();

        $disabledPromo = new PromoCode([
            'code' => 'OFF15',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'is_active' => false,
        ]);
        $disabledPromo->created_by = $this->sa1->id;
        $disabledPromo->save();

        // Percentage 25% of 1800 = 450 off -> 1350 final
        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $this->course), ['code' => 'pct25'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'code' => 'PCT25',
                'original_price' => 1800,
                'discount_amount' => 450,
                'final_price' => 1350,
            ]);

        // Fixed 500 off 1800 -> 1300 final
        $this->actingAs($this->student)
            ->postJson(route('student.courses.promo-check', $this->course), ['code' => 'FIXED500'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'discount_amount' => 500,
                'final_price' => 1300,
            ]);

        // Reject expired, maxed-out, disabled, nonexistent
        foreach (['OLD10', 'ONCE1', 'OFF15', 'NOPE999'] as $badCode) {
            $this->actingAs($this->student)
                ->postJson(route('student.courses.promo-check', $this->course), ['code' => $badCode])
                ->assertStatus(422)
                ->assertJson(['valid' => false]);
        }
    }

    public function test_payment_request_records_promo_snapshot_and_rejecting_releases_usage(): void
    {
        Storage::fake('local');

        $promo = new PromoCode([
            'code' => 'SAVE20',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $promo->created_by = $this->sa1->id;
        $promo->save();

        $this->actingAs($this->student)
            ->post(route('student.courses.subscribe.store', $this->course), [
                'phone' => '+201012345678',
                'promo_code' => 'save20',
                'screenshot' => $this->fakePng(),
            ])
            ->assertRedirect(route('student.payments.index'));

        $payment = PaymentRequest::query()->firstOrFail();
        $this->assertSame('SAVE20', $payment->promo_code_snapshot);
        $this->assertSame('1800.00', (string) $payment->original_price);
        $this->assertSame('360.00', (string) $payment->discount_amount);
        $this->assertSame('1440.00', (string) $payment->final_price);
        $this->assertSame(1, $promo->fresh()->used_count);

        // Rejecting the payment releases the promo code usage count back to 0
        $this->actingAs($this->sa1)
            ->post(route('admin.payments.reject', $payment), [
                'admin_note' => 'Blurred screenshot, please re-upload.',
            ])
            ->assertRedirect(route('admin.payments.index'));

        $this->assertSame(0, $promo->fresh()->used_count);
    }
}
