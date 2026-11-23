<?php

namespace Tests\Feature\Modules;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\Enrollment;
use App\Models\MarketingAccount;
use App\Models\PaymentRequest;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PackagesUsersMarketingTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function fakePng(string $name = 'img.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::TINY_PNG_B64));
    }

    /**
     * 1. Course Packages / Bundles: Admin CRUD, Public Display, Promo Checkout,
     *    and Approval Auto-Unlocking All Included Courses Directly in Student My Courses.
     */
    public function test_package_checkout_and_approval_unlocks_all_included_courses_in_my_courses(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $student = User::factory()->student()->create();

        // Only Super Admins can access /admin/packages
        $this->actingAs($teacher)->get(route('admin.packages.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.packages.index'))->assertForbidden();

        $courses = Course::query()->published()->take(2)->get();
        $this->assertCount(2, $courses);

        // Super Admin 1 creates a bilingual package bundling 2 courses with custom InstaPay QR
        $this->actingAs($sa1)
            ->post(route('admin.packages.store'), [
                'title' => 'Full-Stack Mastery Bundle',
                'title_ar' => 'باكيدج احتراف البرمجة الشاملة',
                'short_description' => 'Two core courses bundled at a huge discount.',
                'description' => 'Complete bundle containing two full Mash Academy courses with projects and certificates.',
                'price' => 2000,
                'currency' => 'EGP',
                'is_published' => 1,
                'course_ids' => $courses->pluck('id')->all(),
                'instapay_qr' => $this->fakePng('bundle-qr.png'),
                'instapay_note' => 'bundle@instapay',
            ])
            ->assertRedirect(route('admin.packages.index'));

        $package = CoursePackage::query()->where('title', 'Full-Stack Mastery Bundle')->firstOrFail();
        $this->assertCount(2, $package->courses);
        $this->assertNotNull($package->instapay_qr_path);
        Storage::disk('public')->assertExists($package->instapay_qr_path);

        // Public Home, Public Courses, Package Details & Student Browse Courses display the package
        $this->get(route('home'))->assertOk()->assertSee('Full-Stack Mastery Bundle');
        $this->get(route('courses.index'))->assertOk()->assertSee('Full-Stack Mastery Bundle');
        $this->get(route('packages.show', $package))->assertOk()->assertSee($courses[0]->title)->assertSee($courses[1]->title);
        $this->actingAs($student)->get(route('student.courses.index'))->assertOk()->assertSee('Full-Stack Mastery Bundle');

        // Promo code works on Package checkout
        $promo = new PromoCode([
            'code' => 'BUNDLE20',
            'discount_type' => PromoCode::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'is_active' => true,
        ]);
        $promo->created_by = $sa1->id;
        $promo->save();

        $this->actingAs($student)
            ->get(route('student.packages.subscribe', $package))
            ->assertOk()
            ->assertSee($package->instapay_qr_path)
            ->assertSee('bundle@instapay');

        $this->actingAs($student)
            ->postJson(route('student.packages.promo-check', $package), ['code' => 'BUNDLE20'])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'original_price' => 2000,
                'discount_amount' => 400,
                'final_price' => 1600,
            ]);

        // Student submits package payment proof -> pending in payment_requests
        $this->actingAs($student)
            ->post(route('student.packages.subscribe.store', $package), [
                'phone' => '+201012345678',
                'promo_code' => 'BUNDLE20',
                'screenshot' => $this->fakePng('receipt.png'),
            ])
            ->assertRedirect(route('student.payments.index'));

        $payment = PaymentRequest::query()
            ->where('user_id', $student->id)
            ->where('course_package_id', $package->id)
            ->firstOrFail();

        $this->assertSame(PaymentRequest::STATUS_PENDING, $payment->status);
        $this->assertEquals(1600.00, (float) $payment->final_price);
        $this->assertNull($payment->course_id);

        // Super Admin 2 approves package payment for 3 months -> unlocks BOTH courses automatically!
        $this->actingAs($sa2)
            ->post(route('admin.payments.approve', $payment), [
                'enroll_now' => 1,
                'duration_value' => 3,
                'duration_unit' => Enrollment::UNIT_MONTHS,
                'admin_note' => 'Verified bundle transfer',
            ])
            ->assertRedirect(route('admin.payments.index'));

        $enrollments = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereIn('course_id', $courses->pluck('id'))
            ->get();

        $this->assertCount(2, $enrollments);
        $this->assertTrue($enrollments->every(fn (Enrollment $e) => $e->isCurrentlyActive()));
        $this->assertSame(
            $enrollments[0]->expires_at->toIso8601String(),
            $enrollments[1]->expires_at->toIso8601String()
        );

        // Student opens regular My Courses view -> BOTH courses appear unlocked directly!
        $this->actingAs($student)
            ->get(route('student.my-courses.index'))
            ->assertOk()
            ->assertSee($courses[0]->title)
            ->assertSee($courses[1]->title);

        $this->actingAs($student)->get(route('student.courses.learn', $courses[0]))->assertOk();
        $this->actingAs($student)->get(route('student.courses.learn', $courses[1]))->assertOk();

        // Manual package enrollment from /admin/enrollments also unlocks/extends all courses in package
        $student2 = User::factory()->student()->create();
        $this->actingAs($sa1)
            ->post(route('admin.enrollments.store'), [
                'user_id' => $student2->id,
                'course_package_id' => $package->id,
                'duration_value' => 6,
                'duration_unit' => Enrollment::UNIT_MONTHS,
            ])
            ->assertRedirect(route('admin.enrollments.index'));

        $this->assertSame(
            2,
            Enrollment::query()->where('user_id', $student2->id)->whereIn('course_id', $courses->pluck('id'))->count()
        );
    }

    /**
     * 2. Dashboard Users Management: All 4 Staff Access, Teacher/Support Locked to Creating Students Only,
     *    and Account Blocking Middleware + Login Enforcement.
     */
    public function test_dashboard_users_creation_role_enforcement_and_blocking_middleware(): void
    {
        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();

        // All 4 staff roles can open /admin/users
        foreach ([$sa1, $sa2, $teacher, $support] as $staff) {
            $this->actingAs($staff)->get(route('admin.users.index'))->assertOk();
        }

        // Teacher can create a student account
        $this->actingAs($teacher)
            ->post(route('admin.users.store'), [
                'name' => 'Teacher Added Student',
                'email' => 'teacher.student@example.com',
                'phone' => '+201011112222',
                'password' => 'StudentPass123',
                'role' => 'student',
            ])
            ->assertRedirect(route('admin.users.index'));

        $createdByTeacher = User::query()->where('email', 'teacher.student@example.com')->firstOrFail();
        $this->assertSame(UserRole::Student, $createdByTeacher->role);

        // Support can create a student account
        $this->actingAs($support)
            ->post(route('admin.users.store'), [
                'name' => 'Support Added Student',
                'email' => 'support.student@example.com',
                'password' => 'StudentPass123',
                'role' => 'student',
            ])
            ->assertRedirect(route('admin.users.index'));

        $createdBySupport = User::query()->where('email', 'support.student@example.com')->firstOrFail();
        $this->assertSame(UserRole::Student, $createdBySupport->role);

        // Teacher & Support CANNOT create staff/admin accounts even via request tampering
        foreach ([$teacher, $support] as $nonSuper) {
            $this->actingAs($nonSuper)
                ->from(route('admin.users.index'))
                ->post(route('admin.users.store'), [
                    'name' => 'Escalated Admin',
                    'email' => 'escalated-'.$nonSuper->id.'@example.com',
                    'password' => 'SecretPass123',
                    'role' => 'super_admin_1',
                ])
                ->assertSessionHasErrors('role');

            $this->assertDatabaseMissing('users', ['email' => 'escalated-'.$nonSuper->id.'@example.com']);
        }

        // Teacher & Support CANNOT block Super Admins or Staff accounts
        $this->actingAs($teacher)->post(route('admin.users.block', $sa1))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.users.block', $support))->assertForbidden();
        $this->actingAs($support)->post(route('admin.users.block', $sa2))->assertForbidden();
        $this->actingAs($support)->post(route('admin.users.block', $teacher))->assertForbidden();

        // Super Admins can NEVER be blocked by anyone (even another Super Admin)
        $this->actingAs($sa2)->post(route('admin.users.block', $sa1))->assertForbidden();

        // Support blocks the student account
        $student = User::factory()->student()->create([
            'email' => 'violator@example.com',
            'password' => Hash::make('Violator123'),
        ]);

        $this->actingAs($support)
            ->post(route('admin.users.block', $student), [
                'blocked_reason' => 'Shared paid course videos externally.',
            ])
            ->assertRedirect(route('admin.users.index'));

        $student->refresh();
        $this->assertTrue($student->isBlocked());
        $this->assertSame('Shared paid course videos externally.', $student->blocked_reason);

        // Blocked student trying to log in is rejected with the exact policy message
        auth()->logout();
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'violator@example.com',
                'password' => 'Violator123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => EnsureUserIsActive::BLOCKED_MESSAGE,
            ]);
        $this->assertGuest();

        // Currently logged-in student who gets blocked is IMMEDIATELY logged out on ANY route (public or student)
        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => EnsureUserIsActive::BLOCKED_MESSAGE,
            ]);
        $this->assertGuest();

        $this->actingAs($student)
            ->get(route('home'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => EnsureUserIsActive::BLOCKED_MESSAGE,
            ]);
        $this->assertGuest();

        // Teacher can unblock the student, and student can log in again cleanly
        $this->actingAs($teacher)
            ->post(route('admin.users.unblock', $student))
            ->assertRedirect(route('admin.users.index'));

        $student->refresh();
        $this->assertFalse($student->isBlocked());

        auth()->logout();
        $this->post(route('login.store'), [
            'email' => 'violator@example.com',
            'password' => 'Violator123',
        ])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    /**
     * 3. Marketing & Social Media Analytics: Multi-Account Connection, Metrics Update,
     *    Live YouTube Data API v3 Sync, Platform Filtering, and Role Access.
     */
    public function test_marketing_multi_account_analytics_and_youtube_sync(): void
    {
        $sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();

        // Connect TikTok and YouTube accounts as Super Admin 1
        $this->actingAs($sa1)
            ->post(route('admin.marketing.store'), [
                'platform' => MarketingAccount::PLATFORM_TIKTOK,
                'account_name' => 'Mash Academy TikTok',
                'profile_url' => 'https://www.tiktok.com/@mashacademy',
                'followers_count' => 25000,
                'total_views' => 450000,
                'total_likes' => 62000,
            ])
            ->assertRedirect(route('admin.marketing.index', ['platform' => 'tiktok']));

        $this->actingAs($sa1)
            ->post(route('admin.marketing.store'), [
                'platform' => MarketingAccount::PLATFORM_YOUTUBE,
                'account_name' => 'Mash Academy YouTube',
                'profile_url' => 'https://www.youtube.com/@mashacademy',
                'channel_id' => 'UC1234567890ABCDEF',
                'api_key' => 'test-yt-api-key',
            ])
            ->assertRedirect(route('admin.marketing.index', ['platform' => 'youtube']));

        $tiktok = MarketingAccount::query()->where('platform', MarketingAccount::PLATFORM_TIKTOK)->firstOrFail();
        $youtube = MarketingAccount::query()->where('platform', MarketingAccount::PLATFORM_YOUTUBE)->firstOrFail();

        // Update TikTok metrics + Top Performing Video
        $this->actingAs($sa1)
            ->put(route('admin.marketing.metrics.update', $tiktok), [
                'account_name' => 'Mash Academy TikTok',
                'profile_url' => 'https://www.tiktok.com/@mashacademy',
                'followers_count' => 30000,
                'total_views' => 500000,
                'total_likes' => 75000,
                'top_video_title' => 'How to Master Laravel in 60s',
                'top_video_url' => 'https://www.tiktok.com/@mashacademy/video/999',
                'top_video_views' => 180000,
                'top_video_likes' => 28000,
            ])
            ->assertRedirect(route('admin.marketing.index', ['platform' => 'tiktok']));

        // Mock YouTube Data API v3 responses and test live Sync
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => 'UC1234567890ABCDEF',
                    'statistics' => [
                        'subscriberCount' => '18500',
                        'viewCount' => '920000',
                    ],
                ]],
            ], 200),
            'https://www.googleapis.com/youtube/v3/search*' => Http::response([
                'items' => [[
                    'id' => ['videoId' => 'dQw4w9WgXcQ'],
                    'snippet' => ['title' => 'Complete Full-Stack Roadmap 2026'],
                ]],
            ], 200),
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [[
                    'statistics' => [
                        'viewCount' => '210000',
                        'likeCount' => '14500',
                    ],
                ]],
            ], 200),
        ]);

        $this->actingAs($sa1)
            ->post(route('admin.marketing.sync', $youtube))
            ->assertRedirect(route('admin.marketing.index', ['platform' => 'youtube']));

        $youtube->refresh();
        $this->assertSame(18500, $youtube->followers_count);
        $this->assertSame(920000, $youtube->total_views);
        $this->assertSame('Complete Full-Stack Roadmap 2026', $youtube->top_video_title);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $youtube->top_video_url);
        $this->assertSame(210000, $youtube->top_video_views);
        $this->assertSame(14500, $youtube->top_video_likes);
        $this->assertSame('youtube_api', $youtube->sync_source);

        // Platform filter tabs & KPIs render accurately
        $this->actingAs($sa1)
            ->get(route('admin.marketing.index'))
            ->assertOk()
            ->assertSee('Mash Academy TikTok')
            ->assertSee('Mash Academy YouTube')
            ->assertSee('How to Master Laravel in 60s')
            ->assertSee('Complete Full-Stack Roadmap 2026');

        $this->actingAs($sa1)
            ->get(route('admin.marketing.index', ['platform' => 'youtube']))
            ->assertOk()
            ->assertSee('Mash Academy YouTube')
            ->assertDontSee('Mash Academy TikTok');

        // Support & Teacher can view Marketing (read-only) but cannot mutate
        foreach ([$teacher, $support] as $viewer) {
            $this->actingAs($viewer)->get(route('admin.marketing.index'))->assertOk();
            $this->actingAs($viewer)->post(route('admin.marketing.store'), [])->assertForbidden();
            $this->actingAs($viewer)->delete(route('admin.marketing.destroy', $tiktok))->assertForbidden();
        }
    }
}
