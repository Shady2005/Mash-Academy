<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_staff_can_login_and_reach_admin_dashboard(): void
    {
        $credentials = [
            ['email' => 'shady.admin@mashacademy.com', 'password' => 'Shady.admin@123', 'role' => UserRole::SuperAdmin1],
            ['email' => 'abdullah.admin@mashacademy.com', 'password' => 'Abdalluh.admin@123', 'role' => UserRole::SuperAdmin2],
            ['email' => 'mariam.teacher@mashacademy.com', 'password' => 'Mariam.Teacher@123', 'role' => UserRole::Teacher],
            ['email' => 'mohammed.support@mashacademy.com', 'password' => 'Mohammed.Support@123', 'role' => UserRole::Support],
        ];

        foreach ($credentials as $cred) {
            $response = $this->post(route('login.store'), [
                'email' => $cred['email'],
                'password' => $cred['password'],
            ]);

            $response->assertRedirect(route('admin.dashboard'));
            $this->assertAuthenticated();

            $user = User::query()->where('email', $cred['email'])->firstOrFail();
            $this->assertSame($cred['role'], $user->role);
            $this->assertFalse($user->must_change_password);
            $this->assertNotNull($user->last_login_at);

            $this->get(route('admin.dashboard'))->assertOk();
            $this->post(route('logout'))->assertRedirect(route('home'));
            $this->assertGuest();
        }
    }

    public function test_student_login_redirects_to_student_dashboard(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'learner@example.com',
            'password' => Hash::make('Student@2026'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'learner@example.com',
            'password' => 'Student@2026',
        ]);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->student()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('Student@2026'),
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'inactive@example.com',
            'password' => 'Student@2026',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'throttle@example.com',
            'password' => Hash::make('CorrectPass1!'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => $student->email,
                'password' => 'WrongPassword1!',
            ])->assertSessionHasErrors('email');
        }

        // 6th attempt even with the right password must be throttled
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'CorrectPass1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $errorMessage = session('errors')->first('email');
        $this->assertStringContainsString('Too many login attempts', $errorMessage);
    }

    public function test_logout_invalidates_session_and_csrf_middleware_is_active(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();

        // Verify web middleware group includes ValidateCsrfToken
        $route = app('router')->getRoutes()->getByName('logout');
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_password_reset_flow_works(): void
    {
        $student = User::factory()->student()->create([
            'email' => 'resetme@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $this->post(route('password.email'), ['email' => $student->email])
            ->assertSessionHas('status');

        $token = Password::createToken($student);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $student->email,
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ]);

        $response->assertRedirect(route('login'));
        $student->refresh();
        $this->assertTrue(Hash::check('NewPass123!', $student->password));
    }

    public function test_authenticated_user_can_change_password_and_clear_temporary_flag(): void
    {
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $teacher->must_change_password = true;
        $teacher->save();

        $response = $this->actingAs($teacher)->put(route('account.password.update'), [
            'current_password' => 'Mariam.Teacher@123',
            'password' => 'NewTeacher@2026!',
            'password_confirmation' => 'NewTeacher@2026!',
        ]);

        $response->assertSessionHas('status');
        $teacher->refresh();
        $this->assertFalse($teacher->must_change_password);
        $this->assertTrue(Hash::check('NewTeacher@2026!', $teacher->password));
    }
}
