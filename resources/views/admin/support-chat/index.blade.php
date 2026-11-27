@extends('layouts.admin')

@section('title', __('messages.support_chat'))
@section('page-title', __('messages.support_chat'))

@section('content')
    <div class="row g-3">
        {{-- Left Column: Conversations List --}}
        <div class="col-lg-4">
            <div class="ma-card overflow-hidden">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <a href="{{ route('admin.support-chat.index', ['status' => 'open']) }}" class="btn {{ $statusFilter === 'open' ? 'btn-ma-primary' : 'btn-ma-outline' }}">
                            Open ({{ $counts['open'] }})
                        </a>
                        <a href="{{ route('admin.support-chat.index', ['status' => 'resolved']) }}" class="btn {{ $statusFilter === 'resolved' ? 'btn-ma-primary' : 'btn-ma-outline' }}">
                            Resolved ({{ $counts['resolved'] }})
                        </a>
                        <a href="{{ route('admin.support-chat.index', ['status' => 'all']) }}" class="btn {{ $statusFilter === 'all' ? 'btn-ma-primary' : 'btn-ma-outline' }}">
                            All
                        </a>
                    </div>
                </div>

                @if ($conversations->isEmpty())
                    <div class="p-4 text-center text-muted small">
                        <i class="bi bi-chat-square-dots fs-2 d-block mb-2 opacity-50" aria-hidden="true"></i>
                        No conversations in this view.
                    </div>
                @else
                    <div class="list-group list-group-flush" style="max-height: 580px; overflow-y: auto;">
                        @foreach ($conversations as $conv)
                            @php
                                $isSelected = $activeConversation && $activeConversation->id === $conv->id;
                            @endphp
                            <a
                                href="{{ route('admin.support-chat.index', ['status' => $statusFilter, 'conversation' => $conv->id]) }}"
                                class="list-group-item list-group-item-action p-3 {{ $isSelected ? 'bg-navy-accent text-white' : 'bg-transparent' }}"
                            >
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <span class="fw-semibold small text-truncate">
                                        {{ $conv->displayName() }}
                                        @if (! $conv->user_id)
                                            <span class="badge {{ $isSelected ? 'bg-light text-dark' : 'bg-secondary' }} ms-1" style="font-size: 0.65rem;">Guest</span>
                                        @endif
                                    </span>
                                    @if (($conv->unread_count ?? 0) > 0)
                                        <span class="badge bg-danger rounded-pill">{{ $conv->unread_count }}</span>
                                    @endif
                                </div>
                                <div class="small text-truncate {{ $isSelected ? 'text-white-50' : 'text-muted' }}">
                                    {{ $conv->latestMessage?->message ?? $conv->subject ?? 'No messages yet' }}
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.72rem;">
                                    <span class="badge {{ $conv->isOpen() ? ($isSelected ? 'bg-light text-dark' : 'bg-success') : 'bg-secondary' }}">
                                        {{ ucfirst($conv->status) }}
                                    </span>
                                    <span class="{{ $isSelected ? 'text-white-50' : 'text-muted' }}">
                                        {{ $conv->last_message_at?->diffForHumans() ?? $conv->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Middle Column: Active Chat Thread --}}
        <div class="col-lg-5">
            <div class="ma-card overflow-hidden d-flex flex-column" style="min-height: 580px;">
                @if ($activeConversation)
                    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h6 fw-bold mb-0">{{ $activeConversation->displayName() }}</h2>
                            <div class="small text-muted">{{ $activeConversation->displayEmail() }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.support-chat.status', $activeConversation) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $activeConversation->isOpen() ? 'resolved' : 'open' }}">
                            <button type="submit" class="btn btn-sm {{ $activeConversation->isOpen() ? 'btn-outline-success' : 'btn-ma-primary' }}">
                                <i class="bi {{ $activeConversation->isOpen() ? 'bi-check2-circle' : 'bi-arrow-clockwise' }} me-1" aria-hidden="true"></i>
                                {{ $activeConversation->isOpen() ? 'Mark Resolved' : 'Reopen Chat' }}
                            </button>
                        </form>
                    </div>

                    <div
                        id="admin-support-chat-feed"
                        class="p-3 flex-grow-1 d-flex flex-column gap-2 overflow-auto"
                        style="max-height: 430px; background-color: var(--ma-surface-subtle);"
                        data-poll-url="{{ route('admin.support-chat.messages', $activeConversation) }}"
                    >
                        @foreach ($activeConversation->messages as $msg)
                            <div class="d-flex {{ $msg->isFromStaff() ? 'justify-content-end' : 'justify-content-start' }}">
                                <div
                                    class="p-2 px-3 rounded-3 small {{ $msg->isFromStaff() ? 'bg-navy-accent text-white' : 'border bg-body' }}"
                                    style="max-width: 85%;"
                                >
                                    <div class="fw-semibold mb-1" style="font-size: 0.72rem; opacity: 0.85;">
                                        {{ $msg->senderDisplayName() }} · {{ $msg->created_at->format('M d, H:i') }}
                                    </div>
                                    <div style="white-space: pre-wrap;">{{ $msg->message }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form
                        id="admin-support-reply-form"
                        method="POST"
                        action="{{ route('admin.support-chat.reply', $activeConversation) }}"
                        class="p-3 border-top"
                    >
                        @csrf
                        <div class="input-group">
                            <input
                                type="text"
                                id="admin-support-reply-input"
                                name="message"
                                class="form-control form-control-sm"
                                placeholder="Type your reply to {{ $activeConversation->displayName() }}..."
                                maxlength="2000"
                                required
                            >
                            <button type="submit" id="admin-support-reply-btn" class="btn btn-ma-primary btn-sm px-3">
                                <i class="bi bi-send me-1" aria-hidden="true"></i>Reply
                            </button>
                        </div>
                    </form>
                @else
                    <div class="p-5 text-center text-muted my-auto">
                        <i class="bi bi-chat-left-text fs-1 d-block mb-2 opacity-50" aria-hidden="true"></i>
                        Select a conversation from the left to view messages and reply.
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column: Student Profile & Enrollment Context --}}
        <div class="col-lg-3">
            <div class="ma-card p-3">
                <h3 class="h6 fw-bold mb-3 border-bottom pb-2">
                    <i class="bi bi-person-vcard text-navy me-1" aria-hidden="true"></i>Customer Context
                </h3>

                @if ($activeConversation && $activeConversation->user)
                    @php $cust = $activeConversation->user; @endphp
                    <dl class="small mb-3">
                        <dt class="text-muted">Name</dt>
                        <dd class="fw-semibold">{{ $cust->name }}</dd>
                        <dt class="text-muted">Email</dt>
                        <dd class="text-break">{{ $cust->email }}</dd>
                        <dt class="text-muted">Phone</dt>
                        <dd>{{ $cust->phone ?: 'Not provided' }}</dd>
                        <dt class="text-muted">Role</dt>
                        <dd><span class="badge bg-navy">{{ $cust->role->label() }}</span></dd>
                    </dl>

                    @if ($cust->isStudent())
                        <a href="{{ route('admin.students.show', $cust) }}" class="btn btn-ma-outline btn-sm w-100 mb-3">
                            <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>Full Student Profile
                        </a>
                    @endif

                    <h4 class="small fw-bold text-uppercase text-muted mb-2">Enrollments ({{ $cust->enrollments->count() }})</h4>
                    @if ($cust->enrollments->isEmpty())
                        <p class="small text-muted mb-0">No course enrollments yet.</p>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach ($cust->enrollments->take(5) as $enr)
                                <div class="border rounded-2 p-2 small">
                                    <div class="fw-semibold text-truncate">{{ $enr->course?->title ?? 'Course' }}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.72rem;">
                                        <span class="badge {{ $enr->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($enr->status) }}</span>
                                        <span class="text-muted">Exp: {{ $enr->expires_at?->format('M d, Y') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @elseif ($activeConversation)
                    <div class="small">
                        <span class="badge bg-secondary mb-2">Guest Visitor</span>
                        <div class="fw-semibold">{{ $activeConversation->guest_name ?: 'Guest' }}</div>
                        <div class="text-muted text-break">{{ $activeConversation->guest_email ?: 'No email provided' }}</div>
                        <p class="text-muted mt-3 mb-0">This visitor is not signed in with a registered student account yet.</p>
                    </div>
                @else
                    <p class="small text-muted mb-0">Open a conversation to inspect customer details and enrollments.</p>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const feed = document.getElementById('admin-support-chat-feed');
    const form = document.getElementById('admin-support-reply-form');
    const input = document.getElementById('admin-support-reply-input');
    const sendBtn = document.getElementById('admin-support-reply-btn');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!feed) return;

    const scrollBottom = () => { feed.scrollTop = feed.scrollHeight; };
    scrollBottom();

    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const renderMessages = (messages) => {
        const wasNearBottom = (feed.scrollHeight - feed.scrollTop - feed.clientHeight) < 80;
        feed.innerHTML = messages.map((m) => `
            <div class="d-flex ${m.is_staff ? 'justify-content-end' : 'justify-content-start'}">
                <div class="p-2 px-3 rounded-3 small ${m.is_staff ? 'bg-navy-accent text-white' : 'border bg-body'}" style="max-width: 85%;">
                    <div class="fw-semibold mb-1" style="font-size: 0.72rem; opacity: 0.85;">
                        ${esc(m.sender_name)} · ${esc(m.created_at)}
                    </div>
                    <div style="white-space: pre-wrap;">${esc(m.message)}</div>
                </div>
            </div>
        `).join('');
        if (wasNearBottom) scrollBottom();
    };

    const poll = async () => {
        try {
            const res = await fetch(feed.dataset.pollUrl, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok && Array.isArray(data.messages)) {
                renderMessages(data.messages);
            }
        } catch {
            // silent retry on next tick
        }
    };

    setInterval(poll, 4500);

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        sendBtn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ message: text }),
            });
            if (res.ok) {
                input.value = '';
                await poll();
                scrollBottom();
            }
        } finally {
            sendBtn.disabled = false;
            input.focus();
        }
    });
})();
</script>
@endpush
