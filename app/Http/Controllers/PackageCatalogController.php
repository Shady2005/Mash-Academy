<?php

namespace App\Http\Controllers;

use App\Models\CoursePackage;
use App\Services\CourseAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageCatalogController extends Controller
{
    public function __construct(
        private readonly CourseAccessService $courseAccess
    ) {}

    public function show(Request $request, CoursePackage $package): View
    {
        abort_unless($package->is_published, 404);

        $package->load([
            'creator',
            'courses' => fn ($q) => $q->withCount(['publishedLessons', 'quizzes', 'assignments']),
        ]);

        $enrolledCourseIds = [];
        if ($request->user()?->isStudent()) {
            $enrolledCourseIds = $this->courseAccess->activeEnrollmentsMapFor($request->user())
                ->keys()
                ->all();
        }

        return view('public.packages.show', [
            'package' => $package,
            'enrolledCourseIds' => $enrolledCourseIds,
        ]);
    }
}
