<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CoursePackageRequest;
use App\Models\Course;
use App\Models\CoursePackage;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CoursePackageController extends Controller
{
    public function index(): View
    {
        $packages = CoursePackage::query()
            ->with(['creator', 'courses'])
            ->withCount('courses')
            ->latest()
            ->paginate(12);

        return view('admin.packages.index', [
            'packages' => $packages,
        ]);
    }

    public function create(): View
    {
        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'title_ar', 'price', 'currency', 'is_published']);

        return view('admin.packages.create', [
            'courses' => $courses,
        ]);
    }

    public function store(CoursePackageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $package = DB::transaction(function () use ($request, $validated): CoursePackage {
            $package = new CoursePackage();
            $package->title = trim((string) $validated['title']);
            $package->title_ar = $this->nullableTrim($validated['title_ar'] ?? null);
            $package->slug = CoursePackage::generateUniqueSlug($package->title);
            $package->short_description = $this->nullableTrim($validated['short_description'] ?? null);
            $package->short_description_ar = $this->nullableTrim($validated['short_description_ar'] ?? null);
            $package->description = trim((string) $validated['description']);
            $package->description_ar = $this->nullableTrim($validated['description_ar'] ?? null);
            $package->price = $validated['price'];
            $package->currency = strtoupper((string) ($validated['currency'] ?? 'EGP'));
            $package->is_published = $request->boolean('is_published');
            $package->instapay_note = $this->nullableTrim($validated['instapay_note'] ?? null);
            $package->created_by = $request->user()->id;

            if ($request->hasFile('thumbnail')) {
                $package->thumbnail_path = $request->file('thumbnail')->store('packages/thumbnails', 'public');
            }

            if ($request->hasFile('instapay_qr')) {
                $package->instapay_qr_path = $request->file('instapay_qr')->store('packages/instapay-qr', 'public');
            }

            $package->save();

            $this->syncCourses($package, $validated['course_ids']);

            ActivityLogger::log(
                action: 'package.created',
                subject: $package,
                meta: [
                    'title' => $package->title,
                    'price' => $package->price,
                    'course_ids' => $validated['course_ids'],
                    'is_published' => $package->is_published,
                ],
                actor: $request->user(),
                ip: $request->ip()
            );

            return $package;
        });

        return redirect()
            ->route('admin.packages.index')
            ->with('status', "Package \"{$package->title}\" created successfully.");
    }

    public function edit(CoursePackage $package): View
    {
        $package->load(['creator', 'courses']);

        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'title_ar', 'price', 'currency', 'is_published']);

        return view('admin.packages.edit', [
            'package' => $package,
            'courses' => $courses,
            'selectedCourseIds' => $package->courses->pluck('id')->all(),
            'resolvedInstaPay' => $package->resolveInstaPayDetails(),
        ]);
    }

    public function update(CoursePackageRequest $request, CoursePackage $package): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $package, $validated): void {
            $oldTitle = $package->title;
            $package->title = trim((string) $validated['title']);
            $package->title_ar = $this->nullableTrim($validated['title_ar'] ?? null);
            if ($oldTitle !== $package->title) {
                $package->slug = CoursePackage::generateUniqueSlug($package->title, $package->id);
            }
            $package->short_description = $this->nullableTrim($validated['short_description'] ?? null);
            $package->short_description_ar = $this->nullableTrim($validated['short_description_ar'] ?? null);
            $package->description = trim((string) $validated['description']);
            $package->description_ar = $this->nullableTrim($validated['description_ar'] ?? null);
            $package->price = $validated['price'];
            $package->currency = strtoupper((string) ($validated['currency'] ?? 'EGP'));
            $package->is_published = $request->boolean('is_published');
            $package->instapay_note = $this->nullableTrim($validated['instapay_note'] ?? null);

            if ($request->hasFile('thumbnail')) {
                if ($package->thumbnail_path && Storage::disk('public')->exists($package->thumbnail_path)) {
                    Storage::disk('public')->delete($package->thumbnail_path);
                }
                $package->thumbnail_path = $request->file('thumbnail')->store('packages/thumbnails', 'public');
            }

            if ($request->hasFile('instapay_qr')) {
                if ($package->instapay_qr_path && Storage::disk('public')->exists($package->instapay_qr_path)) {
                    Storage::disk('public')->delete($package->instapay_qr_path);
                }
                $package->instapay_qr_path = $request->file('instapay_qr')->store('packages/instapay-qr', 'public');
            }

            $package->save();

            $this->syncCourses($package, $validated['course_ids']);

            ActivityLogger::log(
                action: 'package.updated',
                subject: $package,
                meta: [
                    'title' => $package->title,
                    'course_ids' => $validated['course_ids'],
                    'is_published' => $package->is_published,
                ],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.packages.edit', $package)
            ->with('status', 'Package updated successfully.');
    }

    public function togglePublish(Request $request, CoursePackage $package): RedirectResponse
    {
        $package->is_published = ! $package->is_published;
        $package->save();

        ActivityLogger::log(
            action: 'package.toggled_publish',
            subject: $package,
            meta: ['is_published' => $package->is_published],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.packages.index')
            ->with('status', "Package \"{$package->title}\" is now ".($package->is_published ? 'Published' : 'Unpublished').'.');
    }

    public function destroyInstaPayQr(Request $request, CoursePackage $package): RedirectResponse
    {
        if ($package->instapay_qr_path && Storage::disk('public')->exists($package->instapay_qr_path)) {
            Storage::disk('public')->delete($package->instapay_qr_path);
        }

        $package->instapay_qr_path = null;
        $package->save();

        ActivityLogger::log(
            action: 'package.instapay_qr_deleted',
            subject: $package,
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.packages.edit', $package)
            ->with('status', 'Per-package InstaPay QR removed. Checkout will fall back to instructor/academy default QR.');
    }

    public function destroy(Request $request, CoursePackage $package): RedirectResponse
    {
        $title = $package->title;

        DB::transaction(function () use ($request, $package, $title): void {
            if ($package->thumbnail_path && Storage::disk('public')->exists($package->thumbnail_path)) {
                Storage::disk('public')->delete($package->thumbnail_path);
            }
            if ($package->instapay_qr_path && Storage::disk('public')->exists($package->instapay_qr_path)) {
                Storage::disk('public')->delete($package->instapay_qr_path);
            }

            $package->is_published = false;
            $package->thumbnail_path = null;
            $package->instapay_qr_path = null;
            $package->save();
            $package->delete();

            ActivityLogger::log(
                action: 'package.deleted',
                subject: $package,
                meta: ['title' => $title],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.packages.index')
            ->with('status', "Package \"{$title}\" has been deleted.");
    }

    /**
     * @param  array<int, int|string>  $courseIds
     */
    private function syncCourses(CoursePackage $package, array $courseIds): void
    {
        $pivotData = [];
        $pos = 1;
        foreach (array_values(array_unique(array_map('intval', $courseIds))) as $cid) {
            $pivotData[$cid] = ['position' => $pos++];
        }

        $package->courses()->sync($pivotData);
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
