<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\Policy;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->superAdmin = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
    }

    public function test_super_admin_can_update_academy_info_and_header_updates_site_wide(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(route('admin.settings.update'), [
            'academy_name' => 'Mash Academy Egypt',
            'tagline' => 'Practical mastery for modern students.',
            'about' => 'Updated about description.',
            'vision' => 'Updated academy vision.',
            'contact_email' => 'support@mashacademy.eg',
            'contact_phone' => '+20 122 333 4444',
        ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $this->assertSame('Mash Academy Egypt', AcademySetting::getValue('academy_name'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Mash Academy Egypt')
            ->assertSee('Practical mastery for modern students.');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $this->superAdmin->id,
            'action' => 'settings.academy_updated',
        ]);
    }

    public function test_super_admin_can_upload_and_remove_logo(): void
    {
        Storage::fake('public');

        // Minimal valid 1x1 PNG binary (does not depend on PHP gd extension)
        $validPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $logoFile = UploadedFile::fake()->createWithContent('mash-logo.png', $validPng);

        $response = $this->actingAs($this->superAdmin)->put(route('admin.settings.update'), [
            'academy_name' => 'Mash Academy',
            'logo' => $logoFile,
        ]);

        $response->assertRedirect(route('admin.settings.edit'));

        $storedPath = AcademySetting::getValue('logo_path');
        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($storedPath)
            ->assertDontSee('class="ma-brand-emblem"', false);

        // Auth pages (login & register) render uploaded logo instead of static MA badge for guests
        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($storedPath)
            ->assertDontSee('class="ma-brand-emblem"', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee($storedPath)
            ->assertDontSee('class="ma-brand-emblem"', false);

        // Admin dashboard sidebar renders uploaded logo
        $this->actingAs($this->superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee($storedPath);

        // Certificate view & downloadable HTML render uploaded logo
        $course = Course::query()->published()->first();
        if ($course) {
            $this->actingAs($this->superAdmin)
                ->get(route('student.certificates.show', $course))
                ->assertOk()
                ->assertSee($storedPath);

            $this->actingAs($this->superAdmin)
                ->get(route('student.certificates.download', $course))
                ->assertOk()
                ->assertSee('data:image/png;base64,', false);
        }

        // Remove logo -> falls back cleanly to MA badge
        $this->actingAs($this->superAdmin)
            ->delete(route('admin.settings.logo.destroy'))
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertNull(AcademySetting::getValue('logo_path'));
        Storage::disk('public')->assertMissing($storedPath);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="ma-brand-emblem"', false);
    }

    public function test_super_admin_can_upload_svg_logo_and_malicious_svg_is_rejected(): void
    {
        Storage::fake('public');

        $cleanSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#0a192f"/></svg>';
        $svgFile = UploadedFile::fake()->createWithContent('mash-logo.svg', $cleanSvg);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.settings.update'), [
                'academy_name' => 'Mash Academy',
                'logo' => $svgFile,
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $storedPath = AcademySetting::getValue('logo_path');
        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);

        // Malicious SVG containing <script> must be rejected
        $xssSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $badSvgFile = UploadedFile::fake()->createWithContent('evil.svg', $xssSvg);

        $this->actingAs($this->superAdmin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'academy_name' => 'Mash Academy',
                'logo' => $badSvgFile,
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_non_image_logo_upload_is_rejected(): void
    {
        Storage::fake('public');

        $invalidFile = UploadedFile::fake()->create('script.php', 10, 'application/x-httpd-php');

        $this->actingAs($this->superAdmin)
            ->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'academy_name' => 'Mash Academy',
                'logo' => $invalidFile,
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_editing_policy_creates_new_version_preserves_history_and_updates_register_page(): void
    {
        $v1 = Policy::active();
        $this->assertSame(1, $v1->version);

        $response = $this->actingAs($this->superAdmin)->post(route('admin.settings.policy.store'), [
            'title' => 'Mash Academy Policy Updated',
            'body' => 'Updated terms for version 2: all students must follow the revised sequential study policy.',
        ]);

        $response->assertRedirect(route('admin.settings.policy.edit'));

        $v1->refresh();
        $this->assertFalse($v1->is_active);

        $v2 = Policy::active();
        $this->assertSame(2, $v2->version);
        $this->assertTrue($v2->is_active);
        $this->assertSame(2, Policy::count());

        // Visit registration page as a guest student
        auth()->logout();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Mash Academy Policy Updated')
            ->assertSee('Version 2')
            ->assertSee('revised sequential study policy');

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $this->superAdmin->id,
            'action' => 'policy.version_published',
            'subject_id' => $v2->id,
        ]);
    }
}
