<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_super_admins_access_all_admin_routes_and_student_portal_unrestricted(): void
    {
        foreach ([UserRole::SuperAdmin1, UserRole::SuperAdmin2] as $role) {
            $admin = User::query()->where('role', $role->value)->firstOrFail();

            $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
            $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();
            $this->actingAs($admin)->get(route('admin.settings.policy.edit'))->assertOk();
            $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
            $this->actingAs($admin)->get(route('admin.activity-logs.index'))->assertOk();
            $this->actingAs($admin)->get(route('admin.instructor-profile.edit'))->assertOk();

            // Pre-sign-off enhancement: Super Admins 1 & 2 have unrestricted access to the Student Portal
            $this->actingAs($admin)->get(route('student.dashboard'))->assertOk();
        }
    }

    public function test_teacher_accesses_only_admin_shell_and_is_blocked_from_super_admin_and_student_routes(): void
    {
        $teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($teacher)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($teacher)->put(route('admin.settings.update'), ['academy_name' => 'Hacked'])->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.settings.policy.edit'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.settings.policy.store'), ['title' => 'X', 'body' => str_repeat('a', 30)])->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.activity-logs.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_support_accesses_only_admin_shell_and_is_blocked_from_super_admin_and_student_routes(): void
    {
        $support = User::query()->where('role', UserRole::Support->value)->firstOrFail();

        $this->actingAs($support)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($support)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($support)->put(route('admin.settings.update'), ['academy_name' => 'Hacked'])->assertForbidden();
        $this->actingAs($support)->get(route('admin.settings.policy.edit'))->assertForbidden();
        $this->actingAs($support)->post(route('admin.settings.policy.store'), ['title' => 'X', 'body' => str_repeat('a', 30)])->assertForbidden();
        $this->actingAs($support)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.activity-logs.index'))->assertForbidden();
        $this->actingAs($support)->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_student_accesses_student_portal_and_is_blocked_from_all_admin_routes(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.settings.policy.edit'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.activity-logs.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_for_protected_routes(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }
}
