<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffUsersSeeder extends Seeder
{
    public function run(): void
    {
        $staffAccounts = [
            [
                'name' => 'Super Admin 1 (Owner)',
                'email' => 'superadmin1@mashacademy.com',
                'phone' => '+201000000001',
                'password' => 'Admin1@Mash2026!',
                'role' => UserRole::SuperAdmin1,
            ],
            [
                'name' => 'Super Admin 2 (Co-Owner)',
                'email' => 'superadmin2@mashacademy.com',
                'phone' => '+201000000002',
                'password' => 'Admin2@Mash2026!',
                'role' => UserRole::SuperAdmin2,
            ],
            [
                'name' => 'Teacher Admin',
                'email' => 'teacher@mashacademy.com',
                'phone' => '+201000000003',
                'password' => 'Teacher@Mash2026!',
                'role' => UserRole::Teacher,
            ],
            [
                'name' => 'Customer Support Admin',
                'email' => 'support@mashacademy.com',
                'phone' => '+201000000004',
                'password' => 'Support@Mash2026!',
                'role' => UserRole::Support,
            ],
        ];

        foreach ($staffAccounts as $account) {
            $existing = User::withTrashed()->where('role', $account['role']->value)->first();

            if ($existing) {
                continue;
            }

            $user = new User();
            $user->name = $account['name'];
            $user->email = $account['email'];
            $user->phone = $account['phone'];
            $user->password = Hash::make($account['password']);
            $user->role = $account['role'];
            $user->is_active = true;
            $user->must_change_password = true;
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
