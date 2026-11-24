@php
    $unreadCount = (int) ($navUnreadNotificationsCount ?? 0);
    $recentNotifications = $navRecentNotifications ?? collect();
@endphp

<div class="dropdown">
    <button
        type="button"
        class="btn btn-ma-pref btn-sm position-relative px-2"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="{{ __('messages.notifications') }}"
        title="{{ __('messages.notifications') }}"
    >
        <i class="bi bi-bell-fill" aria-hidden="true"></i>
        @if ($unreadCount > 0)
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow p-0" style="width: 340px; max-width: 92vw;">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <span class="fw-bold small">{{ __('messages.notifications') }}</span>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none small fw-semibold">
                        {{ __('messages.mark_all_read') }}
                    </button>
                </form>
            @endif
        </div>

        <div style="max-height: 360px; overflow-y: auto;">
            @forelse ($recentNotifications as $notif)
                @php
                    $data = $notif->data ?? [];
                    $isUnread = $notif->read_at === null;
                @endphp
                <form method="POST" action="{{ route('notifications.read', $notif->id) }}" class="border-bottom">
                    @csrf
                    <button
                        type="submit"
                        class="dropdown-item py-2 px-3 text-wrap {{ $isUnread ? 'fw-semibold' : 'text-muted' }}"
                        style="white-space: normal; {{ $isUnread ? 'background-color: var(--ma-surface-subtle);' : '' }}"
                    >
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi {{ $data['icon'] ?? 'bi-bell' }} text-navy mt-1" aria-hidden="true"></i>
                            <div class="flex-grow-1">
                                <div class="small lh-sm">{{ $data['title'] ?? 'Notification' }}</div>
                                <div class="small text-muted fw-normal mt-1 lh-sm">{{ $data['message'] ?? '' }}</div>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;">{{ $notif->created_at->diffForHumans() }}</div>
                            </div>
                            @if ($isUnread)
                                <span class="badge rounded-pill bg-navy-accent p-1 mt-1" aria-hidden="true"></span>
                            @endif
                        </div>
                    </button>
                </form>
            @empty
                <div class="p-4 text-center small text-muted">
                    <i class="bi bi-bell-slash d-block fs-4 mb-1 opacity-50" aria-hidden="true"></i>
                    {{ __('messages.no_notifications') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
