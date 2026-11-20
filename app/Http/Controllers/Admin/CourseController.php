<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseRequest;
use App\Models\Course;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Course::class);

        $user = $request->user();

        $courses = Course::query()
            ->with(['creator', 'finalProject'])
            ->withCount(['lessons', 'quizzes', 'assignments'])
            ->when($user->isTeacher(), fn ($q) => $q->where('created_by', $user->id))
            ->orderByDesc('is_featured')
            ->latest()
            ->paginate(12);

        $featuredCount = Course::query()->where('is_featured', true)->count();

        return view('admin.courses.index', [
            'courses' => $courses,
            'featuredCount' => $featuredCount,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Course::class);

        $featuredCount = Course::query()->where('is_featured', true)->count();

        return view('admin.courses.create', [
            'featuredCount' => $featuredCount,
        ]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        Gate::authorize('create', Course::class);

        $validated = $request->validated();

        $course = new Course();
        $course->title = trim((string) $validated['title']);
        $course->title_ar = isset($validated['title_ar']) && $validated['title_ar'] !== '' ? trim((string) $validated['title_ar']) : null;
        $course->slug = Course::generateUniqueSlug($course->title);
        $course->short_description = isset($validated['short_description']) && $validated['short_description'] !== '' ? trim((string) $validated['short_description']) : null;
        $course->short_description_ar = isset($validated['short_description_ar']) && $validated['short_description_ar'] !== '' ? trim((string) $validated['short_description_ar']) : null;
        $course->description = trim((string) $validated['description']);
        $course->description_ar = isset($validated['description_ar']) && $validated['description_ar'] !== '' ? trim((string) $validated['description_ar']) : null;
        $course->price = $validated['price'];
        $course->currency = strtoupper((string) ($validated['currency'] ?? 'EGP'));
        $course->is_published = $request->boolean('is_published');
        $course->is_featured = $request->boolean('is_featured');
        $course->featured_order = $course->is_featured ? ($validated['featured_order'] ?? null) : null;
        $course->created_by = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $course->thumbnail_path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        if ($request->hasFile('instapay_qr')) {
            $course->instapay_qr_path = $request->file('instapay_qr')->store('courses/instapay-qr', 'public');
        }
        $course->instapay_note = isset($validated['instapay_note']) && trim((string) $validated['instapay_note']) !== ''
            ? trim((string) $validated['instapay_note'])
            : null;

        $course->save();

        ActivityLogger::log(
            action: 'course.created',
            subject: $course,
            meta: [
                'title' => $course->title,
                'price' => $course->price,
                'is_published' => $course->is_published,
                'is_featured' => $course->is_featured,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.lessons.index', $course)
            ->with('status', "Course \"{$course->title}\" created. You can now upload lessons, quizzes, assignments, and configure the Final Project.");
    }

    public function edit(Course $course): View
    {
        Gate::authorize('update', $course);

        $course->load('creator');

        $featuredCount = Course::query()
            ->where('is_featured', true)
            ->where('id', '!=', $course->id)
            ->count();

        return view('admin.courses.edit', [
            'course' => $course,
            'featuredCount' => $featuredCount,
            'resolvedInstaPay' => $course->resolveInstaPayDetails(),
        ]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        $validated = $request->validated();

        $oldTitle = $course->title;
        $course->title = trim((string) $validated['title']);
        $course->title_ar = isset($validated['title_ar']) && $validated['title_ar'] !== '' ? trim((string) $validated['title_ar']) : null;
        if ($oldTitle !== $course->title) {
            $course->slug = Course::generateUniqueSlug($course->title, $course->id);
        }
        $course->short_description = isset($validated['short_description']) && $validated['short_description'] !== '' ? trim((string) $validated['short_description']) : null;
        $course->short_description_ar = isset($validated['short_description_ar']) && $validated['short_description_ar'] !== '' ? trim((string) $validated['short_description_ar']) : null;
        $course->description = trim((string) $validated['description']);
        $course->description_ar = isset($validated['description_ar']) && $validated['description_ar'] !== '' ? trim((string) $validated['description_ar']) : null;
        $course->price = $validated['price'];
        $course->currency = strtoupper((string) ($validated['currency'] ?? 'EGP'));
        $course->is_published = $request->boolean('is_published');
        $course->is_featured = $request->boolean('is_featured');
        $course->featured_order = $course->is_featured ? ($validated['featured_order'] ?? null) : null;

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path && Storage::disk('public')->exists($course->thumbnail_path)) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $course->thumbnail_path = $request->file('thumbnail')->store('courses/thumbnails', 'public');
        }

        if ($request->hasFile('instapay_qr')) {
            if ($course->instapay_qr_path && Storage::disk('public')->exists($course->instapay_qr_path)) {
                Storage::disk('public')->delete($course->instapay_qr_path);
            }
            $course->instapay_qr_path = $request->file('instapay_qr')->store('courses/instapay-qr', 'public');
        }
        $course->instapay_note = isset($validated['instapay_note']) && trim((string) $validated['instapay_note']) !== ''
            ? trim((string) $validated['instapay_note'])
            : null;

        $course->save();

        ActivityLogger::log(
            action: 'course.updated',
            subject: $course,
            meta: [
                'title' => $course->title,
                'is_published' => $course->is_published,
                'is_featured' => $course->is_featured,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', 'Course details updated successfully.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('delete', $course);

        $title = $course->title;
        $courseId = $course->id;

        DB::transaction(function () use ($course, $request, $title): void {
            // Remove stored lesson videos
            foreach ($course->lessons as $lesson) {
                if ($lesson->video_path && Storage::disk('local')->exists($lesson->video_path)) {
                    Storage::disk('local')->delete($lesson->video_path);
                }
            }

            // Remove Final Project private attachment
            if ($course->finalProject && $course->finalProject->attachment_path && Storage::disk('local')->exists($course->finalProject->attachment_path)) {
                Storage::disk('local')->delete($course->finalProject->attachment_path);
            }

            // Remove public course thumbnail
            if ($course->thumbnail_path && Storage::disk('public')->exists($course->thumbnail_path)) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }

            // Remove per-course InstaPay QR if present
            if ($course->instapay_qr_path && Storage::disk('public')->exists($course->instapay_qr_path)) {
                Storage::disk('public')->delete($course->instapay_qr_path);
            }

            // Clean up child content records cleanly
            $course->quizzes()->delete();
            $course->assignments()->delete();
            $course->finalProject()->delete();
            $course->lessons()->delete();

            // Unfeature, unpublish, and soft-delete the course record
            $course->is_featured = false;
            $course->featured_order = null;
            $course->is_published = false;
            $course->thumbnail_path = null;
            $course->instapay_qr_path = null;
            $course->save();
            $course->delete();

            ActivityLogger::log(
                action: 'course.deleted',
                subject: $course,
                meta: ['title' => $title],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.courses.index')
            ->with('status', "Course \"{$title}\" (#{$courseId}) and its lessons, quizzes, assignments, final project, and uploaded files have been deleted.");
    }

    public function destroyInstaPayQr(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        if ($course->instapay_qr_path && Storage::disk('public')->exists($course->instapay_qr_path)) {
            Storage::disk('public')->delete($course->instapay_qr_path);
        }

        $course->instapay_qr_path = null;
        $course->save();

        ActivityLogger::log(
            action: 'course.instapay_qr_deleted',
            subject: $course,
            meta: ['title' => $course->title],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', 'Per-course InstaPay QR removed. This course will now use the instructor default QR (or academy default QR).');
    }
}
