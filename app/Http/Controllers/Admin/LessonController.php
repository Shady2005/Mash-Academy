<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LessonRequest;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('manageContent', $course);

        $lessons = $course->lessons()->get();

        return view('admin.courses.lessons.index', [
            'course' => $course,
            'lessons' => $lessons,
            'phpUploadMax' => ini_get('upload_max_filesize') ?: 'unknown',
            'phpPostMax' => ini_get('post_max_size') ?: 'unknown',
            'appMaxMb' => round(LessonRequest::maxVideoKilobytes() / 1024),
            'defaultBunnyLibraryId' => AcademySetting::getValue('bunny_library_id'),
        ]);
    }

    public function store(LessonRequest $request, Course $course): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageContent', $course);

        $validated = $request->validated();
        $nextPosition = ((int) $course->lessons()->max('position')) + 1;

        $lesson = new Lesson();
        $lesson->course_id = $course->id;
        $lesson->title = trim((string) $validated['title']);
        $lesson->description = isset($validated['description']) && $validated['description'] !== '' ? trim((string) $validated['description']) : null;
        $lesson->position = $nextPosition;
        $lesson->duration_seconds = isset($validated['duration_seconds']) && $validated['duration_seconds'] !== '' ? (int) $validated['duration_seconds'] : null;
        $lesson->is_published = $request->boolean('is_published', true);

        $sourceType = (string) ($validated['video_source_type'] ?? Lesson::SOURCE_UPLOAD);
        $lesson->video_source_type = $sourceType;

        if ($sourceType === Lesson::SOURCE_BUNNY) {
            $lesson->bunny_library_id = isset($validated['bunny_library_id']) && trim((string) $validated['bunny_library_id']) !== ''
                ? trim((string) $validated['bunny_library_id'])
                : null;
            $lesson->bunny_video_id = trim((string) ($validated['bunny_video_id'] ?? ''));
        } elseif ($request->hasFile('video')) {
            $video = $request->file('video');
            $lesson->video_path = $video->store("courses/{$course->id}/videos", 'local');
            $lesson->video_original_name = $video->getClientOriginalName();
            $lesson->video_mime = $this->resolveVideoMime($video);
            $lesson->video_size_bytes = $video->getSize();
        }

        $lesson->save();

        ActivityLogger::log(
            action: 'lesson.created',
            subject: $lesson,
            meta: [
                'course_id' => $course->id,
                'title' => $lesson->title,
                'has_video' => ! empty($lesson->video_path),
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Lesson \"{$lesson->title}\" added.",
                'redirect' => route('admin.courses.lessons.index', $course),
            ], 201);
        }

        return redirect()
            ->route('admin.courses.lessons.index', $course)
            ->with('status', "Lesson \"{$lesson->title}\" added.");
    }

    public function update(LessonRequest $request, Course $course, Lesson $lesson): RedirectResponse|JsonResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($lesson->course_id === $course->id, 404);

        $validated = $request->validated();

        $lesson->title = trim((string) $validated['title']);
        $lesson->description = isset($validated['description']) && $validated['description'] !== '' ? trim((string) $validated['description']) : null;
        $lesson->duration_seconds = isset($validated['duration_seconds']) && $validated['duration_seconds'] !== '' ? (int) $validated['duration_seconds'] : null;
        $lesson->is_published = $request->boolean('is_published');

        $sourceType = (string) ($validated['video_source_type'] ?? $lesson->video_source_type ?? Lesson::SOURCE_UPLOAD);
        $lesson->video_source_type = $sourceType;

        if ($sourceType === Lesson::SOURCE_BUNNY) {
            $lesson->bunny_library_id = isset($validated['bunny_library_id']) && trim((string) $validated['bunny_library_id']) !== ''
                ? trim((string) $validated['bunny_library_id'])
                : null;
            $lesson->bunny_video_id = trim((string) ($validated['bunny_video_id'] ?? ''));
        } elseif ($request->hasFile('video')) {
            if ($lesson->video_path && Storage::disk('local')->exists($lesson->video_path)) {
                Storage::disk('local')->delete($lesson->video_path);
            }

            $video = $request->file('video');
            $lesson->video_path = $video->store("courses/{$course->id}/videos", 'local');
            $lesson->video_original_name = $video->getClientOriginalName();
            $lesson->video_mime = $this->resolveVideoMime($video);
            $lesson->video_size_bytes = $video->getSize();
        }

        $lesson->save();

        ActivityLogger::log(
            action: 'lesson.updated',
            subject: $lesson,
            meta: ['course_id' => $course->id, 'title' => $lesson->title],
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Lesson \"{$lesson->title}\" updated.",
                'redirect' => route('admin.courses.lessons.index', $course),
            ]);
        }

        return redirect()
            ->route('admin.courses.lessons.index', $course)
            ->with('status', "Lesson \"{$lesson->title}\" updated.");
    }

    public function move(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($lesson->course_id === $course->id, 404);

        $validated = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        DB::transaction(function () use ($course, $lesson, $validated): void {
            $ordered = $course->lessons()->get()->values();
            $currentIndex = $ordered->search(fn (Lesson $item): bool => $item->id === $lesson->id);

            if ($currentIndex === false) {
                return;
            }

            $swapIndex = $validated['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;
            if (! isset($ordered[$swapIndex])) {
                return;
            }

            $temp = $ordered[$currentIndex];
            $ordered[$currentIndex] = $ordered[$swapIndex];
            $ordered[$swapIndex] = $temp;

            foreach ($ordered as $idx => $item) {
                $item->position = $idx + 1;
                $item->save();
            }
        });

        return redirect()
            ->route('admin.courses.lessons.index', $course)
            ->with('status', 'Lesson order updated.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($lesson->course_id === $course->id, 404);

        $title = $lesson->title;

        DB::transaction(function () use ($course, $lesson, $request, $title): void {
            if ($lesson->video_path && Storage::disk('local')->exists($lesson->video_path)) {
                Storage::disk('local')->delete($lesson->video_path);
            }

            $lesson->delete();

            // Re-sequence remaining lessons cleanly
            $course->lessons()->get()->values()->each(function (Lesson $item, int $idx): void {
                $item->position = $idx + 1;
                $item->save();
            });

            ActivityLogger::log(
                action: 'lesson.deleted',
                subject: $course,
                meta: ['course_id' => $course->id, 'lesson_title' => $title],
                actor: $request->user(),
                ip: $request->ip()
            );
        });

        return redirect()
            ->route('admin.courses.lessons.index', $course)
            ->with('status', "Lesson \"{$title}\" deleted.");
    }

    /**
     * Secure admin-only video preview stream with HTTP Range support (206 Partial Content).
     */
    public function stream(Request $request, Course $course, Lesson $lesson): StreamedResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($lesson->course_id === $course->id, 404);
        abort_unless($lesson->hasUploadedVideo(), 404, 'Video file not found.');

        $fullPath = Storage::disk('local')->path($lesson->video_path);
        $fileSize = filesize($fullPath);
        $mime = $lesson->video_mime ?: 'video/mp4';

        $start = 0;
        $end = $fileSize - 1;
        $status = 200;

        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline',
        ];

        $rangeHeader = $request->header('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/i', $rangeHeader, $matches)) {
            if ($matches[1] !== '') {
                $start = (int) $matches[1];
            }
            if ($matches[2] !== '') {
                $end = min((int) $matches[2], $fileSize - 1);
            }

            if ($start > $end || $start >= $fileSize) {
                abort(416, 'Requested Range Not Satisfiable');
            }

            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $length = ($end - $start) + 1;
        $headers['Content-Length'] = (string) $length;

        return response()->stream(function () use ($fullPath, $start, $length): void {
            $handle = fopen($fullPath, 'rb');
            if ($handle === false) {
                return;
            }

            fseek($handle, $start);
            $remaining = $length;
            $chunkSize = 1024 * 64;

            while ($remaining > 0 && ! feof($handle)) {
                $readLength = min($chunkSize, $remaining);
                $buffer = fread($handle, $readLength);
                if ($buffer === false) {
                    break;
                }
                echo $buffer;
                flush();
                $remaining -= strlen($buffer);
            }

            fclose($handle);
        }, $status, $headers);
    }

    private function resolveVideoMime(UploadedFile $video): string
    {
        $detected = strtolower((string) $video->getMimeType());
        if (in_array($detected, ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v'], true)) {
            return $detected;
        }

        return match (strtolower($video->getClientOriginalExtension())) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'm4v' => 'video/mp4',
            default => 'video/mp4',
        };
    }
}
