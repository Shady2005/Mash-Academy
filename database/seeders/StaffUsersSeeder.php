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
                'email' => 'shady.admin@mashacademy.com',
                'phone' => '+201000000001',
                'password' => 'Shady.admin@123',
                'role' => UserRole::SuperAdmin1,
                'instructor_title' => 'Founder & Lead Systems Instructor',
                'instructor_title_ar' => 'المؤسس والمحاضر الرئيسي للأنظمة',
                'instructor_bio' => 'Leads Mash Academy curriculum architecture and mentors students through production-grade engineering systems.',
                'instructor_bio_ar' => 'يقود هيكلة المناهج في أكاديمية ماش ويدرب الطلاب على بناء الأنظمة الهندسية الاحترافية.',
            ],
            [
                'name' => 'Super Admin 2 (Co-Owner)',
                'email' => 'abdullah.admin@mashacademy.com',
                'phone' => '+201000000002',
                'password' => 'Abdalluh.admin@123',
                'role' => UserRole::SuperAdmin2,
                'instructor_title' => 'Co-Founder & Product Design Instructor',
                'instructor_title_ar' => 'الشريك المؤسس ومحاضر تصميم المنتجات',
                'instructor_bio' => 'Specializes in modern UI/UX systems, bilingual web interfaces, and hands-on product mastery.',
                'instructor_bio_ar' => 'متخصص في أنظمة تصميم واجهات وتجربة المستخدم والواجهات ثنائية اللغة والمشاريع العملية.',
            ],
            [
                'name' => 'Teacher Admin',
                'email' => 'mariam.teacher@mashacademy.com',
                'phone' => '+201000000003',
                'password' => 'Mariam.Teacher@123',
                'role' => UserRole::Teacher,
                'instructor_title' => 'Senior Technical Instructor',
                'instructor_title_ar' => 'محاضر تقني أول',
                'instructor_bio' => 'Guides students step by step through practical workshops, applied analytics, and capstone projects.',
                'instructor_bio_ar' => 'يوجّه الطلاب خطوة بخطوة خلال الورش العملية وتحليل البيانات والمشاريع النهائية.',
            ],
            [
                'name' => 'Customer Support Admin',
                'email' => 'mohammed.support@mashacademy.com',
                'phone' => '+201000000004',
                'password' => 'Mohammed.Support@123',
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
            $user->must_change_password = false;
            $user->email_verified_at = now();
            $user->instructor_title = $account['instructor_title'] ?? null;
            $user->instructor_title_ar = $account['instructor_title_ar'] ?? null;
            $user->instructor_bio = $account['instructor_bio'] ?? null;
            $user->instructor_bio_ar = $account['instructor_bio_ar'] ?? null;
            $user->save();
        }
    }
}
