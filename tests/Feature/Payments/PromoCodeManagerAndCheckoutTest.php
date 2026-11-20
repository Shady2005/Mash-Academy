<?php

namespace Tests\Feature\Payments;

use App\Enums\UserRole;
use App\Models\Course;
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
        $this->course = Course::query()->published()->firstOrFail(); // 1800 EGP
    }

    private function fakePng(string $name = 'proof.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, $png);
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

    public function test_teacher_support_and_student_cannot_manage_promo_codes(): void
    {
        foreach ([$this->teacher, $this->support, $this->student] as $actor) {
            $this->actingAs($actor)->get(route('admin.promo-codes.index'))->assertForbidden();
            $this->actingAs($actor)->post(route('admin.promo-codes.store'), [
                'code' => 'FREE99',
                'discount_type' => PromoCode::TYPE_PERCENTAGE,
                'discount_value' => 99,
            ])->assertForbidden();
        }
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
