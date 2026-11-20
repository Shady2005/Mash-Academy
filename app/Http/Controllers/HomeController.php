<?php

namespace App\Http\Controllers;

use App\Models\Course;
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

        $instructors = User::query()
            ->whereIn('role', User::INSTRUCTOR_ROLES)
            ->where('is_active', true)
            ->withCount([
                'courses as published_courses_count' => fn ($q) => $q->where('is_published', true),
            ])
            ->orderByRaw("CASE role WHEN 'super_admin_1' THEN 1 WHEN 'super_admin_2' THEN 2 WHEN 'teacher' THEN 3 ELSE 4 END ASC")
            ->get();

        return view('public.home', [
            'activePolicy' => Policy::active(),
            'featuredCourses' => $featuredCourses,
            'totalPublishedCourses' => $totalPublishedCourses,
            'instructors' => $instructors,
        ]);
    }
}
