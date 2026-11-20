<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Services\ActivityLogger;
use App\Services\DripLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentSubmissionController extends Controller
{
    public function __construct(
        private readonly DripLockService $dripLock
    ) {}

    public function show(Request $request, Course $course, Assignment $assignment): View
    {
        abort_unless($assignment->course_id === $course->id, 404);
        $this->ensureLinkedLessonUnlocked($request, $course, $assignment);

        $assignment->load(['lesson', 'questions.options']);

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return view('student.assignments.show', [
            'course' => $course,
            'assignment' => $assignment,
            'submission' => $submission,
        ]);
    }

    public function submit(Request $request, Course $course, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->course_id === $course->id, 404);
        $this->ensureLinkedLessonUnlocked($request, $course, $assignment);

        $assignment->load('questions.options');
        $student = $request->user();

        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->first();

        $rules = [
            'notes' => ['nullable', 'string', 'max:4000'],
        ];

        if ($assignment->supportsFile()) {
            $fileRequired = ($existing === null || ! $existing->hasFile()) ? 'required' : 'nullable';
            $rules['file'] = [
                $fileRequired,
                'file',
                'mimes:pdf,doc,docx,zip,png,jpg,jpeg',
                'max:20480',
            ];
        }

        if ($assignment->supportsMcq() && $assignment->questions->isNotEmpty()) {
            $rules['answers'] = ['required', 'array'];
            $rules['answers.*'] = ['nullable', 'integer'];
        }

        $validated = $request->validate($rules, [
            'file.required' => 'Please upload your assignment solution file.',
            'file.mimes' => 'Allowed file formats: PDF, DOC, DOCX, ZIP, PNG, JPG, JPEG.',
            'file.max' => 'Assignment file may not exceed 20 MB.',
            'answers.required' => 'Please complete the multiple-choice questions.',
        ]);

        $submission = $existing ?? new AssignmentSubmission([
            'assignment_id' => $assignment->id,
            'user_id' => $student->id,
        ]);

        $submission->notes = isset($validated['notes']) && $validated['notes'] !== ''
            ? trim((string) $validated['notes'])
            : null;

        // Handle File Upload (for `file` or `both`)
        if ($assignment->supportsFile() && $request->hasFile('file')) {
            if ($submission->file_path && Storage::disk('local')->exists($submission->file_path)) {
                Storage::disk('local')->delete($submission->file_path);
            }

            $file = $request->file('file');
            $submission->file_path = $file->store("submissions/assignments/assignment-{$assignment->id}/user-{$student->id}", 'local');
            $submission->file_original_name = $file->getClientOriginalName();
        }

        // Handle Interactive MCQ Auto-Grading (for `mcq` or `both`)
        if ($assignment->supportsMcq() && $assignment->questions->isNotEmpty()) {
            $submittedAnswers = $validated['answers'] ?? [];
            $mcqScore = 0;
            $mcqMax = 0;
            $gradedMap = [];

            foreach ($assignment->questions as $question) {
                $mcqMax += (int) $question->points;
                $chosenId = isset($submittedAnswers[$question->id]) ? (int) $submittedAnswers[$question->id] : null;
                $chosenOption = $chosenId ? $question->options->firstWhere('id', $chosenId) : null;
                $isCorrect = $chosenOption !== null && $chosenOption->is_correct;
                $earned = $isCorrect ? (int) $question->points : 0;
                $mcqScore += $earned;

                $gradedMap[$question->id] = [
                    'option_id' => $chosenOption?->id,
                    'is_correct' => $isCorrect,
                    'points_earned' => $earned,
                ];
            }

            $mcqPct = $mcqMax > 0 ? round(($mcqScore / $mcqMax) * 100, 2) : 0.0;
            $passMark = $assignment->pass_mark ?? 60;

            $submission->mcq_answers = $gradedMap;
            $submission->mcq_score = $mcqScore;
            $submission->mcq_max_score = $mcqMax;
            $submission->mcq_percentage = $mcqPct;
            $submission->mcq_passed = $mcqPct >= $passMark;
        }

        $submission->status = AssignmentSubmission::STATUS_SUBMITTED;
        $submission->submitted_at = now();
        $submission->save();

        ActivityLogger::log(
            action: 'assignment.submitted',
            subject: $submission,
            meta: [
                'assignment_id' => $assignment->id,
                'course_id' => $course->id,
                'submission_type' => $assignment->submission_type,
                'mcq_percentage' => $submission->mcq_percentage,
            ],
            actor: $student,
            ip: $request->ip()
        );

        return redirect()
            ->route('student.courses.assignments.show', [$course, $assignment])
            ->with('status', 'Your assignment has been submitted successfully.');
    }

    public function downloadOwnFile(Request $request, Course $course, Assignment $assignment): StreamedResponse
    {
        abort_unless($assignment->course_id === $course->id, 404);

        $submission = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        abort_unless($submission->hasFile(), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->file_original_name ?: 'assignment-submission'
        );
    }

    private function ensureLinkedLessonUnlocked(Request $request, Course $course, Assignment $assignment): void
    {
        if (! $assignment->lesson_id) {
            return;
        }

        $lesson = $assignment->lesson;
        if (! $lesson) {
            return;
        }

        $check = $this->dripLock->checkLessonAccess($request->user(), $course, $lesson);
        if (! $check['unlocked']) {
            abort(403, $check['reason'] ?? 'Complete the linked lesson first to access this assignment.');
        }
    }
}
