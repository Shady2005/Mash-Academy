<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\FinalProjectSubmission;
use App\Models\QuizAttempt;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseSubmissionReviewController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $notifications
    ) {}

    public function index(Course $course): View
    {
        Gate::authorize('manageContent', $course);

        $assignmentIds = $course->assignments()->pluck('id');
        $quizIds = $course->quizzes()->pluck('id');
        $finalProject = $course->finalProject;

        $assignmentSubmissions = $assignmentIds->isNotEmpty()
            ? AssignmentSubmission::query()
                ->with(['user', 'assignment', 'reviewer'])
                ->whereIn('assignment_id', $assignmentIds)
                ->latest('submitted_at')
                ->get()
            : collect();

        $finalProjectSubmissions = $finalProject
            ? FinalProjectSubmission::query()
                ->with(['user', 'reviewer'])
                ->where('final_project_id', $finalProject->id)
                ->latest('submitted_at')
                ->get()
            : collect();

        $quizAttempts = $quizIds->isNotEmpty()
            ? QuizAttempt::query()
                ->with(['user', 'quiz'])
                ->whereIn('quiz_id', $quizIds)
                ->whereNotNull('submitted_at')
                ->latest('submitted_at')
                ->limit(50)
                ->get()
            : collect();

        $courseQuestions = CourseQuestion::query()
            ->with(['user', 'lesson', 'replies.user'])
            ->where('course_id', $course->id)
            ->latest()
            ->limit(30)
            ->get();

        return view('admin.courses.submissions.index', [
            'course' => $course,
            'assignmentSubmissions' => $assignmentSubmissions,
            'finalProjectSubmissions' => $finalProjectSubmissions,
            'quizAttempts' => $quizAttempts,
            'courseQuestions' => $courseQuestions,
        ]);
    }

    public function downloadAssignmentFile(Course $course, AssignmentSubmission $submission): StreamedResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($submission->assignment && $submission->assignment->course_id === $course->id, 404);
        abort_unless($submission->hasFile(), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->file_original_name ?: 'assignment-submission'
        );
    }

    public function reviewAssignment(Request $request, Course $course, AssignmentSubmission $submission): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($submission->assignment && $submission->assignment->course_id === $course->id, 404);

        $validated = $request->validate([
            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->grade = $validated['grade'] ?? null;
        $submission->feedback = isset($validated['feedback']) && $validated['feedback'] !== ''
            ? trim((string) $validated['feedback'])
            : null;
        $submission->status = AssignmentSubmission::STATUS_REVIEWED;
        $submission->reviewed_by = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->save();

        ActivityLogger::log(
            action: 'assignment.reviewed',
            subject: $submission,
            meta: ['course_id' => $course->id, 'grade' => $submission->grade],
            actor: $request->user(),
            ip: $request->ip()
        );

        return back()->with('status', 'Assignment submission reviewed and saved.');
    }

    public function downloadFinalProjectFile(Course $course, FinalProjectSubmission $submission): StreamedResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($submission->finalProject && $submission->finalProject->course_id === $course->id, 404);
        abort_unless($submission->hasFile(), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->file_original_name
        );
    }

    public function reviewFinalProject(Request $request, Course $course, FinalProjectSubmission $submission): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($submission->finalProject && $submission->finalProject->course_id === $course->id, 404);

        $validated = $request->validate([
            'grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]);

        $submission->grade = $validated['grade'] ?? null;
        $submission->feedback = isset($validated['feedback']) && $validated['feedback'] !== ''
            ? trim((string) $validated['feedback'])
            : null;
        $submission->status = FinalProjectSubmission::STATUS_REVIEWED;
        $submission->reviewed_by = $request->user()->id;
        $submission->reviewed_at = now();
        $submission->save();

        ActivityLogger::log(
            action: 'final_project.reviewed',
            subject: $submission,
            meta: ['course_id' => $course->id, 'grade' => $submission->grade],
            actor: $request->user(),
            ip: $request->ip()
        );

        $this->notifications->notifyFinalProjectReviewed($submission);

        return back()->with('status', 'Final Project submission reviewed and saved.');
    }
}
