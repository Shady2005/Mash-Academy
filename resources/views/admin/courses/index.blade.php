@extends('layouts.admin')

@section('title', __('messages.manage_courses'))
@section('page-title', __('messages.manage_courses'))

@section('content')
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Course Management</h2>
            <p class="text-muted small mb-0">
                @if (auth()->user()->isSuperAdmin())
                    Managing all Mash Academy courses. Featured on Home Page: <strong>{{ $featuredCount }} / 3</strong>.
                @else
                    Showing courses you created. You can edit or delete only your own courses.
                @endif
            </p>
        </div>
        <a href="{{ route('admin.courses.create') }}" class="btn btn-ma-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('messages.create_course') }}
        </a>
    </div>

    <div class="ma-card overflow-hidden">
        @if ($courses->isEmpty())
            <div class="p-5 text-center">
                <i class="bi bi-journal-plus fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
                <p class="text-muted mb-3">No courses created yet.</p>
                <a href="{{ route('admin.courses.create') }}" class="btn btn-ma-primary btn-sm">
                    {{ __('messages.create_course') }}
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>{{ __('messages.price') }}</th>
                            <th>Content</th>
                            <th>{{ __('messages.instructor') }}</th>
                            <th>{{ __('messages.status') }}</th>
                            <th class="text-end">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($course->thumbnailUrl())
                                            <img src="{{ $course->thumbnailUrl() }}" alt="" class="rounded border" style="width: 64px; height: 42px; object-fit: cover;">
                                        @else
                                            <div class="rounded d-flex align-items-center justify-content-center text-white" style="width: 64px; height: 42px; background: linear-gradient(135deg, #000000, #1e3a8a);">
                                                <i class="bi bi-play-btn" aria-hidden="true"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $course->title }}</div>
                                            @if ($course->title_ar)
                                                <div class="small text-muted" dir="rtl">{{ $course->title_ar }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="fw-semibold text-nowrap">{{ $course->formattedPrice() }}</td>
                                <td class="small text-muted text-nowrap">
                                    <div>{{ $course->lessons_count }} lessons · {{ $course->quizzes_count }} quizzes · {{ $course->assignments_count }} assignments</div>
                                    @if ($course->finalProject)
                                        <span class="badge bg-navy-accent mt-1">Final Project ({{ $course->finalProject->required_completion_percentage }}%)</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <div>{{ $course->creator?->name ?? '—' }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">{{ $course->creator?->role?->shortLabel() }}</div>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge {{ $course->is_published ? 'bg-navy-accent' : 'bg-secondary' }}">
                                        {{ $course->is_published ? __('messages.published') : __('messages.draft') }}
                                    </span>
                                    @if ($course->is_featured)
                                        <span class="badge bg-black-badge">
                                            <i class="bi bi-star-fill me-1" aria-hidden="true"></i>#{{ $course->featured_order ?? '★' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('admin.courses.lessons.index', $course) }}" class="btn btn-ma-primary btn-sm" title="{{ __('messages.manage_content') }}">
                                            <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Content
                                        </a>
                                        <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ma-outline btn-sm" title="{{ __('messages.edit') }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </a>
                                        @can('delete', $course)
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteCourseModal{{ $course->id }}" title="{{ __('messages.delete') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($courses->hasPages())
                <div class="p-3 border-top">{{ $courses->links() }}</div>
            @endif
        @endif
    </div>

    {{-- Delete Confirmation Modals --}}
    @foreach ($courses as $course)
        @can('delete', $course)
            <div class="modal fade" id="deleteCourseModal{{ $course->id }}" tabindex="-1" aria-labelledby="deleteCourseLabel{{ $course->id }}" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.courses.destroy', $course) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h3 class="modal-title h6 fw-bold text-danger" id="deleteCourseLabel{{ $course->id }}">{{ __('messages.delete_course') }}</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">Are you sure you want to delete <strong>{{ $course->title }}</strong>?</p>
                                <p class="small text-muted mb-0">
                                    This permanently removes all its lessons, uploaded videos, quizzes, flexible assignments, Final Project, and thumbnail.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                                <button type="submit" class="btn btn-danger btn-sm">Yes, Delete Course</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    @endforeach
@endsection
