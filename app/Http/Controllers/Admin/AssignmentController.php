<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignmentQuestionRequest;
use App\Http\Requests\Admin\AssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentQuestion;
use App\Models\Course;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('manageContent', $course);

        $assignments = $course->assignments()
            ->with(['lesson', 'questions.options'])
            ->get();

        $lessons = $course->lessons()->get();

        return view('admin.courses.assignments.index', [
            'course' => $course,
            'assignments' => $assignments,
            'lessons' => $lessons,
        ]);
    }

    public function store(AssignmentRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('manageContent', $course);

        $validated = $request->validated();

        $assignment = new Assignment();
        $assignment->course_id = $course->id;
        $assignment->lesson_id = $validated['lesson_id'] ?? null;
        $assignment->title = trim((string) $validated['title']);
        $assignment->submission_type = $validated['submission_type'];
        $assignment->instructions = isset($validated['instructions']) && $validated['instructions'] !== '' ? trim((string) $validated['instructions']) : null;
        $assignment->pass_mark = $assignment->supportsMcq() ? (int) ($validated['pass_mark'] ?? 60) : null;
        $assignment->due_at = $validated['due_at'] ?? null;
        $assignment->created_by = $request->user()->id;
        $assignment->save();

        ActivityLogger::log(
            action: 'assignment.created',
            subject: $assignment,
            meta: [
                'course_id' => $course->id,
                'title' => $assignment->title,
                'submission_type' => $assignment->submission_type,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.assignments.index', $course)
            ->with('status', "Assignment \"{$assignment->title}\" ({$assignment->typeLabel()}) created.");
    }

    public function update(AssignmentRequest $request, Course $course, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $validated = $request->validated();

        $assignment->lesson_id = $validated['lesson_id'] ?? null;
        $assignment->title = trim((string) $validated['title']);
        $assignment->submission_type = $validated['submission_type'];
        $assignment->instructions = isset($validated['instructions']) && $validated['instructions'] !== '' ? trim((string) $validated['instructions']) : null;
        $assignment->pass_mark = $assignment->supportsMcq() ? (int) ($validated['pass_mark'] ?? 60) : null;
        $assignment->due_at = $validated['due_at'] ?? null;
        $assignment->save();

        return redirect()
            ->route('admin.courses.assignments.index', $course)
            ->with('status', "Assignment \"{$assignment->title}\" updated.");
    }

    public function destroy(Request $request, Course $course, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($assignment->course_id === $course->id, 404);

        $title = $assignment->title;
        $assignment->delete();

        ActivityLogger::log(
            action: 'assignment.deleted',
            subject: $course,
            meta: ['course_id' => $course->id, 'assignment_title' => $title],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.courses.assignments.index', $course)
            ->with('status', "Assignment \"{$title}\" deleted.");
    }

    public function storeQuestion(AssignmentQuestionRequest $request, Course $course, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($assignment->course_id === $course->id, 404);

        if (! $assignment->supportsMcq()) {
            return redirect()
                ->route('admin.courses.assignments.index', $course)
                ->withErrors(['submission_type' => 'This assignment is set to File Upload only. Switch it to MCQ or Combined to add multiple-choice questions.']);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($assignment, $validated): void {
            $nextPosition = ((int) $assignment->questions()->max('position')) + 1;

            $question = $assignment->questions()->create([
                'question_text' => trim((string) $validated['question_text']),
                'position' => $nextPosition,
                'points' => (int) $validated['points'],
            ]);

            $correctIndex = (int) $validated['correct_option'];

            foreach ($validated['options'] as $idx => $text) {
                $clean = trim((string) $text);
                if ($clean === '') {
                    continue;
                }

                $question->options()->create([
                    'option_text' => $clean,
                    'is_correct' => ((int) $idx) === $correctIndex,
                ]);
            }
        });

        return redirect()
            ->route('admin.courses.assignments.index', $course)
            ->with('status', 'MCQ question added to assignment.');
    }

    public function destroyQuestion(Course $course, Assignment $assignment, AssignmentQuestion $question): RedirectResponse
    {
        Gate::authorize('manageContent', $course);
        abort_unless($assignment->course_id === $course->id && $question->assignment_id === $assignment->id, 404);

        $question->delete();

        return redirect()
            ->route('admin.courses.assignments.index', $course)
            ->with('status', 'Assignment MCQ question removed.');
    }
}
