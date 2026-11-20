@php
    $academyName = \App\Models\AcademySetting::localized('academy_name', $currentLocale ?? app()->getLocale(), $academySettings ?? null) ?: 'Mash Academy';
    $initials = collect(explode(' ', trim($academyName)))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: 'MA';
@endphp

<span class="d-inline-flex align-items-center gap-2 text-decoration-none">
    @if (! empty($academyLogoUrl))
        <img src="{{ $academyLogoUrl }}" alt="{{ $academyName }} logo" class="ma-brand-logo">
    @else
        <span class="ma-brand-emblem" aria-hidden="true">{{ $initials }}</span>
    @endif
    <span class="ma-brand-name {{ $nameClass ?? '' }}">{{ $academyName }}</span>
</span>
