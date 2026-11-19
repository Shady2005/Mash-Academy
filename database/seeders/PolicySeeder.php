<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Database\Seeder;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        if (Policy::query()->exists()) {
            return;
        }

        $owner = User::query()->where('role', UserRole::SuperAdmin1->value)->first();

        Policy::createNewVersion(
            title: 'Mash Academy Student Terms & Platform Policy',
            body: <<<'POLICY'
Welcome to Mash Academy. By creating a student account and accessing our platform, you agree to comply with the following policies:

1. Personal Account Use
Your Mash Academy account is strictly personal and non-transferable. Sharing login credentials, recorded lessons, quizzes, or course materials with unauthorized individuals is prohibited and may result in immediate account suspension without refund.

2. Course Access & Time-Limited Enrollments
All courses remain locked until your enrollment is verified and activated by Mash Academy administration. Active enrollments grant access strictly for the approved duration and automatically lock once the access period expires.

3. Sequential Learning & Academic Integrity
Course videos, quizzes, and assignments are designed to be completed in sequence. Students must complete their own quizzes and assignments honestly and respect instructors and support staff at all times.

4. Intellectual Property
All videos, study materials, quizzes, and branding published on Mash Academy are protected intellectual property. Recording, downloading, redistributing, or republishing any platform content without written consent is strictly forbidden.

5. Privacy & Contact Information
You agree to provide accurate contact information (full name, email address, and phone number) so Mash Academy can verify enrollments and provide student support.

6. Policy Updates
Mash Academy may update this Policy from time to time. Each revision is versioned and archived, and continued use of the platform signifies acceptance of the active Policy.
POLICY,
            actor: $owner
        );
    }
}
