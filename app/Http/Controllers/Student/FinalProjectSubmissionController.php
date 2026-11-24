<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\FinalProjectSubmission;
use App\Services\ActivityLogger;
use App\Services\CertificateService;
use App\Services\DripLockService;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinalProjectSubmissionController extends Controller
{
    public function __construct(
        private readonly DripLockService $dripLock,
        private readonly CertificateService $certificateService,
        private readonly NotificationDispatcher $notifications
    ) {}

    public function show(Request $request, Course $course): View
    {
        $finalProject = $course->finalProject;
        abort_unless($finalProject && $finalProject->is_published, 404, 'This course does not have a published Final Project.');

        $student = $request->user();
        $summary = $this->dripLock->courseProgressSummary($student, $course);

        if (! $summary['final_project_unlocked']) {
            abort(
                403,
                "Final Project is locked. Current course completion: {$summary['completion_percentage']}%. Reach at least {$summary['required_final_project_percentage']}% to unlock."
            );
        }

        $submission = FinalProjectSubmission::query()
            ->where('final_project_id', $finalProject->id)
            ->where('user_id', $student->id)
            ->first();

        return view('student.final-project.show', [
            'course' => $course,
            'finalProject' => $finalProject,
            'summary' => $summary,
            'submission' => $submission,
        ]);
    }

    public function submit(Request $request, Course $course): RedirectResponse
    {
        $finalProject = $course->finalProject;
        abort_unless($finalProject && $finalProject->is_published, 404);

        $student = $request->user();
        $summary = $this->dripLock->courseProgressSummary($student, $course);

        if (! $summary['final_project_unlocked']) {
            abort(
                403,
                "Final Project is locked until you reach at least {$summary['required_final_project_percentage']}% course completion."
            );
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,zip,png,jpg,jpeg', 'max:30720'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], [
            'file.required' => 'Please upload your Final Project deliverable file.',
            'file.mimes' => 'Allowed formats: PDF, DOC, DOCX, ZIP, PNG, JPG, JPEG.',
            'file.max' => 'Final Project file may not exceed 30 MB.',
        ]);

        $submission = FinalProjectSubmission::query()->firstOrNew([
            'final_project_id' => $finalProject->id,
            'user_id' => $student->id,
        ]);

        if ($submission->file_path && Storage::disk('local')->exists($submission->file_path)) {
            Storage::disk('local')->delete($submission->file_path);
        }

        $file = $request->file('file');
        $submission->file_path = $file->store("submissions/final-projects/project-{$finalProject->id}/user-{$student->id}", 'local');
        $submission->file_original_name = $file->getClientOriginalName();
        $submission->notes = isset($validated['notes']) && $validated['notes'] !== ''
            ? trim((string) $validated['notes'])
            : null;
        $submission->status = FinalProjectSubmission::STATUS_SUBMITTED;
        $submission->submitted_at = now();
        $submission->save();

        ActivityLogger::log(
            action: 'final_project.submitted',
            subject: $submission,
            meta: [
                'course_id' => $course->id,
                'final_project_id' => $finalProject->id,
                'completion_percentage' => $summary['completion_percentage'],
            ],
            actor: $student,
            ip: $request->ip()
        );

        // Notify Super Admins & Course Teacher
        $this->notifications->notifyFinalProjectSubmitted($submission);

        // Immediately issue Completion Certificate now that Final Project is submitted!
        $certificate = $this->certificateService->issueIfEligible($student, $course, $request->ip());

        $message = $certificate
            ? "Congratulations! Your Final Project has been submitted and your Certificate ({$certificate->certificate_code}) is now unlocked in My Certificates!"
            : 'Congratulations! Your Final Project has been submitted for review.';

        return redirect()
            ->route('student.courses.final-project.show', $course)
            ->with('status', $message);
    }

    public function downloadBrief(Request $request, Course $course): StreamedResponse
    {
        $finalProject = $course->finalProject;
        abort_unless($finalProject && $finalProject->is_published && $finalProject->hasAttachment(), 404);

        $summary = $this->dripLock->courseProgressSummary($request->user(), $course);
        abort_unless($summary['final_project_unlocked'], 403, 'Final Project is locked until ≥ 95% completion.');

        return Storage::disk('local')->download(
            $finalProject->attachment_path,
            $finalProject->attachment_original_name ?: 'final-project-brief'
        );
    }

    public function downloadOwnSubmission(Request $request, Course $course): StreamedResponse
    {
        $finalProject = $course->finalProject;
        abort_unless($finalProject, 404);

        $submission = FinalProjectSubmission::query()
            ->where('final_project_id', $finalProject->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($submission->hasFile(), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->file_original_name
        );
    }
}
