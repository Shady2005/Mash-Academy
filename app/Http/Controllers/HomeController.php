<?php

namespace App\Http\Controllers;

use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\Policy;
use App\Models\User;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredCourses = Course::query()
            ->featured()
            ->with(['creator', 'finalProject'])
            ->withCount(['publishedLessons', 'quizzes', 'assignments'])
            ->limit(Course::MAX_FEATURED)
            ->get();

        $totalPublishedCourses = Course::query()->published()->count();

        $packages = CoursePackage::query()
            ->published()
            ->with('courses')
            ->withCount('courses')
            ->latest()
            ->limit(6)
            ->get();

        $instructors = User::query()
            ->whereIn('role', User::INSTRUCTOR_ROLES)
            ->where('is_active', true)
            ->withCount([
                'courses as published_courses_count' => fn ($q) => $q->where('is_published', true),
            ])
            ->orderByRaw("CASE role WHEN 'super_admin_1' THEN 1 WHEN 'super_admin_2' THEN 2 WHEN 'teacher' THEN 3 ELSE 4 END ASC")
            ->get();

        $demoVideosEnabled = AcademySetting::isFeatureEnabled('enable_demo_videos');
        $demoCourses = $demoVideosEnabled
            ? Course::query()
                ->published()
                ->where('has_demo_video', true)
                ->whereNotNull('demo_video_value')
                ->where('demo_video_value', '!=', '')
                ->with('creator')
                ->latest()
                ->limit(3)
                ->get()
            : collect();

        return view('public.home', [
            'activePolicy' => Policy::active(),
            'featuredCourses' => $featuredCourses,
            'totalPublishedCourses' => $totalPublishedCourses,
            'packages' => $packages,
            'instructors' => $instructors,
            'demoVideosEnabled' => $demoVideosEnabled,
            'demoCourses' => $demoCourses,
        ]);
    }
}
