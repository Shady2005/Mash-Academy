<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FinalProjectRequest;
use App\Models\Course;
use App\Models\FinalProject;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinalProjectController extends Controller
{
    public function edit(Course $course): View
    {
        Gate::authorize('manageContent', $course);

        return view('admin.courses.final-project.edit', [
            'course' => $course,
            'finalProject' => $course->finalProject,
        ]);
    }

    public function upsert(FinalProjectRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('manageContent', $course);

        $validated = $request->validated();
        $finalProject = $course->finalProject ?? new FinalProject(['course_id' => $course->id]);

        $isNew = ! $finalProject->exists;
        $finalProject->course_id = $course->id;
        $finalProject->title = trim((string) $validated['title']);
        $finalProject->instructions = trim((string) $validated['instructions']);
        $finalProject->required_completion_percentage = (int) $validated['required_completion_percentage'];
        $finalProject->is_published = $request->boolean('is_published', true);

        if ($isNew) {
            $finalProject->created_by = $request->user()->id;
        }

        if ($request->hasFile('attachment')) {
            if ($finalProject->attachment_path && Storage::disk('local')->exists($finalProject->attachment_path)) {
                Storage::disk('local')->delete($finalProject->attachment_path);
            }

            $file = $request->file('attachment');
            $finalProject->attachment_path = $file->store("courses/{$course->id}/final-project", 'local');
            $finalProject->attachment_original_name = $file->getClientOriginalName();
        }

        $finalProject->save();

        ActivityLogger::log(
            action: $isNew ? 'final_project.created' : 'final_project.updated',
            subject: $finalProject,
            meta: [
                'course_id' => $course->id,
                'title' => $finalProject->title,
                'required_completion_percentage' => $finalProject->required_completion_percentage,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.final-project.edit', $course)
            ->with('status', 'Final Project configuration saved.');
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manageContent', $course);

        $finalProject = $course->finalProject;
        if ($finalProject) {
            if ($finalProject->attachment_path && Storage::disk('local')->exists($finalProject->attachment_path)) {
                Storage::disk('local')->delete($finalProject->attachment_path);
            }

            $title = $finalProject->title;
            $finalProject->delete();

            ActivityLogger::log(
                action: 'final_project.deleted',
                subject: $course,
                meta: ['course_id' => $course->id, 'title' => $title],
                actor: $request->user(),
                ip: $request->ip()
            );
        }

        return redirect()
            ->route('admin.courses.final-project.edit', $course)
            ->with('status', 'Final Project removed from this course.');
    }

    public function downloadAttachment(Course $course): StreamedResponse
    {
        Gate::authorize('manageContent', $course);

        $finalProject = $course->finalProject;
        abort_unless($finalProject && $finalProject->hasAttachment(), 404);

        return Storage::disk('local')->download(
            $finalProject->attachment_path,
            $finalProject->attachment_original_name ?: 'final-project-brief'
        );
    }
}
