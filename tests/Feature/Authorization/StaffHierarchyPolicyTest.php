<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffHierarchyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $sa1;

    private User $sa2;

    private User $teacher;

    private User $support;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->sa1 = User::query()->where('role', UserRole::SuperAdmin1->value)->firstOrFail();
        $this->sa2 = User::query()->where('role', UserRole::SuperAdmin2->value)->firstOrFail();
        $this->teacher = User::query()->where('role', UserRole::Teacher->value)->firstOrFail();
        $this->support = User::query()->where('role', UserRole::Support->value)->firstOrFail();
        $this->student = User::factory()->student()->create();
    }

    public function test_super_admins_cannot_delete_each_other_or_themselves(): void
    {
        $this->assertFalse(Gate::forUser($this->sa1)->allows('delete', $this->sa2));
        $this->assertFalse(Gate::forUser($this->sa2)->allows('delete', $this->sa1));
        $this->assertFalse(Gate::forUser($this->sa1)->allows('delete', $this->sa1));
        $this->assertFalse(Gate::forUser($this->sa2)->allows('delete', $this->sa2));

        $this->actingAs($this->sa1)
            ->delete(route('admin.staff.destroy', $this->sa2->id))
            ->assertForbidden();

        $this->actingAs($this->sa2)
            ->delete(route('admin.staff.destroy', $this->sa1->id))
            ->assertForbidden();

        $this->actingAs($this->sa1)
            ->delete(route('admin.staff.destroy', $this->sa1->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->sa1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $this->sa2->id, 'deleted_at' => null]);
    }

    public function test_super_admin_1_can_delete_teacher_and_support(): void
    {
        $this->assertTrue(Gate::forUser($this->sa1)->allows('delete', $this->teacher));
        $this->assertTrue(Gate::forUser($this->sa1)->allows('delete', $this->support));

        $this->actingAs($this->sa1)
            ->delete(route('admin.staff.destroy', $this->teacher->id))
            ->assertRedirect(route('admin.staff.index'));

        $this->teacher->refresh();
        $this->assertTrue($this->teacher->trashed());
        $this->assertFalse($this->teacher->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $this->sa1->id,
            'action' => 'staff.deleted',
            'subject_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->sa1)
            ->delete(route('admin.staff.destroy', $this->support->id))
            ->assertRedirect(route('admin.staff.index'));

        $this->support->refresh();
        $this->assertTrue($this->support->trashed());
        $this->assertFalse($this->support->is_active);
    }

    public function test_super_admin_2_can_delete_teacher_and_support(): void
    {
        $this->assertTrue(Gate::forUser($this->sa2)->allows('delete', $this->teacher));
        $this->assertTrue(Gate::forUser($this->sa2)->allows('delete', $this->support));

        $this->actingAs($this->sa2)
            ->delete(route('admin.staff.destroy', $this->support->id))
            ->assertRedirect(route('admin.staff.index'));

        $this->support->refresh();
        $this->assertTrue($this->support->trashed());
    }

    public function test_teacher_support_and_student_cannot_delete_anyone(): void
    {
        $nonSuperAdmins = [$this->teacher, $this->support, $this->student];
        $targets = [$this->sa1, $this->sa2, $this->teacher, $this->support, $this->student];

        foreach ($nonSuperAdmins as $actor) {
            foreach ($targets as $target) {
                $this->assertFalse(Gate::forUser($actor)->allows('delete', $target));
            }

            $this->actingAs($actor)
                ->delete(route('admin.staff.destroy', $this->support->id))
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_reset_teacher_password_but_not_other_super_admin(): void
    {
        $this->actingAs($this->sa1)
            ->put(route('admin.staff.reset-password', $this->teacher->id), [
                'password' => 'ResetTeacher@2026',
                'password_confirmation' => 'ResetTeacher@2026',
            ])
            ->assertRedirect(route('admin.staff.index'));

        $this->teacher->refresh();
        $this->assertTrue(Hash::check('ResetTeacher@2026', $this->teacher->password));
        $this->assertTrue($this->teacher->must_change_password);

        // Cannot reset SA2's password
        $this->actingAs($this->sa1)
            ->put(route('admin.staff.reset-password', $this->sa2->id), [
                'password' => 'ResetAdmin@2026',
                'password_confirmation' => 'ResetAdmin@2026',
            ])
            ->assertForbidden();
    }

    public function test_exactly_four_staff_accounts_guard_enforced_at_application_and_database_layers(): void
    {
        $this->assertSame(
            4,
            User::withTrashed()->whereIn('role', UserRole::staffValues())->count()
        );

        // Application-level guard blocks creating a second Teacher
        $this->expectException(DomainException::class);

        $duplicateTeacher = new User();
        $duplicateTeacher->name = 'Extra Teacher';
        $duplicateTeacher->email = 'extra-teacher@mashacademy.com';
        $duplicateTeacher->password = Hash::make('Password123!');
        $duplicateTeacher->role = UserRole::Teacher;
        $duplicateTeacher->save();
    }

    public function test_database_unique_constraint_blocks_raw_duplicate_staff_insert(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Rogue Super Admin',
            'email' => 'rogue@mashacademy.com',
            'password' => Hash::make('Password123!'),
            'role' => UserRole::SuperAdmin1->value,
            'staff_role_key' => UserRole::SuperAdmin1->value,
            'is_active' => true,
            'must_change_password' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
