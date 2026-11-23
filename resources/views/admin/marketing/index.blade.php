@extends('layouts.admin')

@section('title', __('messages.marketing_analytics'))
@section('page-title', __('messages.marketing_analytics'))

@section('content')
    {{-- Header Actions --}}
    <div class="ma-card p-4 mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 fw-bold mb-1">
                <i class="bi bi-megaphone-fill me-2 text-navy" aria-hidden="true"></i>{{ __('messages.marketing_analytics') }}
            </h2>
            <p class="text-muted small mb-0">
                Track Mash Academy's connected TikTok, YouTube, Facebook, and Instagram channels, audience growth, engagement, and top-performing videos.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($canManage)
                <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#youtubeApiModal">
                    <i class="bi bi-key me-1" aria-hidden="true"></i>YouTube API Key
                    <span class="badge {{ $hasGlobalYoutubeKey ? 'bg-success' : 'bg-secondary' }} ms-1">{{ $hasGlobalYoutubeKey ? 'Set' : 'Optional' }}</span>
                </button>
                <button type="button" class="btn btn-ma-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addSocialAccountModal">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('messages.add_social_account') }}
                </button>
            @else
                <span class="badge bg-black-badge px-3 py-2">
                    <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('messages.read_only_badge') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">{{ __('messages.total_followers') }}</div>
                <div class="h3 fw-bold text-navy mb-0 mt-1">{{ number_format($summary['followers']) }}</div>
                <div class="small text-muted mt-1">{{ $summary['accounts_count'] }} connected accounts</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">{{ __('messages.total_video_views') }}</div>
                <div class="h3 fw-bold mb-0 mt-1">{{ number_format($summary['views']) }}</div>
                <div class="small text-muted mt-1">Across selected platform(s)</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">{{ __('messages.total_likes') }}</div>
                <div class="h3 fw-bold text-navy mb-0 mt-1">{{ number_format($summary['likes']) }}</div>
                <div class="small text-muted mt-1">Cumulative video &amp; post likes</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="ma-card p-3 h-100">
                <div class="small text-muted text-uppercase fw-semibold">Avg. Engagement Rate</div>
                <div class="h3 fw-bold mb-0 mt-1">{{ number_format($summary['avg_engagement'], 2) }}%</div>
                <div class="small text-muted mt-1">Likes-to-views / custom logged</div>
            </div>
        </div>
    </div>

    {{-- Platform Filter Tabs --}}
    <ul class="nav nav-pills gap-2 mb-4">
        @php
            $tabs = [
                'all' => ['label' => 'All Platforms', 'icon' => 'bi-grid'],
                'tiktok' => ['label' => 'TikTok', 'icon' => 'bi-tiktok'],
                'youtube' => ['label' => 'YouTube', 'icon' => 'bi-youtube'],
                'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook'],
                'instagram' => ['label' => 'Instagram', 'icon' => 'bi-instagram'],
            ];
        @endphp
        @foreach ($tabs as $key => $meta)
            <li class="nav-item">
                <a href="{{ route('admin.marketing.index', ['platform' => $key]) }}"
                   class="btn btn-sm {{ $platformFilter === $key ? 'btn-ma-primary' : 'btn-ma-outline' }}">
                    <i class="bi {{ $meta['icon'] }} me-1" aria-hidden="true"></i>{{ $meta['label'] }}
                    <span class="badge {{ $platformFilter === $key ? 'bg-light text-dark' : 'bg-secondary' }} ms-1">{{ $platformCounts[$key] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Per-Account Analytics Cards --}}
    @if ($accounts->isEmpty())
        <div class="ma-card p-5 text-center">
            <i class="bi bi-bar-chart-line fs-1 text-muted d-block mb-2" aria-hidden="true"></i>
            <p class="text-muted mb-3">No social media accounts connected in this view yet.</p>
            @if ($canManage)
                <button type="button" class="btn btn-ma-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSocialAccountModal">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('messages.add_social_account') }}
                </button>
            @endif
        </div>
    @else
        <div class="row g-4">
            @foreach ($accounts as $account)
                <div class="col-lg-6">
                    <article class="ma-card p-4 h-100 d-flex flex-column">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="ma-icon-box ma-icon-navy">
                                    <i class="bi {{ $account->platformIcon() }} fs-5" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <h3 class="h6 fw-bold mb-0">{{ $account->account_name }}</h3>
                                        <span class="badge bg-black-badge">{{ $account->platformLabel() }}</span>
                                    </div>
                                    <a href="{{ $account->profile_url }}" target="_blank" rel="noopener noreferrer" class="small text-decoration-none text-muted">
                                        {{ \Illuminate\Support\Str::limit($account->profile_url, 52) }}
                                        <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>

                            @if ($canManage)
                                <div class="d-flex gap-1">
                                    @if ($account->platform === \App\Models\MarketingAccount::PLATFORM_YOUTUBE)
                                        <form method="POST" action="{{ route('admin.marketing.sync', $account) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ma-dark btn-sm" title="Fetch live metrics from YouTube">
                                                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Sync YouTube
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-ma-outline btn-sm" data-bs-toggle="modal" data-bs-target="#metricsModal{{ $account->id }}">
                                        <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('messages.sync_update_metrics') }}
                                    </button>
                                    <form method="POST" action="{{ route('admin.marketing.destroy', $account) }}" onsubmit="return confirm('Disconnect {{ $account->account_name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove account">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        {{-- 3 Core Metrics --}}
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="border rounded-3 p-2 text-center" style="background-color: var(--ma-surface-subtle);">
                                    <div class="text-muted" style="font-size: 0.72rem;">Followers / Subs</div>
                                    <div class="fw-bold fs-6 text-navy">{{ number_format($account->followers_count) }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2 text-center" style="background-color: var(--ma-surface-subtle);">
                                    <div class="text-muted" style="font-size: 0.72rem;">Total Views</div>
                                    <div class="fw-bold fs-6">{{ number_format($account->total_views) }}</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-2 text-center" style="background-color: var(--ma-surface-subtle);">
                                    <div class="text-muted" style="font-size: 0.72rem;">Likes ({{ $account->computedEngagementRate() }}%)</div>
                                    <div class="fw-bold fs-6 text-navy">{{ number_format($account->total_likes) }}</div>
                                </div>
                            </div>
                        </div>

                        {{-- Top Performing Video --}}
                        <div class="border rounded-3 p-3 mt-auto" style="border-inline-start: 4px solid #1e3a8a !important;">
                            <div class="small text-uppercase fw-bold text-navy mb-1">
                                <i class="bi bi-trophy-fill me-1" aria-hidden="true"></i>{{ __('messages.top_performing_video') }}
                            </div>
                            @if ($account->top_video_title || $account->top_video_url)
                                <div class="fw-semibold small mb-1">
                                    @if ($account->top_video_url)
                                        <a href="{{ $account->top_video_url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                            {{ $account->top_video_title ?: 'Watch Top Video' }}
                                            <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        {{ $account->top_video_title }}
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap gap-3 small text-muted">
                                    <span><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ number_format($account->top_video_views) }} views</span>
                                    <span><i class="bi bi-heart-fill me-1 text-danger" aria-hidden="true"></i>{{ number_format($account->top_video_likes) }} likes</span>
                                </div>
                            @else
                                <div class="small text-muted">No top video logged yet. Click <strong>{{ __('messages.sync_update_metrics') }}</strong> to add one.</div>
                            @endif
                        </div>

                        <div class="d-flex justify-content-between align-items-center small text-muted mt-3 pt-2 border-top">
                            <span>Source: <strong>{{ $account->sync_source }}</strong></span>
                            <span>
                                {{ $account->last_synced_at ? 'Updated '.$account->last_synced_at->diffForHumans() : 'Not synced yet' }}
                            </span>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif

    @if ($canManage)
        {{-- + Add Account Modal --}}
        <div class="modal fade" id="addSocialAccountModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.marketing.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h6 fw-bold">
                                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Connect Social Media Account
                            </h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Platform <span class="text-danger">*</span></label>
                                    <select name="platform" class="form-select" required>
                                        <option value="tiktok">TikTok</option>
                                        <option value="youtube">YouTube</option>
                                        <option value="facebook">Facebook</option>
                                        <option value="instagram">Instagram</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold">Account / Page Name <span class="text-danger">*</span></label>
                                    <input type="text" name="account_name" class="form-control" required maxlength="150" placeholder="e.g. Mash Academy Official">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Profile / Channel URL <span class="text-danger">*</span></label>
                                    <input type="url" name="profile_url" class="form-control" required maxlength="500" placeholder="https://www.youtube.com/@mashacademy">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Channel ID / Handle (Optional)</label>
                                    <input type="text" name="channel_id" class="form-control" maxlength="150" placeholder="e.g. UCxxxxxxxxxxxx or @handle">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Account API Key (Optional, encrypted)</label>
                                    <input type="password" name="api_key" class="form-control" maxlength="500" placeholder="Optional YouTube Data API v3 key">
                                </div>
                                <div class="col-12"><hr class="my-1"><div class="small fw-semibold text-muted">Initial Metrics (Optional — can also be updated or synced after adding)</div></div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Followers / Subscribers</label>
                                    <input type="number" min="0" name="followers_count" value="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Total Video Views</label>
                                    <input type="number" min="0" name="total_views" value="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Total Likes</label>
                                    <input type="number" min="0" name="total_likes" value="0" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">Connect Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Global YouTube API Key Modal --}}
        <div class="modal fade" id="youtubeApiModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.marketing.settings.update') }}">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title h6 fw-bold">YouTube Data API v3 Key</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                Configure an optional global YouTube Data API v3 key so clicking <strong>Sync YouTube</strong> fetches live subscriber counts, total channel views, and the top-performing video automatically.
                            </p>
                            <label class="form-label small fw-semibold">YouTube API Key</label>
                            <input type="password" name="youtube_api_key" class="form-control" placeholder="AIzaSy... (leave blank to clear)">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                            <button type="submit" class="btn btn-ma-primary btn-sm">{{ __('messages.save_changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Sync / Update Metrics Modals (Per Account) --}}
        @foreach ($accounts as $account)
            <div class="modal fade" id="metricsModal{{ $account->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.marketing.metrics.update', $account) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-header">
                                <h2 class="modal-title h6 fw-bold">
                                    {{ __('messages.sync_update_metrics') }} — {{ $account->account_name }} ({{ $account->platformLabel() }})
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Account / Page Name <span class="text-danger">*</span></label>
                                        <input type="text" name="account_name" value="{{ $account->account_name }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Profile / Channel URL <span class="text-danger">*</span></label>
                                        <input type="url" name="profile_url" value="{{ $account->profile_url }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Channel ID / Handle</label>
                                        <input type="text" name="channel_id" value="{{ $account->channel_id }}" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Replace Account API Key (Optional)</label>
                                        <input type="password" name="api_key" class="form-control" placeholder="{{ $account->hasApiKey() ? 'Saved (leave blank to keep)' : 'Optional API key' }}">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Followers / Subs <span class="text-danger">*</span></label>
                                        <input type="number" min="0" name="followers_count" value="{{ $account->followers_count }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Total Views <span class="text-danger">*</span></label>
                                        <input type="number" min="0" name="total_views" value="{{ $account->total_views }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Total Likes <span class="text-danger">*</span></label>
                                        <input type="number" min="0" name="total_likes" value="{{ $account->total_likes }}" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Engagement %</label>
                                        <input type="number" step="0.01" min="0" max="100" name="engagement_rate" value="{{ $account->engagement_rate }}" class="form-control" placeholder="Auto">
                                    </div>

                                    <div class="col-12"><hr class="my-1"><div class="small fw-bold text-navy">{{ __('messages.top_performing_video') }}</div></div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Top Video Title</label>
                                        <input type="text" name="top_video_title" value="{{ $account->top_video_title }}" class="form-control" maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Top Video Direct Link</label>
                                        <input type="url" name="top_video_url" value="{{ $account->top_video_url }}" class="form-control" maxlength="500">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Top Video Views</label>
                                        <input type="number" min="0" name="top_video_views" value="{{ $account->top_video_views }}" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Top Video Likes</label>
                                        <input type="number" min="0" name="top_video_likes" value="{{ $account->top_video_likes }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                                <button type="submit" class="btn btn-ma-primary btn-sm">{{ __('messages.save_changes') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
