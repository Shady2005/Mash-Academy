<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInstructorProfileRequest;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InstructorProfileController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('manage-instructor-profile');

        $instructor = $request->user();
        $coursesCount = Course::query()
            ->where('created_by', $instructor->id)
            ->count();

        return view('admin.instructor-profile.edit', [
            'instructor' => $instructor,
            'coursesCount' => $coursesCount,
            'globalInstaPayQrUrl' => AcademySetting::instapayQrUrl(),
            'globalInstaPayNote' => AcademySetting::getValue('instapay_account_note'),
        ]);
    }

    public function update(UpdateInstructorProfileRequest $request): RedirectResponse
    {
        Gate::authorize('manage-instructor-profile');

        $instructor = $request->user();
        $validated = $request->validated();

        $instructor->name = trim((string) $validated['name']);
        $instructor->instructor_title = $this->nullableTrim($validated['instructor_title'] ?? null);
        $instructor->instructor_title_ar = $this->nullableTrim($validated['instructor_title_ar'] ?? null);
        $instructor->instructor_bio = $this->nullableTrim($validated['instructor_bio'] ?? null);
        $instructor->instructor_bio_ar = $this->nullableTrim($validated['instructor_bio_ar'] ?? null);
        $instructor->portfolio_url = $this->nullableTrim($validated['portfolio_url'] ?? null);
        $instructor->linkedin_url = $this->nullableTrim($validated['linkedin_url'] ?? null);
        $instructor->github_url = $this->nullableTrim($validated['github_url'] ?? null);
        $instructor->instapay_note = $this->nullableTrim($validated['instapay_note'] ?? null);

        if ($request->hasFile('avatar')) {
            if ($instructor->avatar_path && Storage::disk('public')->exists($instructor->avatar_path)) {
                Storage::disk('public')->delete($instructor->avatar_path);
            }
            $instructor->avatar_path = $request->file('avatar')->store('instructors/avatars', 'public');
        }

        if ($request->hasFile('instapay_qr')) {
            if ($instructor->instapay_qr_path && Storage::disk('public')->exists($instructor->instapay_qr_path)) {
                Storage::disk('public')->delete($instructor->instapay_qr_path);
            }
            $instructor->instapay_qr_path = $request->file('instapay_qr')->store('instructors/instapay-qr', 'public');
        }

        $instructor->save();

        ActivityLogger::log(
            action: 'instructor_profile.updated',
            subject: $instructor,
            meta: [
                'has_avatar' => (bool) $instructor->avatar_path,
                'has_portfolio' => (bool) $instructor->portfolio_url,
                'has_personal_instapay_qr' => (bool) $instructor->instapay_qr_path,
            ],
            actor: $instructor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.instructor-profile.edit')
            ->with('status', 'Instructor profile and personal InstaPay QR settings updated successfully.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        Gate::authorize('manage-instructor-profile');

        $instructor = $request->user();
        if ($instructor->avatar_path && Storage::disk('public')->exists($instructor->avatar_path)) {
            Storage::disk('public')->delete($instructor->avatar_path);
        }

        $instructor->avatar_path = null;
        $instructor->save();

        ActivityLogger::log(
            action: 'instructor_profile.avatar_deleted',
            subject: $instructor,
            actor: $instructor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.instructor-profile.edit')
            ->with('status', 'Profile photo removed.');
    }

    public function destroyInstaPayQr(Request $request): RedirectResponse
    {
        Gate::authorize('manage-instructor-profile');

        $instructor = $request->user();
        if ($instructor->instapay_qr_path && Storage::disk('public')->exists($instructor->instapay_qr_path)) {
            Storage::disk('public')->delete($instructor->instapay_qr_path);
        }

        $instructor->instapay_qr_path = null;
        $instructor->save();

        ActivityLogger::log(
            action: 'instructor_profile.instapay_qr_deleted',
            subject: $instructor,
            actor: $instructor,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.instructor-profile.edit')
            ->with('status', 'Personal default InstaPay QR code removed. Courses without a dedicated QR will fall back to the academy default QR.');
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
