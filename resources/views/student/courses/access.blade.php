@extends('layouts.student')

@section('title', $course->localizedTitle($currentLocale ?? app()->getLocale()))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <div class="ma-card p-4 mb-4 border-start border-4" style="border-inline-start-color: #1e3a8a !important;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge bg-navy-accent">
                        <i class="bi bi-unlock-fill me-1" aria-hidden="true"></i>{{ __('messages.unlocked') }}
                    </span>
                    @if ($enrollment)
                        <span class="badge bg-black-badge">
                            {{ __('messages.expires_on', ['date' => $enrollment->expires_at->format('M d, Y H:i')]) }}
                        </span>
                        <span class="badge bg-secondary">
                            {{ __('messages.days_remaining', ['days' => $enrollment->remainingDays()]) }}
                        </span>
                    @elseif (auth()->user()->isSuperAdmin())
                        <span class="badge bg-black-badge">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('messages.super_admin_preview') }}
                        </span>
                    @endif
                </div>
                <h1 class="h4 fw-bold mb-1">{{ $course->localizedTitle($locale) }}</h1>
                <p class="text-muted small mb-0">{{ $course->localizedShortDescription($locale) }}</p>
            </div>
            <a href="{{ route('student.courses.index') }}" class="btn btn-ma-outline btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.browse_courses') }}
            </a>
        </div>
    </div>

    <div class="alert alert-success small d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-play-circle-fill fs-5" aria-hidden="true"></i>
            <div>
                Your enrollment is <strong>Active</strong>. Launch the interactive sequential Course Player to watch lessons (100% full watch), take quizzes, submit flexible assignments, and unlock the 95%-gated Final Project.
            </div>
        </div>
        <a href="{{ route('student.courses.learn', $course) }}" class="btn btn-ma-primary btn-sm">
            <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('messages.continue_learning') }}
        </a>
    </div>

    <div class="ma-card p-4">
        <h2 class="h6 fw-bold mb-3">{{ __('messages.curriculum_outline') }} ({{ $course->publishedLessons->count() }})</h2>
        @if ($course->publishedLessons->isEmpty())
            <p class="text-muted small mb-0">No lessons published yet.</p>
        @else
            <div class="list-group list-group-flush">
                @foreach ($course->publishedLessons as $lesson)
                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center bg-transparent">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-navy rounded-pill">{{ $lesson->position }}</span>
                            <div>
                                <div class="fw-semibold">{{ $lesson->title }}</div>
                                @if ($lesson->description)
                                    <div class="small text-muted">{{ $lesson->description }}</div>
                                @endif
                            </div>
                        </div>
                        <span class="small text-muted">{{ $lesson->formattedDuration() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
