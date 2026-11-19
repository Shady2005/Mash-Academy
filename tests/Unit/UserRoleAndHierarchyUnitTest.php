<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleAndHierarchyUnitTest extends TestCase
{
    public function test_staff_roles_list_contains_exactly_four_roles(): void
    {
        $staffRoles = UserRole::staffRoles();

        $this->assertCount(4, $staffRoles);
        $this->assertSame([
            'super_admin_1',
            'super_admin_2',
            'teacher',
            'support',
        ], UserRole::staffValues());
    }

    public function test_super_admin_and_staff_helpers(): void
    {
        $this->assertTrue(UserRole::SuperAdmin1->isSuperAdmin());
        $this->assertTrue(UserRole::SuperAdmin2->isSuperAdmin());
        $this->assertFalse(UserRole::Teacher->isSuperAdmin());
        $this->assertFalse(UserRole::Support->isSuperAdmin());
        $this->assertFalse(UserRole::Student->isSuperAdmin());

        $this->assertTrue(UserRole::Teacher->isStaff());
        $this->assertTrue(UserRole::Support->isStaff());
        $this->assertFalse(UserRole::Student->isStaff());
    }
}
