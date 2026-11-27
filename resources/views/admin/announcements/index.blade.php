@extends('layouts.admin')

@section('title', __('messages.announcements'))
@section('page-title', __('messages.announcements'))

@section('content')
    @php
        $locale = $currentLocale ?? app()->getLocale();
    @endphp

    <div class="row g-4">
        {{-- Create New Announcement --}}
        <div class="col-lg-4">
            <div class="ma-card p-4">
                <h2 class="h6 fw-bold mb-1">
                    <i class="bi bi-megaphone-fill text-navy me-1" aria-hidden="true"></i>New Announcement
                </h2>
                <p class="small text-muted mb-3">
                    @if ($canCreateGlobal)
                        Post a <strong>Global Site-Wide</strong> announcement or target a specific course.
                    @else
                        Post an announcement for students enrolled in your courses.
                    @endif
                </p>

                <form method="POST" action="{{ route('admin.announcements.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="ann_course_id" class="form-label small fw-semibold">Target Scope <span class="text-danger">*</span></label>
                        <select id="ann_course_id" name="course_id" class="form-select form-select-sm @error('course_id') is-invalid @enderror" {{ $canCreateGlobal ? '' : 'required' }}>
                            @if ($canCreateGlobal)
                                <option value="">🌐 Global Site-Wide Announcement</option>
                            @else
                                <option value="">— Select Your Course —</option>
                            @endif
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>
                                    Course: {{ $course->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="ann_title_en" class="form-label small fw-semibold">Title (English) <span class="text-danger">*</span></label>
                        <input type="text" id="ann_title_en" name="title_en" value="{{ old('title_en') }}" class="form-control form-control-sm @error('title_en') is-invalid @enderror" required maxlength="255">
                        @error('title_en')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="ann_title_ar" class="form-label small fw-semibold">Title (Arabic / العنوان بالعربية)</label>
                        <input type="text" id="ann_title_ar" name="title_ar" dir="rtl" value="{{ old('title_ar') }}" class="form-control form-control-sm" maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label for="ann_body_en" class="form-label small fw-semibold">Message Body (English) <span class="text-danger">*</span></label>
                        <textarea id="ann_body_en" name="body_en" rows="3" class="form-control form-control-sm @error('body_en') is-invalid @enderror" required maxlength="4000">{{ old('body_en') }}</textarea>
                        @error('body_en')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="ann_body_ar" class="form-label small fw-semibold">Message Body (Arabic / نص الإعلان)</label>
                        <textarea id="ann_body_ar" name="body_ar" dir="rtl" rows="3" class="form-control form-control-sm" maxlength="4000">{{ old('body_ar') }}</textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="ann_priority" class="form-label small fw-semibold">Priority</label>
                            <select id="ann_priority" name="priority" class="form-select form-select-sm">
                                <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                <option value="important" @selected(old('priority') === 'important')>Important</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="ann_expires_at" class="form-label small fw-semibold">Expires At (Optional)</label>
                            <input type="datetime-local" id="ann_expires_at" name="expires_at" value="{{ old('expires_at') }}" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="ann_is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="form-check-label small fw-semibold" for="ann_is_active">Active immediately</label>
                    </div>

                    <button type="submit" class="btn btn-ma-primary btn-sm w-100" {{ (! $canCreateGlobal && $courses->isEmpty()) ? 'disabled' : '' }}>
                        <i class="bi bi-send me-1" aria-hidden="true"></i>Publish Announcement
                    </button>
                </form>
            </div>
        </div>

        {{-- Announcements List --}}
        <div class="col-lg-8">
            <div class="ma-card p-3 mb-3">
                <form method="GET" action="{{ route('admin.announcements.index') }}" class="row g-2 align-items-center">
                    @if ($canCreateGlobal)
                        <div class="col-sm-4">
                            <select name="scope" class="form-select form-select-sm">
                                <option value="">All Scopes</option>
                                <option value="global" @selected($scopeFilter === 'global')>Global Site-Wide</option>
                                <option value="course" @selected($scopeFilter === 'course')>Course-Specific</option>
                            </select>
                        </div>
                    @endif
                    <div class="col-sm-4">
                        <select name="priority" class="form-select form-select-sm">
                            <option value="">All Priorities</option>
                            <option value="important" @selected($priorityFilter === 'important')>Important Only</option>
                            <option value="normal" @selected($priorityFilter === 'normal')>Normal Only</option>
                        </select>
                    </div>
                    <div class="col-sm-4 d-flex gap-2">
                        <button type="submit" class="btn btn-ma-primary btn-sm">Filter</button>
                        <a href="{{ route('admin.announcements.index') }}" class="btn btn-ma-outline btn-sm">Reset</a>
                    </div>
                </form>
            </div>

            <div class="ma-card overflow-hidden">
                @if ($announcements->isEmpty())
                    <div class="p-5 text-center text-muted small">
                        <i class="bi bi-megaphone fs-1 d-block mb-2 opacity-50" aria-hidden="true"></i>
                        No announcements found.
                    </div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach ($announcements as $ann)
                            <div class="list-group-item p-3 bg-transparent">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                            @if ($ann->isGlobal())
                                                <span class="badge bg-navy-accent">Global Site-Wide</span>
                                            @else
                                                <span class="badge bg-black-badge">Course: {{ $ann->course?->title ?? 'Deleted' }}</span>
                                            @endif
                                            @if ($ann->isImportant())
                                                <span class="badge bg-danger">Important</span>
                                            @else
                                                <span class="badge bg-secondary">Normal</span>
                                            @endif
                                            @if ($ann->isExpired())
                                                <span class="badge bg-warning text-dark">Expired</span>
                                            @elseif ($ann->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </div>
                                        <h3 class="h6 fw-bold mb-1">{{ $ann->localizedTitle($locale) }}</h3>
                                        <p class="small text-muted mb-1" style="white-space: pre-line;">{{ $ann->localizedBody($locale) }}</p>
                                        <div class="text-muted" style="font-size: 0.76rem;">
                                            By <strong>{{ $ann->author?->name ?? 'Staff' }}</strong> ({{ $ann->author?->role?->shortLabel() ?? 'Staff' }})
                                            · {{ $ann->created_at->format('M d, Y H:i') }}
                                            @if ($ann->expires_at)
                                                · Expires: {{ $ann->expires_at->format('M d, Y H:i') }}
                                            @endif
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#editAnnModal{{ $ann->id }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.announcements.toggle', $ann) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ $ann->is_active ? 'Deactivate' : 'Activate' }}">
                                                <i class="bi {{ $ann->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.announcements.destroy', $ann) }}" onsubmit="return confirm('Delete this announcement?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($announcements->hasPages())
                        <div class="p-3 border-top">{{ $announcements->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Edit Modals --}}
    @foreach ($announcements as $ann)
        <div class="modal fade" id="editAnnModal{{ $ann->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.announcements.update', $ann) }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h3 class="modal-title h6 fw-bold">Edit Announcement</h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Target Scope</label>
                                    <select name="course_id" class="form-select form-select-sm" {{ $canCreateGlobal ? '' : 'required' }}>
                                        @if ($canCreateGlobal)
                                            <option value="" @selected($ann->course_id === null)>🌐 Global Site-Wide</option>
                                        @endif
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->id }}" @selected($ann->course_id === $course->id)>Course: {{ $course->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Priority</label>
                                    <select name="priority" class="form-select form-select-sm">
                                        <option value="normal" @selected($ann->priority === 'normal')>Normal</option>
                                        <option value="important" @selected($ann->priority === 'important')>Important</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Expires At</label>
                                    <input type="datetime-local" name="expires_at" value="{{ $ann->expires_at?->format('Y-m-d\TH:i') }}" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Title (English) *</label>
                                    <input type="text" name="title_en" value="{{ $ann->title_en }}" class="form-control form-control-sm" required maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Title (Arabic)</label>
                                    <input type="text" name="title_ar" dir="rtl" value="{{ $ann->title_ar }}" class="form-control form-control-sm" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Body (English) *</label>
                                    <textarea name="body_en" rows="3" class="form-control form-control-sm" required maxlength="4000">{{ $ann->body_en }}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Body (Arabic)</label>
                                    <textarea name="body_ar" dir="rtl" rows="3" class="form-control form-control-sm" maxlength="4000">{{ $ann->body_ar }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_active_{{ $ann->id }}" {{ $ann->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label small fw-semibold" for="edit_active_{{ $ann->id }}">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">{{ __('messages.save_changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
