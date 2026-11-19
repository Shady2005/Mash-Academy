<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Policy;
use App\Models\PolicyAcceptance;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationPolicyGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_register_page_displays_active_policy(): void
    {
        $activePolicy = Policy::active();

        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee($activePolicy->title);
        $response->assertSee('Version '.$activePolicy->version);
    }

    public function test_registration_fails_without_policy_checkbox(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test Student',
            'email' => 'student1@example.com',
            'phone' => '+201012345678',
            'password' => 'Student@2026',
            'password_confirmation' => 'Student@2026',
            // agree_policy omitted
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('agree_policy');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'student1@example.com']);
        $this->assertSame(0, PolicyAcceptance::count());
    }

    public function test_registration_fails_when_policy_checkbox_is_false(): void
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Test Student',
            'email' => 'student2@example.com',
            'password' => 'Student@2026',
            'password_confirmation' => 'Student@2026',
            'agree_policy' => '0',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('agree_policy');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'student2@example.com']);
    }

    public function test_registration_succeeds_with_policy_checkbox_and_records_acceptance(): void
    {
        $activePolicy = Policy::active();

        $response = $this->post(route('register.store'), [
            'name' => 'Nour Hassan',
            'email' => 'nour@example.com',
            'phone' => '+201099887766',
            'password' => 'Student@2026',
            'password_confirmation' => 'Student@2026',
            'agree_policy' => '1',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated();

        $student = User::query()->where('email', 'nour@example.com')->firstOrFail();

        $this->assertSame(UserRole::Student, $student->role);
        $this->assertNull($student->staff_role_key);
        $this->assertTrue($student->is_active);
        $this->assertNotSame('Student@2026', $student->password);
        $this->assertTrue(Hash::check('Student@2026', $student->password));

        $this->assertDatabaseHas('policy_acceptances', [
            'user_id' => $student->id,
            'policy_id' => $activePolicy->id,
            'policy_version' => $activePolicy->version,
        ]);

        $acceptance = PolicyAcceptance::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertNotNull($acceptance->accepted_at);
        $this->assertNotNull($acceptance->ip_address);

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $student->id,
            'action' => 'student.registered',
        ]);
    }

    public function test_student_cannot_escalate_role_via_request_tampering(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Tamper Student',
            'email' => 'tamper@example.com',
            'password' => 'Student@2026',
            'password_confirmation' => 'Student@2026',
            'agree_policy' => '1',
            'role' => 'super_admin_1',
            'staff_role_key' => 'super_admin_1',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('student.dashboard'));

        $student = User::query()->where('email', 'tamper@example.com')->firstOrFail();
        $this->assertSame(UserRole::Student, $student->role);
        $this->assertNull($student->staff_role_key);
        $this->assertTrue($student->is_active);

        // Also verify direct mass-assignment protection on User::fill()
        $student->fill([
            'name' => 'Updated Name',
            'role' => UserRole::SuperAdmin1,
            'is_active' => false,
        ]);
        $student->save();
        $student->refresh();

        $this->assertSame('Updated Name', $student->name);
        $this->assertSame(UserRole::Student, $student->role);
        $this->assertTrue($student->is_active);
    }
}
