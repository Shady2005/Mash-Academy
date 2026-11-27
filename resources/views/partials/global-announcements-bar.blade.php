@if (isset($globalAnnouncements) && $globalAnnouncements->isNotEmpty())
    @php
        $barLocale = $currentLocale ?? app()->getLocale();
        $topAnn = $globalAnnouncements->first();
    @endphp
    <div
        id="ma-global-announcement-bar"
        data-announcement-id="{{ $topAnn->id }}"
        class="py-2 px-3 border-bottom small {{ $topAnn->isImportant() ? 'bg-navy text-white' : '' }}"
        style="{{ $topAnn->isImportant() ? 'background-color: #0a192f !important;' : 'background-color: var(--ma-surface-subtle);' }}"
        role="region"
        aria-label="{{ __('messages.global_announcements') }}"
    >
        <div class="container d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2 min-w-0">
                <span class="badge {{ $topAnn->isImportant() ? 'bg-danger' : 'bg-navy-accent' }}">
                    <i class="bi bi-megaphone-fill me-1" aria-hidden="true"></i>
                    {{ $topAnn->isImportant() ? 'Important' : __('messages.announcements') }}
                </span>
                <strong class="text-truncate">{{ $topAnn->localizedTitle($barLocale) }}:</strong>
                <span class="{{ $topAnn->isImportant() ? 'text-white-50' : 'text-muted' }} text-truncate">
                    {{ \Illuminate\Support\Str::limit($topAnn->localizedBody($barLocale), 140) }}
                </span>
            </div>
            <button
                type="button"
                class="btn-close {{ $topAnn->isImportant() ? 'btn-close-white' : '' }} btn-sm flex-shrink-0"
                data-dismiss-announcement="{{ $topAnn->id }}"
                aria-label="Dismiss announcement"
            ></button>
        </div>
    </div>
@endif
