<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('instructor_title', 150)->nullable()->after('avatar_path');
            $table->string('instructor_title_ar', 150)->nullable()->after('instructor_title');
            $table->text('instructor_bio')->nullable()->after('instructor_title_ar');
            $table->text('instructor_bio_ar')->nullable()->after('instructor_bio');
            $table->string('portfolio_url', 255)->nullable()->after('instructor_bio_ar');
            $table->string('linkedin_url', 255)->nullable()->after('portfolio_url');
            $table->string('github_url', 255)->nullable()->after('linkedin_url');
            $table->string('instapay_qr_path')->nullable()->after('github_url');
            $table->string('instapay_note', 500)->nullable()->after('instapay_qr_path');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('instapay_qr_path')->nullable()->after('thumbnail_path');
            $table->string('instapay_note', 500)->nullable()->after('instapay_qr_path');
        });

        // Populate tasteful default instructor metadata for existing seeded instructors without overwriting custom values
        $defaults = [
            'super_admin_1' => [
                'instructor_title' => 'Founder & Lead Systems Instructor',
                'instructor_title_ar' => 'المؤسس والمحاضر الرئيسي للأنظمة',
                'instructor_bio' => 'Leads Mash Academy curriculum architecture and mentors students through production-grade engineering systems.',
                'instructor_bio_ar' => 'يقود هيكلة المناهج في أكاديمية ماش ويدرب الطلاب على بناء الأنظمة الهندسية الاحترافية.',
            ],
            'super_admin_2' => [
                'instructor_title' => 'Co-Founder & Product Design Instructor',
                'instructor_title_ar' => 'الشريك المؤسس ومحاضر تصميم المنتجات',
                'instructor_bio' => 'Specializes in modern UI/UX systems, bilingual web interfaces, and hands-on product mastery.',
                'instructor_bio_ar' => 'متخصص في أنظمة تصميم واجهات وتجربة المستخدم والواجهات ثنائية اللغة والمشاريع العملية.',
            ],
            'teacher' => [
                'instructor_title' => 'Senior Technical Instructor',
                'instructor_title_ar' => 'محاضر تقني أول',
                'instructor_bio' => 'Guides students step by step through practical workshops, applied analytics, and capstone projects.',
                'instructor_bio_ar' => 'يوجّه الطلاب خطوة بخطوة خلال الورش العملية وتحليل البيانات والمشاريع النهائية.',
            ],
        ];

        foreach ($defaults as $role => $attrs) {
            DB::table('users')
                ->where('role', $role)
                ->whereNull('instructor_title')
                ->update($attrs);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['instapay_qr_path', 'instapay_note']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'instructor_title',
                'instructor_title_ar',
                'instructor_bio',
                'instructor_bio_ar',
                'portfolio_url',
                'linkedin_url',
                'github_url',
                'instapay_qr_path',
                'instapay_note',
            ]);
        });
    }
};
