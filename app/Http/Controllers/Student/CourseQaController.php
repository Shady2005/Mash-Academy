<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\CourseQuestionReply;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourseQaController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $notifications
    ) {}

    public function storeQuestion(Request $request, Course $course): RedirectResponse
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_course_qa'), 404);

        $validated = $request->validate([
            'lesson_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $lessonId = null;
        if (! empty($validated['lesson_id'])) {
            $lessonExists = $course->lessons()->where('id', (int) $validated['lesson_id'])->exists();
            if ($lessonExists) {
                $lessonId = (int) $validated['lesson_id'];
            }
        }

        $q = CourseQuestion::query()->create([
            'course_id' => $course->id,
            'lesson_id' => $lessonId,
            'user_id' => $request->user()->id,
            'question' => trim((string) $validated['question']),
            'is_resolved' => false,
        ]);

        ActivityLogger::log(
            action: 'course_qa.question_posted',
            subject: $q,
            meta: ['course_id' => $course->id, 'lesson_id' => $lessonId],
            actor: $request->user(),
            ip: $request->ip()
        );

        return back()->with('status', 'Your question has been posted to the course Q&A.');
    }

    public function storeReply(Request $request, Course $course, CourseQuestion $question): RedirectResponse
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_course_qa'), 404);
        abort_unless($question->course_id === $course->id, 404);

        $user = $request->user();

        // Students can reply to their own threads or enrolled course discussions; Teachers only on their own courses; Super Admins anywhere
        if ($user->isTeacher()) {
            abort_unless($course->created_by === $user->id, 403);
        }

        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $isInstructorReply = $user->isSuperAdmin() || ($user->isTeacher() && $course->created_by === $user->id);

        $reply = CourseQuestionReply::query()->create([
            'course_question_id' => $question->id,
            'user_id' => $user->id,
            'reply' => trim((string) $validated['reply']),
            'is_instructor_reply' => $isInstructorReply,
        ]);

        if ($isInstructorReply) {
            $question->is_resolved = true;
            $question->save();
            $this->notifications->notifyCourseQuestionReplied($question, $user);
        }

        ActivityLogger::log(
            action: 'course_qa.reply_posted',
            subject: $reply,
            meta: ['course_id' => $course->id, 'question_id' => $question->id, 'instructor_reply' => $isInstructorReply],
            actor: $user,
            ip: $request->ip()
        );

        return back()->with('status', 'Reply posted successfully.');
    }
}
