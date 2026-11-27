<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $scopeFilter = trim((string) $request->query('scope', ''));
        $priorityFilter = trim((string) $request->query('priority', ''));

        $coursesQuery = Course::query()->orderBy('title');
        if ($user->isTeacher()) {
            $coursesQuery->where('created_by', $user->id);
        }
        $manageableCourses = $coursesQuery->get();
        $manageableCourseIds = $manageableCourses->pluck('id')->all();

        $query = Announcement::query()
            ->with(['author', 'course'])
            ->latest();

        if ($user->isTeacher()) {
            $query->whereIn('course_id', $manageableCourseIds ?: [0]);
        }

        if ($scopeFilter === 'global' && $user->isSuperAdmin()) {
            $query->whereNull('course_id');
        } elseif ($scopeFilter === 'course') {
            $query->whereNotNull('course_id');
        }

        if (in_array($priorityFilter, [Announcement::PRIORITY_NORMAL, Announcement::PRIORITY_IMPORTANT], true)) {
            $query->where('priority', $priorityFilter);
        }

        $announcements = $query->paginate(15)->withQueryString();

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'courses' => $manageableCourses,
            'canCreateGlobal' => $user->isSuperAdmin(),
            'scopeFilter' => $scopeFilter,
            'priorityFilter' => $priorityFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $this->validateAnnouncement($request, $user);

        $announcement = Announcement::query()->create([
            'user_id' => $user->id,
            'course_id' => $validated['course_id'],
            'title_en' => trim((string) $validated['title_en']),
            'title_ar' => $this->nullableTrim($validated['title_ar'] ?? null),
            'body_en' => trim((string) $validated['body_en']),
            'body_ar' => $this->nullableTrim($validated['body_ar'] ?? null),
            'priority' => $validated['priority'],
            'is_active' => $request->boolean('is_active', true),
            'expires_at' => ! empty($validated['expires_at']) ? $validated['expires_at'] : null,
        ]);

        ActivityLogger::log(
            action: 'announcement.created',
            subject: $announcement,
            meta: [
                'scope' => $announcement->isGlobal() ? 'global' : 'course',
                'course_id' => $announcement->course_id,
                'priority' => $announcement->priority,
            ],
            actor: $user,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('status', 'Announcement published successfully.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAnnouncement($user, $announcement);

        $validated = $this->validateAnnouncement($request, $user);

        $announcement->update([
            'course_id' => $validated['course_id'],
            'title_en' => trim((string) $validated['title_en']),
            'title_ar' => $this->nullableTrim($validated['title_ar'] ?? null),
            'body_en' => trim((string) $validated['body_en']),
            'body_ar' => $this->nullableTrim($validated['body_ar'] ?? null),
            'priority' => $validated['priority'],
            'is_active' => $request->boolean('is_active'),
            'expires_at' => ! empty($validated['expires_at']) ? $validated['expires_at'] : null,
        ]);

        ActivityLogger::log(
            action: 'announcement.updated',
            subject: $announcement,
            meta: ['course_id' => $announcement->course_id, 'priority' => $announcement->priority],
            actor: $user,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('status', 'Announcement updated successfully.');
    }

    public function toggle(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAnnouncement($user, $announcement);

        $announcement->is_active = ! $announcement->is_active;
        $announcement->save();

        ActivityLogger::log(
            action: 'announcement.toggled',
            subject: $announcement,
            meta: ['is_active' => $announcement->is_active],
            actor: $user,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('status', $announcement->is_active ? 'Announcement activated.' : 'Announcement deactivated.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeAnnouncement($user, $announcement);

        $title = $announcement->title_en;
        $announcement->delete();

        ActivityLogger::log(
            action: 'announcement.deleted',
            meta: ['title_en' => $title],
            actor: $user,
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.announcements.index')
            ->with('status', "Announcement \"{$title}\" deleted.");
    }

    private function authorizeAnnouncement(User $user, Announcement $announcement): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isTeacher()) {
            abort_if($announcement->isGlobal(), 403, 'Teachers cannot manage global site-wide announcements.');
            $course = $announcement->course;
            abort_unless($course && (int) $course->created_by === (int) $user->id, 403, 'You can only manage announcements for your own courses.');

            return;
        }

        abort(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAnnouncement(Request $request, User $user): array
    {
        $courseIdRaw = $request->input('course_id');
        $courseId = ($courseIdRaw !== null && $courseIdRaw !== '') ? (int) $courseIdRaw : null;

        if ($user->isTeacher()) {
            if ($courseId === null) {
                abort(403, 'Teachers can only create course-specific announcements for their own courses.');
            }
            $ownsCourse = Course::query()
                ->where('id', $courseId)
                ->where('created_by', $user->id)
                ->exists();
            abort_unless($ownsCourse, 403, 'You can only post announcements inside courses you created.');
        }

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'title_en' => ['required', 'string', 'min:3', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'body_en' => ['required', 'string', 'min:5', 'max:4000'],
            'body_ar' => ['nullable', 'string', 'max:4000'],
            'priority' => ['required', Rule::in([Announcement::PRIORITY_NORMAL, Announcement::PRIORITY_IMPORTANT])],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $validated['course_id'] = $courseId;

        return $validated;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $clean = trim((string) ($value ?? ''));

        return $clean !== '' ? $clean : null;
    }
}
