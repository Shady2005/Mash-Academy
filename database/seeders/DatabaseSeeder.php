<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffUsersSeeder::class,
            AcademySettingsSeeder::class,
            PolicySeeder::class,
            DemoCoursesSeeder::class,
        ]);
    }
}
