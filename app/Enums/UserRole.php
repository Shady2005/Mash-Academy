<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin1 = 'super_admin_1';
    case SuperAdmin2 = 'super_admin_2';
    case Teacher = 'teacher';
    case Support = 'support';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin1 => 'Super Admin 1 (Owner)',
            self::SuperAdmin2 => 'Super Admin 2 (Co-Owner)',
            self::Teacher => 'Teacher Admin',
            self::Support => 'Customer Support Admin',
            self::Student => 'Student',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::SuperAdmin1 => 'Super Admin 1',
            self::SuperAdmin2 => 'Super Admin 2',
            self::Teacher => 'Teacher',
            self::Support => 'Support',
            self::Student => 'Student',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SuperAdmin1 => 'bg-primary',
            self::SuperAdmin2 => 'bg-indigo',
            self::Teacher => 'bg-teal',
            self::Support => 'bg-amber',
            self::Student => 'bg-secondary',
        };
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this, [self::SuperAdmin1, self::SuperAdmin2], true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Student;
    }

    /**
     * @return list<self>
     */
    public static function staffRoles(): array
    {
        return [
            self::SuperAdmin1,
            self::SuperAdmin2,
            self::Teacher,
            self::Support,
        ];
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::staffRoles());
    }
}
