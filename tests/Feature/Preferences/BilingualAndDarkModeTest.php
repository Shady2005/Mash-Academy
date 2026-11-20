<?php

namespace Tests\Feature\Preferences;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BilingualAndDarkModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_layout_is_english_ltr_and_light_mode(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('dir="ltr"', false);
        $response->assertSee('data-bs-theme="light"', false);
        $response->assertSee('bootstrap.min.css', false);
    }

    public function test_language_switcher_flips_to_arabic_rtl_and_persists(): void
    {
        $this->from(route('home'))
            ->post(route('preferences.locale'), ['locale' => 'ar'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('locale', 'ar');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('lang="ar"', false);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('bootstrap.rtl.min.css', false);
        $response->assertSee('أكاديمية ماش');
    }

    public function test_dark_mode_toggle_persists_in_session_and_renders_dark_theme(): void
    {
        $this->from(route('home'))
            ->post(route('preferences.theme'), ['theme' => 'dark'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('theme', 'dark');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-bs-theme="dark"', false)
            ->assertSee('<meta name="color-scheme" content="dark">', false);

        // Toggle back to light
        $this->from(route('home'))
            ->post(route('preferences.theme'), ['theme' => 'light'])
            ->assertSessionHas('theme', 'light');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-bs-theme="light"', false);
    }

    public function test_super_admin_can_edit_bilingual_vision_and_home_page_reflects_both_languages(): void
    {
        $superAdmin = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();

        $this->actingAs($superAdmin)
            ->put(route('admin.settings.update'), [
                'academy_name' => 'Mash Academy',
                'academy_name_ar' => 'أكاديمية ماش التعليمية',
                'vision' => 'Our bold new English academy vision for 2027.',
                'vision_ar' => 'رؤيتنا العربية الجديدة لعام 2027 للارتقاء بالتعليم.',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        // Verify English Home Page
        $this->withSession(['locale' => 'en'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Our bold new English academy vision for 2027.');

        // Verify Arabic Home Page
        $this->withSession(['locale' => 'ar'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('أكاديمية ماش التعليمية')
            ->assertSee('رؤيتنا العربية الجديدة لعام 2027 للارتقاء بالتعليم.');
    }
}
