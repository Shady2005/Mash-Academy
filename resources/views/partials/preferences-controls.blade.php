@php
    $nextLocale = ($currentLocale ?? 'en') === 'ar' ? 'en' : 'ar';
    $nextLocaleLabel = $nextLocale === 'ar' ? 'عربي' : 'EN';
    $isDark = ($currentTheme ?? 'light') === 'dark';
@endphp

<div class="d-inline-flex align-items-center gap-1">
    <form method="POST" action="{{ route('preferences.locale') }}" class="d-inline">
        @csrf
        <input type="hidden" name="locale" value="{{ $nextLocale }}">
        <button type="submit" class="btn btn-ma-pref btn-sm px-2" title="Switch language">
            <i class="bi bi-translate me-1" aria-hidden="true"></i>{{ $nextLocaleLabel }}
        </button>
    </form>

    <form method="POST" action="{{ route('preferences.theme') }}" class="d-inline">
        @csrf
        <input type="hidden" name="theme" value="{{ $isDark ? 'light' : 'dark' }}">
        <button
            type="submit"
            class="btn btn-ma-pref btn-sm px-2"
            aria-label="{{ $isDark ? __('messages.switch_to_light') : __('messages.switch_to_dark') }}"
            title="{{ $isDark ? __('messages.switch_to_light') : __('messages.switch_to_dark') }}"
        >
            @if ($isDark)
                <i class="bi bi-sun-fill" aria-hidden="true"></i>
            @else
                <i class="bi bi-moon-stars-fill" aria-hidden="true"></i>
            @endif
        </button>
    </form>
</div>
