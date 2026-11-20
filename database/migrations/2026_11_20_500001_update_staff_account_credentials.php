<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Safely update the 4 existing fixed staff accounts' emails & passwords in-place,
     * preserving their IDs, roles, permissions, and all existing courses/students/enrollments.
     */
    public function up(): void
    {
        $updates = [
            'super_admin_1' => [
                'email' => 'shady.admin@mashacademy.com',
                'password' => Hash::make('Shady.admin@123'),
                'must_change_password' => false,
            ],
            'super_admin_2' => [
                'email' => 'abdullah.admin@mashacademy.com',
                'password' => Hash::make('Abdalluh.admin@123'),
                'must_change_password' => false,
            ],
            'teacher' => [
                'email' => 'mariam.teacher@mashacademy.com',
                'password' => Hash::make('Mariam.Teacher@123'),
                'must_change_password' => false,
            ],
            'support' => [
                'email' => 'mohammed.support@mashacademy.com',
                'password' => Hash::make('Mohammed.Support@123'),
                'must_change_password' => false,
            ],
        ];

        $now = now();

        foreach ($updates as $role => $data) {
            DB::table('users')
                ->where('role', $role)
                ->update([
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'must_change_password' => $data['must_change_password'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive so rollbacks never expose or overwrite production credentials.
    }
};
