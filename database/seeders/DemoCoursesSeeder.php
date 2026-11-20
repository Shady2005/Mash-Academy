<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\FinalProject;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCoursesSeeder extends Seeder
{
    /**
     * Seeds 3 clearly marked Demo Featured Courses for Phase 2 verification.
     * - 2 courses created by Super Admin 1 (to verify Teacher cannot edit/delete them)
     * - 1 course created by Teacher Admin (to verify Teacher manages own & Super Admin can delete it)
     */
    public function run(): void
    {
        if (Course::withTrashed()->exists()) {
            return;
        }

        $superAdmin = User::query()->where('role', UserRole::SuperAdmin1->value)->first();
        $teacher = User::query()->where('role', UserRole::Teacher->value)->first();

        if (! $superAdmin || ! $teacher) {
            return;
        }

        $demoCourses = [
            [
                'owner' => $superAdmin,
                'title' => 'Full-Stack Web Engineering with Laravel [Demo]',
                'title_ar' => 'هندسة الويب المتكاملة باستخدام لارافيل [تجريبي]',
                'short_description' => 'Master modern PHP, Laravel MVC, MySQL schema design, authentication, and production architecture.',
                'short_description_ar' => 'أتقن PHP الحديثة و Laravel وتصميم قواعد بيانات MySQL وبناء أنظمة متكاملة جاهزة للإطلاق.',
                'description' => "A structured, hands-on engineering track covering routing, Eloquent ORM, security policies, RESTful patterns, and deployment.\n\nEach video lesson unlocks sequentially and culminates in a Capstone Final Project required for your Mash Academy certificate.",
                'description_ar' => "مسار عملي منظم يغطي التوجيه و Eloquent ORM وسياسات الأمان وهيكلة التطبيقات الاحترافية.\n\nتُفتح الدروس بالتتابع وتنتهي بمشروع نهائي تطبيقي للحصول على شهادة الإتمام من أكاديمية ماش.",
                'price' => 1800,
                'featured_order' => 1,
                'lessons' => [
                    ['title' => 'Lesson 1: Architecture & Local Environment Setup', 'duration' => 720],
                    ['title' => 'Lesson 2: Relational Database Modeling & Migrations', 'duration' => 940],
                    ['title' => 'Lesson 3: Role-Based Authorization & Policies', 'duration' => 1080],
                ],
                'assignment_type' => Assignment::TYPE_BOTH,
            ],
            [
                'owner' => $superAdmin,
                'title' => 'UI/UX Product Design & Design Systems [Demo]',
                'title_ar' => 'تصميم واجهات وتجربة المستخدم وأنظمة التصميم [تجريبي]',
                'short_description' => 'Learn typography, contrast, responsive grid systems, bilingual RTL interfaces, and component libraries.',
                'short_description_ar' => 'تعلم التباين والشبكات المتجاوبة وتصميم الواجهات ثنائية اللغة (عربي/إنجليزي) ومكتبات المكونات.',
                'description' => "Build crisp, high-contrast digital products from wireframe to interactive prototype with bilingual Arabic RTL and English LTR mastery.",
                'description_ar' => "صمّم منتجات رقمية عالية الوضوح من الفكرة الأولية إلى النماذج التفاعلية مع إتقان تصميم الواجهات العربية والإنجليزية.",
                'price' => 1500,
                'featured_order' => 2,
                'lessons' => [
                    ['title' => 'Lesson 1: Visual Hierarchy, Color Contrast & Typography', 'duration' => 650],
                    ['title' => 'Lesson 2: Building Scalable Bilingual RTL/LTR Grids', 'duration' => 840],
                ],
                'assignment_type' => Assignment::TYPE_FILE,
            ],
            [
                'owner' => $teacher,
                'title' => 'Applied Data Analytics & Business SQL [Demo]',
                'title_ar' => 'تحليل البيانات التطبيقي وقواعد بيانات SQL للأعمال [تجريبي]',
                'short_description' => 'Turn raw operational data into clear executive dashboards, KPIs, and actionable SQL reports.',
                'short_description_ar' => 'حوّل البيانات الخام إلى لوحات مؤشرات أداء واضحة وتقارير SQL عملية لاتخاذ القرارات.',
                'description' => "Designed for analysts and builders: write clean analytical SQL queries, design cohort funnels, and present executive metrics.",
                'description_ar' => "مصممة للمحللين وبناة المنتجات: اكتب استعلامات SQL تحليلية احترافية وصمّم تقارير مؤشرات الأداء.",
                'price' => 1350,
                'featured_order' => 3,
                'lessons' => [
                    ['title' => 'Lesson 1: Analytical Aggregations & Window Functions', 'duration' => 780],
                    ['title' => 'Lesson 2: KPI Modeling & Reporting Accuracy', 'duration' => 890],
                ],
                'assignment_type' => Assignment::TYPE_MCQ,
            ],
        ];

        foreach ($demoCourses as $item) {
            $course = new Course();
            $course->title = $item['title'];
            $course->title_ar = $item['title_ar'];
            $course->slug = Course::generateUniqueSlug($item['title']);
            $course->short_description = $item['short_description'];
            $course->short_description_ar = $item['short_description_ar'];
            $course->description = $item['description'];
            $course->description_ar = $item['description_ar'];
            $course->price = $item['price'];
            $course->currency = 'EGP';
            $course->is_published = true;
            $course->is_featured = true;
            $course->featured_order = $item['featured_order'];
            $course->created_by = $item['owner']->id;
            $course->save();

            $firstLesson = null;
            foreach ($item['lessons'] as $idx => $lessonData) {
                $lesson = Lesson::query()->create([
                    'course_id' => $course->id,
                    'title' => $lessonData['title'],
                    'description' => 'Structured video lesson (upload your MP4/WebM recording from Admin > Lessons).',
                    'position' => $idx + 1,
                    'duration_seconds' => $lessonData['duration'],
                    'is_published' => true,
                ]);
                $firstLesson ??= $lesson;
            }

            // Sample Quiz
            $quiz = new Quiz();
            $quiz->course_id = $course->id;
            $quiz->lesson_id = $firstLesson?->id;
            $quiz->title = 'Module 1 Mastery Check';
            $quiz->description = 'Verify core concepts from Lesson 1 before advancing.';
            $quiz->pass_mark = 70;
            $quiz->is_required_for_next = false;
            $quiz->created_by = $item['owner']->id;
            $quiz->save();

            $q1 = $quiz->questions()->create([
                'question_text' => 'Why does Mash Academy enforce sequential lesson progression?',
                'type' => 'multiple_choice',
                'position' => 1,
                'points' => 1,
            ]);
            $q1->options()->createMany([
                ['option_text' => 'To ensure foundational concepts are mastered before advanced topics', 'is_correct' => true],
                ['option_text' => 'To hide course titles from enrolled students', 'is_correct' => false],
                ['option_text' => 'To disable quizzes across courses', 'is_correct' => false],
            ]);

            // Sample Flexible Assignment
            $assignment = new Assignment();
            $assignment->course_id = $course->id;
            $assignment->lesson_id = $firstLesson?->id;
            $assignment->title = 'Practical Checkpoint Assignment';
            $assignment->submission_type = $item['assignment_type'];
            $assignment->instructions = 'Complete the practical exercise following the lesson blueprint and submit your deliverable.';
            $assignment->pass_mark = $assignment->supportsMcq() ? 70 : null;
            $assignment->created_by = $item['owner']->id;
            $assignment->save();

            if ($assignment->supportsMcq()) {
                $aq = $assignment->questions()->create([
                    'question_text' => 'Which deliverable verifies completion of this practical checkpoint?',
                    'position' => 1,
                    'points' => 2,
                ]);
                $aq->options()->createMany([
                    ['option_text' => 'Documented implementation matching the module specification', 'is_correct' => true],
                    ['option_text' => 'An empty blank file', 'is_correct' => false],
                ]);
            }

            // Dedicated Final Project (95% Gate)
            $fp = new FinalProject();
            $fp->course_id = $course->id;
            $fp->title = 'Capstone Final Project';
            $fp->instructions = "Build and submit your end-to-end Capstone Project demonstrating mastery of every module in this course.\n\nThis Final Project unlocks once you reach at least 95% course completion and is required to earn your Mash Academy Completion Certificate.";
            $fp->required_completion_percentage = 95;
            $fp->is_published = true;
            $fp->created_by = $item['owner']->id;
            $fp->save();
        }
    }
}
