@extends('layouts.admin')

@section('title', __('messages.ai_sandbox'))
@section('page-title', __('messages.ai_sandbox'))

@section('content')
    <div class="row g-4">
        {{-- Left Column: Course Context & Status --}}
        <div class="col-lg-4">
            <div class="ma-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 fw-bold mb-0">
                        <i class="bi bi-stars text-navy me-1" aria-hidden="true"></i>Sandbox Context
                    </h2>
                    <span class="badge bg-navy-accent text-uppercase">{{ $aiProvider }}</span>
                </div>
                <p class="small text-muted mb-3">
                    Test the AI Course Tutor exactly as a student would before turning it ON for students.
                </p>

                @if (! $hasApiKey)
                    <div class="alert alert-warning small py-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                        No API key configured yet.
                        <a href="{{ route('admin.settings.edit') }}#ai-integration" class="fw-semibold">Configure in AI Settings</a>.
                    </div>
                @endif

                @if (! $aiAdminEnabled)
                    <div class="alert alert-secondary small py-2 mb-3">
                        Dashboard AI Tools are currently toggled <strong>OFF</strong>.
                        <a href="{{ route('admin.settings.edit') }}#ai-integration" class="fw-semibold">Enable in AI Settings</a>.
                    </div>
                @endif

                <div class="mb-3">
                    <label for="sandbox_course_id" class="form-label small fw-semibold">Select Course</label>
                    <select id="sandbox_course_id" class="form-select form-select-sm">
                        @forelse ($courses as $course)
                            <option
                                value="{{ $course->id }}"
                                data-lessons='@json($course->publishedLessons->map(fn ($l) => ["id" => $l->id, "label" => "#{$l->position} — {$l->title}"])->values())'
                                @selected($selectedCourseId === $course->id)
                            >
                                {{ $course->title }}
                            </option>
                        @empty
                            <option value="">No courses available yet</option>
                        @endforelse
                    </select>
                </div>

                <div class="mb-3">
                    <label for="sandbox_lesson_id" class="form-label small fw-semibold">Active Lesson Context (Optional)</label>
                    <select id="sandbox_lesson_id" class="form-select form-select-sm">
                        <option value="">Entire Course Context</option>
                    </select>
                </div>

                <div class="border rounded-3 p-3 small" style="background-color: var(--ma-surface-subtle);">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Model:</span>
                        <code>{{ $aiModel }}</code>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Dashboard AI Tools:</span>
                        <span class="badge {{ $aiAdminEnabled ? 'bg-success' : 'bg-secondary' }}">{{ $aiAdminEnabled ? 'ON' : 'OFF' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Student AI Tutor:</span>
                        <span class="badge {{ $aiStudentEnabled ? 'bg-navy-accent' : 'bg-black-badge' }}">{{ $aiStudentEnabled ? 'ON (Visible)' : 'OFF (Hidden)' }}</span>
                    </div>
                    <a href="{{ route('admin.settings.edit') }}#ai-integration" class="btn btn-ma-outline btn-sm w-100 mt-1">
                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>Manage AI Settings &amp; Toggles
                    </a>
                </div>
            </div>
        </div>

        {{-- Right Column: Interactive Student-Preview Chat Window --}}
        <div class="col-lg-8">
            <div class="ma-card overflow-hidden d-flex flex-column" style="min-height: 540px;">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="h6 fw-bold mb-0">
                            <i class="bi bi-chat-square-text text-navy me-1" aria-hidden="true"></i>Student Preview Chat
                        </h3>
                        <div class="small text-muted">Ask in English or Arabic to inspect response quality, tone, and curriculum accuracy.</div>
                    </div>
                    <button type="button" id="sandbox-clear-btn" class="btn btn-ma-outline btn-sm">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reset Chat
                    </button>
                </div>

                <div id="sandbox-chat-messages" class="p-3 flex-grow-1 d-flex flex-column gap-3 overflow-auto" style="max-height: 400px; background-color: var(--ma-surface-subtle);">
                    <div class="small text-muted text-center py-4" id="sandbox-empty-state">
                        <i class="bi bi-stars fs-2 d-block mb-2 text-navy opacity-75" aria-hidden="true"></i>
                        Select a course on the left and ask a sample student question below.
                    </div>
                </div>

                <div id="sandbox-error" class="alert alert-danger small mx-3 mb-0 mt-2 py-2 d-none" role="alert"></div>

                <form id="sandbox-chat-form" class="p-3 border-top" data-ask-url="{{ route('admin.ai-assistant.ask') }}">
                    <div class="input-group">
                        <input
                            type="text"
                            id="sandbox-question-input"
                            class="form-control"
                            placeholder="Ask a question as a student (English or Arabic)..."
                            maxlength="1500"
                            required
                            {{ ($courses->isEmpty() || ! $aiAdminEnabled) ? 'disabled' : '' }}
                        >
                        <button
                            type="submit"
                            id="sandbox-send-btn"
                            class="btn btn-ma-primary px-4"
                            {{ ($courses->isEmpty() || ! $aiAdminEnabled) ? 'disabled' : '' }}
                        >
                            <i class="bi bi-send me-1" aria-hidden="true"></i>Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const courseSelect = document.getElementById('sandbox_course_id');
    const lessonSelect = document.getElementById('sandbox_lesson_id');
    const chatBox = document.getElementById('sandbox-chat-messages');
    const emptyState = document.getElementById('sandbox-empty-state');
    const errorBox = document.getElementById('sandbox-error');
    const form = document.getElementById('sandbox-chat-form');
    const input = document.getElementById('sandbox-question-input');
    const sendBtn = document.getElementById('sandbox-send-btn');
    const clearBtn = document.getElementById('sandbox-clear-btn');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let history = [];

    const syncLessons = () => {
        if (!courseSelect || !lessonSelect) return;
        const opt = courseSelect.options[courseSelect.selectedIndex];
        let lessons = [];
        try { lessons = JSON.parse(opt?.dataset?.lessons || '[]'); } catch { lessons = []; }
        lessonSelect.innerHTML = '<option value="">Entire Course Context</option>';
        lessons.forEach((l) => {
            const el = document.createElement('option');
            el.value = l.id;
            el.textContent = l.label;
            lessonSelect.appendChild(el);
        });
    };

    const resetChat = () => {
        history = [];
        errorBox?.classList.add('d-none');
        if (chatBox) {
            chatBox.innerHTML = '';
            if (emptyState) {
                emptyState.classList.remove('d-none');
                chatBox.appendChild(emptyState);
            }
        }
    };

    const appendBubble = (role, text) => {
        emptyState?.classList.add('d-none');
        const wrap = document.createElement('div');
        wrap.className = role === 'user' ? 'd-flex justify-content-end' : 'd-flex justify-content-start';

        const bubble = document.createElement('div');
        bubble.className = role === 'user'
            ? 'p-3 rounded-3 small bg-navy-accent text-white'
            : 'p-3 rounded-3 small border bg-body';
        bubble.style.maxWidth = '85%';
        bubble.style.whiteSpace = 'pre-wrap';
        bubble.textContent = text;

        wrap.appendChild(bubble);
        chatBox.appendChild(wrap);
        chatBox.scrollTop = chatBox.scrollHeight;
    };

    courseSelect?.addEventListener('change', () => { syncLessons(); resetChat(); });
    clearBtn?.addEventListener('click', resetChat);
    syncLessons();

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = input.value.trim();
        if (!question || !courseSelect?.value) return;

        errorBox?.classList.add('d-none');
        appendBubble('user', question);
        input.value = '';
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Thinking...';

        try {
            const res = await fetch(form.dataset.askUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    course_id: Number(courseSelect.value),
                    lesson_id: lessonSelect.value ? Number(lessonSelect.value) : null,
                    question,
                    history,
                }),
            });

            const data = await res.json();
            if (!res.ok || !data.ok) {
                errorBox.textContent = data.message || 'AI request failed.';
                errorBox.classList.remove('d-none');
            } else {
                appendBubble('assistant', data.answer);
                history.push({ role: 'user', content: question });
                history.push({ role: 'assistant', content: data.answer });
            }
        } catch {
            errorBox.textContent = 'Network error while contacting the AI Sandbox.';
            errorBox.classList.remove('d-none');
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send me-1"></i>Send';
            input.focus();
        }
    });
})();
</script>
@endpush
