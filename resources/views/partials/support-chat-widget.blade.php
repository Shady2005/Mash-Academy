{{-- Floating Live Customer Support Chat Widget (Public Website & Student Portal) --}}
<div
    id="ma-support-chat-widget"
    data-state-url="{{ route('support-chat.state') }}"
    data-start-url="{{ route('support-chat.start') }}"
    data-send-url="{{ route('support-chat.messages.store') }}"
    data-is-authenticated="{{ auth()->check() ? '1' : '0' }}"
    style="position: fixed; bottom: 1.5rem; inset-inline-end: 1.5rem; z-index: 1045;"
>
    {{-- Floating Trigger Button --}}
    <button
        type="button"
        id="ma-support-chat-toggle"
        class="btn btn-ma-primary shadow-lg rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2 position-relative"
        aria-expanded="false"
        aria-controls="ma-support-chat-panel"
    >
        <i class="bi bi-headset fs-6" aria-hidden="true"></i>
        <span class="fw-semibold small">{{ __('messages.support_need_help') }}</span>
        <span id="ma-support-chat-unread" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
    </button>

    {{-- Popup Chat Box --}}
    <div
        id="ma-support-chat-panel"
        class="ma-card shadow-lg mt-2 d-none flex-column overflow-hidden"
        style="width: min(360px, calc(100vw - 2rem)); height: 460px; position: fixed; bottom: 5rem; inset-inline-end: 1.5rem; z-index: 1046;"
        role="dialog"
        aria-label="{{ __('messages.support_chat') }}"
    >
        <div class="p-3 d-flex justify-content-between align-items-center text-white" style="background-color: #0a192f;">
            <div>
                <div class="fw-bold small">
                    <i class="bi bi-headset me-1" aria-hidden="true"></i>{{ __('messages.support_chat') }}
                </div>
                <div style="font-size: 0.72rem; opacity: 0.8;" id="ma-support-chat-subtitle">
                    Mash Academy Customer Support
                </div>
            </div>
            <button type="button" id="ma-support-chat-close" class="btn-close btn-close-white btn-sm" aria-label="Close chat"></button>
        </div>

        {{-- Guest Start Form (Shown only to unauthenticated visitors before first message) --}}
        <form id="ma-support-guest-form" class="p-3 d-none flex-column gap-2 flex-grow-1 overflow-auto">
            <p class="small text-muted mb-1">Enter your details to start a live chat with our support team.</p>
            <div>
                <label for="support_guest_name" class="form-label small fw-semibold mb-1">Your Name *</label>
                <input type="text" id="support_guest_name" class="form-control form-control-sm" required maxlength="120" placeholder="e.g. Ahmed Hassan">
            </div>
            <div>
                <label for="support_guest_email" class="form-label small fw-semibold mb-1">Email Address *</label>
                <input type="email" id="support_guest_email" class="form-control form-control-sm" required maxlength="150" placeholder="name@example.com">
            </div>
            <div>
                <label for="support_guest_message" class="form-label small fw-semibold mb-1">How can we help? *</label>
                <textarea id="support_guest_message" rows="3" class="form-control form-control-sm" required maxlength="2000" placeholder="Write your question..."></textarea>
            </div>
            <div id="ma-support-guest-error" class="alert alert-danger small py-1 px-2 mb-0 d-none"></div>
            <button type="submit" id="ma-support-guest-submit" class="btn btn-ma-primary btn-sm mt-auto">
                <i class="bi bi-send me-1" aria-hidden="true"></i>Start Chat
            </button>
        </form>

        {{-- Active Chat Messages Feed + Send Bar --}}
        <div id="ma-support-chat-body" class="d-none flex-column flex-grow-1 overflow-hidden">
            <div id="ma-support-chat-messages" class="p-3 flex-grow-1 d-flex flex-column gap-2 overflow-auto" style="background-color: var(--ma-surface-subtle);">
                <div class="small text-muted text-center py-4" id="ma-support-chat-empty">
                    <i class="bi bi-chat-dots fs-2 d-block mb-1 opacity-50" aria-hidden="true"></i>
                    Send a message below — our support team will reply right here.
                </div>
            </div>
            <div id="ma-support-chat-error" class="alert alert-danger small mx-2 my-1 py-1 px-2 d-none"></div>
            <form id="ma-support-message-form" class="p-2 border-top">
                <div class="input-group input-group-sm">
                    <input type="text" id="ma-support-message-input" class="form-control" placeholder="Write a message..." maxlength="2000" required>
                    <button type="submit" id="ma-support-message-send" class="btn btn-ma-primary px-3">
                        <i class="bi bi-send" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
