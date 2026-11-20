@extends('layouts.admin')

@section('title', __('messages.analytics_dashboard'))
@section('page-title', __('messages.analytics_dashboard'))

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="h5 fw-bold mb-1">Student Progress &amp; Academy Analytics</h2>
            <p class="small text-muted mb-0">
                Real-time enrollment, video completion, quiz performance, assignment status, Final Project, and certificate metrics.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if (auth()->user()->isSupport())
                <span class="badge bg-black-badge px-3 py-2">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('messages.read_only_badge') }}
                </span>
            @endif
            <a href="{{ route('admin.analytics.export', request()->query()) }}" class="btn btn-ma-outline btn-sm">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('messages.export_csv') }}
            </a>
        </div>
    </div>

    {{-- Summary Metric Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Active Students</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ $summary['active_students'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Active Enrollments</div>
                <div class="h3 fw-bold mt-1 mb-0 text-navy">{{ $summary['active_enrollments'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Expiring (7d)</div>
                <div class="h3 fw-bold mt-1 mb-0 text-warning">{{ $summary['expiring_soon'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Pending Payments</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ $summary['pending_payments'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Certificates</div>
                <div class="h3 fw-bold mt-1 mb-0 text-success">{{ $summary['certificates_issued'] }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-2">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Published Courses</div>
                <div class="h3 fw-bold mt-1 mb-0">{{ $summary['published_courses'] }}</div>
            </div>
        </div>
    </div>

    {{-- Charts Row (Chart.js) --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="ma-card p-4 h-100">
                <h3 class="h6 fw-bold mb-3">Active Enrollments &amp; Certificates by Course</h3>
                <div style="height: 240px;">
                    <canvas id="courseEnrollmentsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ma-card p-4 h-100">
                <h3 class="h6 fw-bold mb-3">Enrollment Status Breakdown</h3>
                <div style="height: 240px;">
                    <canvas id="enrollmentStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-Course Summary Table --}}
    <div class="ma-card overflow-hidden mb-4">
        <div class="p-3 border-bottom">
            <h3 class="h6 fw-bold mb-0">Per-Course Performance Overview</h3>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 small">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Published Lessons</th>
                        <th>Total Enrollments</th>
                        <th>Active Enrollments</th>
                        <th>Certificates Issued</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($courseReport as $cRow)
                        <tr>
                            <td class="fw-semibold">{{ $cRow['course']->title }}</td>
                            <td>{{ $cRow['lessons_count'] }}</td>
                            <td>{{ $cRow['total_enrollments'] }}</td>
                            <td><span class="badge bg-navy-accent">{{ $cRow['active_enrollments'] }}</span></td>
                            <td><span class="badge bg-success">{{ $cRow['certificates_issued'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No courses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Filters for Per-Student Progress Table --}}
    <div class="ma-card p-3 mb-3">
        <form method="GET" action="{{ route('admin.analytics.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="analytics_q" class="form-label small fw-semibold">Search Student</label>
                <input type="text" id="analytics_q" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Name, email, or phone...">
            </div>
            <div class="col-md-3">
                <label for="analytics_course" class="form-label small fw-semibold">Course</label>
                <select id="analytics_course" name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" {{ (string) $filters['course_id'] === (string) $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="analytics_status" class="form-label small fw-semibold">Status</label>
                <select id="analytics_status" name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" {{ $filters['status'] === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="expired" {{ $filters['status'] === 'expired' ? 'selected' : '' }}>Expired</option>
                    <option value="revoked" {{ $filters['status'] === 'revoked' ? 'selected' : '' }}>Revoked</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="analytics_from" class="form-label small fw-semibold">From Date</label>
                <input type="date" id="analytics_from" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="analytics_to" class="form-label small fw-semibold">To Date</label>
                <input type="date" id="analytics_to" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-12 d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-ma-primary btn-sm px-3">Apply Filters</button>
                <a href="{{ route('admin.analytics.index') }}" class="btn btn-ma-outline btn-sm">Reset</a>
            </div>
        </form>
    </div>

    {{-- Detailed Per-Student Progress Table --}}
    <div class="ma-card overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <h3 class="h6 fw-bold mb-0">Per-Student Progress &amp; Assessment Report</h3>
            <span class="small text-muted">{{ $studentRows->total() }} enrollment records</span>
        </div>

        @if ($studentRows->isEmpty())
            <div class="p-5 text-center text-muted small">No enrollment progress records match your filters.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course &amp; Enrollment</th>
                            <th>Videos Watched</th>
                            <th style="min-width: 140px;">Completion %</th>
                            <th>Quiz Scores</th>
                            <th>Assignments</th>
                            <th>Final Project</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($studentRows as $row)
                            <tr>
                                <td>
                                    @if ($row['student'])
                                        <a href="{{ route('admin.students.show', $row['student']) }}" class="fw-semibold text-decoration-none text-navy">
                                            {{ $row['student']->name }}
                                        </a>
                                        <div class="text-muted">{{ $row['student']->email }}</div>
                                    @else
                                        <span class="text-muted">Deleted User</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $row['course']?->title ?? 'Deleted' }}</div>
                                    <div class="mt-1">
                                        @if ($row['effective_status'] === 'active')
                                            <span class="badge bg-navy-accent">Active</span>
                                        @elseif ($row['effective_status'] === 'expired')
                                            <span class="badge bg-secondary">Expired</span>
                                        @else
                                            <span class="badge bg-danger">Revoked</span>
                                        @endif
                                        <span class="text-muted ms-1">Exp: {{ $row['enrollment']->expires_at->format('M d, Y') }}</span>
                                    </div>
                                </td>
                                <td class="fw-semibold text-nowrap">
                                    {{ $row['videos_watched'] }} / {{ $row['total_videos'] }}
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold text-navy">{{ $row['completion_percentage'] }}%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-navy-accent" style="width: {{ min(100, $row['completion_percentage']) }}%;"></div>
                                    </div>
                                </td>
                                <td class="text-nowrap">
                                    @if ($row['quiz_attempts_count'] > 0)
                                        <div>Best: <strong>{{ $row['quiz_best'] }}%</strong></div>
                                        <div class="text-muted">Avg: {{ $row['quiz_avg'] }}% · Last: {{ $row['quiz_latest'] }}%</div>
                                    @else
                                        <span class="text-muted">No attempts</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($row['assignment_status'] === 'Submitted')
                                        <span class="badge bg-success">Submitted</span>
                                    @elseif ($row['assignment_status'] === 'Pending')
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    @else
                                        <span class="badge bg-secondary">N/A</span>
                                    @endif
                                    <div class="text-muted mt-1">{{ $row['submitted_assignments'] }}/{{ $row['total_assignments'] }}</div>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge {{ in_array($row['final_project_status'], ['Submitted', 'Reviewed'], true) ? 'bg-success' : 'bg-black-badge' }}">
                                        {{ $row['final_project_status'] }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    @if ($row['certificate'] && $row['certificate']->isValid())
                                        <span class="badge bg-navy-accent">{{ $row['certificate']->certificate_code }}</span>
                                    @elseif ($row['certificate'] && $row['certificate']->isRevoked())
                                        <span class="badge bg-danger">Revoked</span>
                                    @else
                                        <span class="text-muted">Locked</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($studentRows->hasPages())
                <div class="p-3 border-top">{{ $studentRows->links() }}</div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
        (() => {
            const chartData = @json($chartData);
            const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const tickColor = isDark ? '#94a3b8' : '#4b5563';
            const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';

            const courseCanvas = document.getElementById('courseEnrollmentsChart');
            if (courseCanvas && window.Chart) {
                new Chart(courseCanvas, {
                    type: 'bar',
                    data: {
                        labels: chartData.course_labels,
                        datasets: [
                            {
                                label: 'Active Enrollments',
                                data: chartData.course_active,
                                backgroundColor: '#1e3a8a',
                                borderRadius: 6,
                            },
                            {
                                label: 'Certificates Issued',
                                data: chartData.course_certificates,
                                backgroundColor: isDark ? '#60a5fa' : '#111111',
                                borderRadius: 6,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { ticks: { color: tickColor }, grid: { display: false } },
                            y: { beginAtZero: true, ticks: { precision: 0, color: tickColor }, grid: { color: gridColor } }
                        },
                        plugins: { legend: { labels: { color: tickColor } } }
                    }
                });
            }

            const statusCanvas = document.getElementById('enrollmentStatusChart');
            if (statusCanvas && window.Chart) {
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'Expired', 'Revoked'],
                        datasets: [{
                            data: [
                                chartData.enrollment_statuses.active,
                                chartData.enrollment_statuses.expired,
                                chartData.enrollment_statuses.revoked
                            ],
                            backgroundColor: ['#1e3a8a', '#64748b', '#dc2626'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: tickColor } } }
                    }
                });
            }
        })();
    </script>
@endpush
