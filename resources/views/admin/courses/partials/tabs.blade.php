<div class="ma-card p-3 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <a href="{{ route('admin.courses.index') }}" class="small text-decoration-none text-muted">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('messages.back_to_courses') }}
            </a>
            <h2 class="h5 fw-bold mb-0 mt-1">{{ $course->title }}</h2>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge {{ $course->is_published ? 'bg-navy-accent' : 'bg-secondary' }}">
                {{ $course->is_published ? __('messages.published') : __('messages.draft') }}
            </span>
            @if ($course->is_featured)
                <span class="badge bg-black-badge">
                    <i class="bi bi-star-fill me-1" aria-hidden="true"></i>{{ __('messages.featured') }}{{ $course->featured_order ? ' #'.$course->featured_order : '' }}
                </span>
            @endif
            @if ($course->is_published)
                <a href="{{ route('courses.show', $course) }}" target="_blank" rel="noopener" class="btn btn-ma-outline btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Public View
                </a>
            @endif
        </div>
    </div>

    <ul class="nav ma-course-tabs flex-wrap gap-2">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.edit') ? 'active' : '' }}" href="{{ route('admin.courses.edit', $course) }}">
                <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>{{ __('messages.course_details') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.lessons.*') ? 'active' : '' }}" href="{{ route('admin.courses.lessons.index', $course) }}">
                <i class="bi bi-camera-video me-1" aria-hidden="true"></i>{{ __('messages.lessons_videos') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.quizzes.*') ? 'active' : '' }}" href="{{ route('admin.courses.quizzes.index', $course) }}">
                <i class="bi bi-patch-question me-1" aria-hidden="true"></i>{{ __('messages.quizzes') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.assignments.*') ? 'active' : '' }}" href="{{ route('admin.courses.assignments.index', $course) }}">
                <i class="bi bi-file-earmark-check me-1" aria-hidden="true"></i>{{ __('messages.assignments') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.final-project.*') ? 'active' : '' }}" href="{{ route('admin.courses.final-project.edit', $course) }}">
                <i class="bi bi-award me-1" aria-hidden="true"></i>{{ __('messages.final_project') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.courses.submissions.*') ? 'active' : '' }}" href="{{ route('admin.courses.submissions.index', $course) }}">
                <i class="bi bi-inbox me-1" aria-hidden="true"></i>{{ __('messages.submissions_review') }}
            </a>
        </li>
    </ul>
</div>
