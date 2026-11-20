@extends('layouts.admin')

@section('title', __('messages.instructor_profile_qr'))
@section('page-title', __('messages.instructor_profile_qr'))

@section('content')
    <div class="row g-4">
        {{-- Public Instructor Profile Form --}}
        <div class="col-lg-8">
            <div class="card ma-card">
                <div class="card-header bg-transparent py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h6 fw-bold mb-0">
                            <i class="bi bi-person-badge-fill me-2" aria-hidden="true"></i>Public Instructor Profile ("Meet Our Instructors")
                        </h2>
                        <div class="small text-muted">
                            Displayed on the Public Home Page. Manage your bilingual bio, photo, portfolio URL, and personal default InstaPay QR.
                        </div>
                    </div>
                    <span class="badge bg-navy">{{ $instructor->role->label() }}</span>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="{{ route('admin.instructor-profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold">Display Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $instructor->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" required maxlength="120">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="avatar" class="form-label fw-semibold">Profile Photo / Avatar</label>
                                <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp"
                                       class="form-control @error('avatar') is-invalid @enderror">
                                <div class="form-text">PNG, JPG, or WebP (max 2 MB). Square portrait recommended.</div>
                                @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="instructor_title" class="form-label fw-semibold">Title / Role (English)</label>
                                <input type="text" id="instructor_title" name="instructor_title"
                                       value="{{ old('instructor_title', $instructor->instructor_title) }}"
                                       placeholder="e.g. Co-Founder & Lead Instructor"
                                       class="form-control @error('instructor_title') is-invalid @enderror" maxlength="160">
                                @error('instructor_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="instructor_title_ar" class="form-label fw-semibold">Title / Role (Arabic — العنوان الوظيفي)</label>
                                <input type="text" id="instructor_title_ar" name="instructor_title_ar" dir="rtl"
                                       value="{{ old('instructor_title_ar', $instructor->instructor_title_ar) }}"
                                       placeholder="مثال: شريك مؤسس ومحاضر رئيسي"
                                       class="form-control @error('instructor_title_ar') is-invalid @enderror" maxlength="160">
                                @error('instructor_title_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="instructor_bio" class="form-label fw-semibold">Short Bio (English)</label>
                                <textarea id="instructor_bio" name="instructor_bio" rows="4" maxlength="2000"
                                          placeholder="Highlight your teaching background, expertise, and focus areas..."
                                          class="form-control @error('instructor_bio') is-invalid @enderror">{{ old('instructor_bio', $instructor->instructor_bio) }}</textarea>
                                @error('instructor_bio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="instructor_bio_ar" class="form-label fw-semibold">Short Bio (Arabic — نبذة مختصرة)</label>
                                <textarea id="instructor_bio_ar" name="instructor_bio_ar" rows="4" dir="rtl" maxlength="2000"
                                          placeholder="نبذة مختصرة عن خبرتك التدريبية والتقنية..."
                                          class="form-control @error('instructor_bio_ar') is-invalid @enderror">{{ old('instructor_bio_ar', $instructor->instructor_bio_ar) }}</textarea>
                                @error('instructor_bio_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <hr class="my-2">
                                <h3 class="h6 fw-bold mb-1">
                                    <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Portfolio & Social Links
                                </h3>
                                <p class="small text-muted mb-0">
                                    Your Portfolio / Personal Website URL powers the external <strong>View Portfolio / Website</strong> button on the Home Page.
                                </p>
                            </div>

                            <div class="col-12">
                                <label for="portfolio_url" class="form-label fw-semibold">Portfolio / Personal Website URL</label>
                                <input type="url" id="portfolio_url" name="portfolio_url"
                                       value="{{ old('portfolio_url', $instructor->portfolio_url) }}"
                                       placeholder="https://your-portfolio.example.com"
                                       class="form-control @error('portfolio_url') is-invalid @enderror" maxlength="255">
                                @error('portfolio_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="linkedin_url" class="form-label fw-semibold">LinkedIn Profile URL (Optional)</label>
                                <input type="url" id="linkedin_url" name="linkedin_url"
                                       value="{{ old('linkedin_url', $instructor->linkedin_url) }}"
                                       placeholder="https://www.linkedin.com/in/username"
                                       class="form-control @error('linkedin_url') is-invalid @enderror" maxlength="255">
                                @error('linkedin_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="github_url" class="form-label fw-semibold">GitHub Profile URL (Optional)</label>
                                <input type="url" id="github_url" name="github_url"
                                       value="{{ old('github_url', $instructor->github_url) }}"
                                       placeholder="https://github.com/username"
                                       class="form-control @error('github_url') is-invalid @enderror" maxlength="255">
                                @error('github_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <hr class="my-2">
                                <h3 class="h6 fw-bold mb-1">
                                    <i class="bi bi-qr-code-scan me-1" aria-hidden="true"></i>Personal Default InstaPay QR Code
                                </h3>
                                <p class="small text-muted mb-0">
                                    Used automatically on the Subscribe checkout page for <strong>your courses</strong> unless you upload a course-specific InstaPay QR on an individual course.
                                </p>
                            </div>

                            <div class="col-md-6">
                                <label for="instapay_qr" class="form-label fw-semibold">Upload Personal InstaPay QR Image</label>
                                <input type="file" id="instapay_qr" name="instapay_qr" accept="image/png,image/jpeg,image/webp"
                                       class="form-control @error('instapay_qr') is-invalid @enderror">
                                <div class="form-text">PNG, JPG, or WebP (max 4 MB).</div>
                                @error('instapay_qr') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="instapay_note" class="form-label fw-semibold">Personal InstaPay Handle / Note</label>
                                <input type="text" id="instapay_note" name="instapay_note"
                                       value="{{ old('instapay_note', $instructor->instapay_note) }}"
                                       placeholder="e.g. username@instapay — Transfer exact fee"
                                       class="form-control @error('instapay_note') is-invalid @enderror" maxlength="255">
                                @error('instapay_note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-ma-primary px-4">
                                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('messages.save_changes') }}
                            </button>
                            <a href="{{ route('home') }}#meet-our-instructors" target="_blank" rel="noopener noreferrer" class="btn btn-ma-outline">
                                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Preview on Home Page
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Live Preview & Asset Management Sidebar --}}
        <div class="col-lg-4">
            <div class="card ma-card mb-4">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 fw-bold mb-0">Profile Card Preview</h2>
                </div>
                <div class="card-body text-center p-4">
                    @if ($instructor->avatarUrl())
                        <img src="{{ $instructor->avatarUrl() }}" alt="{{ $instructor->name }}"
                             class="rounded-circle object-fit-cover border shadow-sm mb-3"
                             style="width: 104px; height: 104px;">
                    @else
                        <div class="rounded-circle bg-navy text-white d-inline-flex align-items-center justify-content-center fw-bold fs-2 mb-3"
                             style="width: 104px; height: 104px;">
                            {{ strtoupper(mb_substr($instructor->name, 0, 1)) }}
                        </div>
                    @endif

                    <h3 class="h5 fw-bold mb-1">{{ $instructor->name }}</h3>
                    <div class="small fw-semibold text-primary mb-2">
                        {{ $instructor->localizedInstructorTitle() }}
                    </div>
                    <p class="small text-muted mb-3">
                        {{ $instructor->localizedInstructorBio() ?: 'Add a short bio so students can learn about your background.' }}
                    </p>

                    <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-navy">{{ $coursesCount }} Courses Created</span>
                        @if ($instructor->portfolio_url)
                            <a href="{{ $instructor->portfolio_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-ma-primary btn-sm">
                                <i class="bi bi-globe2 me-1" aria-hidden="true"></i>{{ __('messages.view_portfolio') }}
                            </a>
                        @endif
                    </div>

                    @if ($instructor->avatar_path)
                        <form method="POST" action="{{ route('admin.instructor-profile.avatar.destroy') }}"
                              onsubmit="return confirm('Remove your current profile photo?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Photo
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card ma-card">
                <div class="card-header bg-transparent py-3">
                    <h2 class="h6 fw-bold mb-0">
                        <i class="bi bi-qr-code me-1" aria-hidden="true"></i>Active Personal InstaPay QR
                    </h2>
                </div>
                <div class="card-body text-center p-4">
                    @if ($instructor->instapayQrUrl())
                        <div class="p-3 bg-white rounded border d-inline-block mb-3">
                            <img src="{{ $instructor->instapayQrUrl() }}" alt="Personal InstaPay QR"
                                 class="img-fluid" style="max-height: 200px;">
                        </div>
                        <div class="small text-muted mb-3">
                            @if ($instructor->instapay_note)
                                <div class="fw-semibold text-body">{{ $instructor->instapay_note }}</div>
                            @endif
                            Active default QR for all your courses.
                        </div>
                        <form method="POST" action="{{ route('admin.instructor-profile.instapay-qr.destroy') }}"
                              onsubmit="return confirm('Remove your personal default InstaPay QR?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Remove Personal QR
                            </button>
                        </form>
                    @else
                        <div class="py-3 text-muted small">
                            <i class="bi bi-qr-code-scan display-6 d-block mb-2 opacity-50" aria-hidden="true"></i>
                            No personal default QR uploaded yet.
                            @if ($globalInstaPayQrUrl)
                                <div class="mt-2">Currently falling back to the <strong>Academy Global QR</strong> unless overridden on a course.</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
