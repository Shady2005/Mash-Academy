@extends('layouts.admin')

@section('title', __('messages.submissions_review'))
@section('page-title', __('messages.submissions_review'))

@section('content')
    @include('admin.courses.partials.tabs', ['course' => $course])

    {{-- 1. Assignment Submissions --}}
    <div class="ma-card overflow-hidden mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="h6 fw-bold mb-0">Assignment Submissions ({{ $assignmentSubmissions->count() }})</h3>
        </div>
        @if ($assignmentSubmissions->isEmpty())
            <div class="p-4 text-center text-muted small">No student assignment submissions yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Assignment</th>
                            <th>MCQ / File</th>
                            <th>Status &amp; Grade</th>
                            <th>Submitted</th>
                            <th class="text-end">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignmentSubmissions as $sub)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $sub->user?->name ?? 'Deleted' }}</div>
                                    <div class="text-muted">{{ $sub->user?->email }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $sub->assignment?->title }}</div>
                                    <span class="badge bg-black-badge">{{ $sub->assignment?->typeLabel() }}</span>
                                </td>
                                <td>
                                    @if ($sub->mcq_score !== null)
                                        <div>MCQ: <strong>{{ $sub->mcq_score }}/{{ $sub->mcq_max_score }}</strong> ({{ round((float) $sub->mcq_percentage, 1) }}%)</div>
                                    @endif
                                    @if ($sub->hasFile())
                                        <a href="{{ route('admin.courses.assignment-submissions.download', [$course, $sub]) }}" class="btn btn-ma-outline btn-sm mt-1">
                                            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $sub->file_original_name ?: 'File' }}
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $sub->status === 'reviewed' ? 'bg-success' : 'bg-navy-accent' }}">{{ ucfirst($sub->status) }}</span>
                                    @if ($sub->grade !== null)
                                        <div class="fw-bold mt-1">{{ $sub->grade }} / 100</div>
                                    @endif
                                </td>
                                <td class="text-muted text-nowrap">{{ $sub->submitted_at?->format('M d, Y H:i') }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-ma-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewAssignmentModal{{ $sub->id }}">
                                        Grade / Review
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 2. Final Project Submissions --}}
    <div class="ma-card overflow-hidden mb-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="h6 fw-bold mb-0">Final Project Submissions ({{ $finalProjectSubmissions->count() }})</h3>
        </div>
        @if ($finalProjectSubmissions->isEmpty())
            <div class="p-4 text-center text-muted small">No Final Project submissions yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Deliverable File</th>
                            <th>Status &amp; Grade</th>
                            <th>Submitted</th>
                            <th class="text-end">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($finalProjectSubmissions as $fpSub)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $fpSub->user?->name ?? 'Deleted' }}</div>
                                    <div class="text-muted">{{ $fpSub->user?->email }}</div>
                                </td>
                                <td>
                                    <a href="{{ route('admin.courses.final-project-submissions.download', [$course, $fpSub]) }}" class="btn btn-ma-outline btn-sm">
                                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ $fpSub->file_original_name }}
                                    </a>
                                    @if ($fpSub->notes)
                                        <div class="text-muted mt-1">{{ $fpSub->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $fpSub->status === 'reviewed' ? 'bg-success' : 'bg-navy-accent' }}">{{ ucfirst($fpSub->status) }}</span>
                                    @if ($fpSub->grade !== null)
                                        <div class="fw-bold mt-1">{{ $fpSub->grade }} / 100</div>
                                    @endif
                                </td>
                                <td class="text-muted text-nowrap">{{ $fpSub->submitted_at->format('M d, Y H:i') }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-ma-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewFpModal{{ $fpSub->id }}">
                                        Grade / Review
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 3. Quiz Attempts Overview --}}
    <div class="ma-card overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="h6 fw-bold mb-0">Recent Quiz Attempts ({{ $quizAttempts->count() }})</h3>
        </div>
        @if ($quizAttempts->isEmpty())
            <div class="p-4 text-center text-muted small">No student quiz attempts yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Quiz</th>
                            <th>Score</th>
                            <th>Result</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quizAttempts as $attempt)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $attempt->user?->name ?? 'Deleted' }}</div>
                                    <div class="text-muted">{{ $attempt->user?->email }}</div>
                                </td>
                                <td class="fw-semibold">{{ $attempt->quiz?->title }}</td>
                                <td>{{ $attempt->score }} / {{ $attempt->max_score }} ({{ round((float) $attempt->percentage, 1) }}%)</td>
                                <td>
                                    <span class="badge {{ $attempt->passed ? 'bg-success' : 'bg-danger' }}">
                                        {{ $attempt->passed ? 'Passed' : 'Failed' }}
                                    </span>
                                </td>
                                <td class="text-muted text-nowrap">{{ $attempt->submitted_at->format('M d, Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 4. Course Q&A (Instructor Reply Thread) --}}
    <div class="ma-card overflow-hidden mt-4">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="h6 fw-bold mb-0">
                <i class="bi bi-chat-dots-fill text-navy me-1" aria-hidden="true"></i>{{ __('messages.course_qa') }} ({{ ($courseQuestions ?? collect())->count() }})
            </h3>
        </div>
        @if (($courseQuestions ?? collect())->isEmpty())
            <div class="p-4 text-center text-muted small">No student questions posted for this course yet.</div>
        @else
            <div class="p-3 d-flex flex-column gap-3">
                @foreach ($courseQuestions as $q)
                    <div class="border rounded-3 p-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <div class="small fw-semibold">
                                {{ $q->user?->name ?? 'Student' }}
                                <span class="text-muted fw-normal">({{ $q->user?->email }})</span>
                                @if ($q->lesson)
                                    <span class="badge bg-secondary ms-1">Lesson #{{ $q->lesson->position }}</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge {{ $q->is_resolved ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $q->is_resolved ? 'Answered' : 'Awaiting Reply' }}
                                </span>
                                <span class="small text-muted">{{ $q->created_at->format('M d, Y H:i') }}</span>
                            </div>
                        </div>
                        <p class="small mb-2" style="white-space: pre-line;">{{ $q->question }}</p>

                        @if ($q->replies->isNotEmpty())
                            <div class="ps-3 border-start border-2 d-flex flex-column gap-2 mb-2" style="border-inline-start-color: #1e3a8a !important;">
                                @foreach ($q->replies as $rep)
                                    <div class="p-2 rounded bg-body-tertiary small">
                                        <div class="fw-semibold">
                                            {{ $rep->user?->name ?? 'User' }}
                                            @if ($rep->is_instructor_reply)
                                                <span class="badge bg-navy-accent ms-1">{{ __('messages.instructor_reply') }}</span>
                                            @endif
                                        </div>
                                        <div style="white-space: pre-line;">{{ $rep->reply }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.courses.questions.reply', [$course, $q]) }}" class="d-flex gap-2 mt-2">
                            @csrf
                            <input type="text" name="reply" required minlength="2" maxlength="2000" class="form-control form-control-sm" placeholder="Write instructor reply (notifies the student)...">
                            <button type="submit" class="btn btn-ma-primary btn-sm text-nowrap">Reply</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Assignment Review Modals --}}
    @foreach ($assignmentSubmissions as $sub)
        <div class="modal fade" id="reviewAssignmentModal{{ $sub->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.courses.assignment-submissions.review', [$course, $sub]) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h4 class="modal-title h6 fw-bold">Review Assignment — {{ $sub->user?->name }}</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if ($sub->notes)
                                <div class="p-2 rounded border mb-3 small"><strong>Student Notes:</strong> {{ $sub->notes }}</div>
                            @endif
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Grade (0–100, Optional)</label>
                                <input type="number" step="0.5" min="0" max="100" name="grade" value="{{ $sub->grade }}" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Feedback</label>
                                <textarea name="feedback" rows="3" class="form-control">{{ $sub->feedback }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">Save Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Final Project Review Modals --}}
    @foreach ($finalProjectSubmissions as $fpSub)
        <div class="modal fade" id="reviewFpModal{{ $fpSub->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.courses.final-project-submissions.review', [$course, $fpSub]) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h4 class="modal-title h6 fw-bold">Review Final Project — {{ $fpSub->user?->name }}</h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Grade (0–100, Optional)</label>
                                <input type="number" step="0.5" min="0" max="100" name="grade" value="{{ $fpSub->grade }}" class="form-control">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Feedback</label>
                                <textarea name="feedback" rows="3" class="form-control">{{ $fpSub->feedback }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">Save Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
