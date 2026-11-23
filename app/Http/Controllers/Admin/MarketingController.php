<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarketingAccountRequest;
use App\Models\AcademySetting;
use App\Models\MarketingAccount;
use App\Services\ActivityLogger;
use App\Services\MarketingMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function __construct(
        private readonly MarketingMetricsService $metricsService
    ) {}

    public function index(Request $request): View
    {
        $platformFilter = strtolower(trim((string) $request->query('platform', 'all')));
        if (! in_array($platformFilter, array_merge(['all'], MarketingAccount::PLATFORMS), true)) {
            $platformFilter = 'all';
        }

        $allAccounts = MarketingAccount::query()
            ->with('creator')
            ->latest()
            ->get();

        $filteredAccounts = $platformFilter === 'all'
            ? $allAccounts
            : $allAccounts->where('platform', $platformFilter)->values();

        $totalFollowers = (int) $filteredAccounts->sum('followers_count');
        $totalViews = (int) $filteredAccounts->sum('total_views');
        $totalLikes = (int) $filteredAccounts->sum('total_likes');
        $avgEngagement = $filteredAccounts->isNotEmpty()
            ? round($filteredAccounts->avg(fn (MarketingAccount $a): float => $a->computedEngagementRate()), 2)
            : 0.0;

        $platformCounts = [
            'all' => $allAccounts->count(),
            MarketingAccount::PLATFORM_TIKTOK => $allAccounts->where('platform', MarketingAccount::PLATFORM_TIKTOK)->count(),
            MarketingAccount::PLATFORM_YOUTUBE => $allAccounts->where('platform', MarketingAccount::PLATFORM_YOUTUBE)->count(),
            MarketingAccount::PLATFORM_FACEBOOK => $allAccounts->where('platform', MarketingAccount::PLATFORM_FACEBOOK)->count(),
            MarketingAccount::PLATFORM_INSTAGRAM => $allAccounts->where('platform', MarketingAccount::PLATFORM_INSTAGRAM)->count(),
        ];

        $hasGlobalYoutubeKey = ! empty(AcademySetting::getValue('youtube_api_key'));

        return view('admin.marketing.index', [
            'accounts' => $filteredAccounts,
            'platformFilter' => $platformFilter,
            'platformCounts' => $platformCounts,
            'summary' => [
                'accounts_count' => $filteredAccounts->count(),
                'followers' => $totalFollowers,
                'views' => $totalViews,
                'likes' => $totalLikes,
                'avg_engagement' => $avgEngagement,
            ],
            'hasGlobalYoutubeKey' => $hasGlobalYoutubeKey,
            'canManage' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function store(MarketingAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = new MarketingAccount();
        $account->platform = $validated['platform'];
        $account->account_name = trim((string) $validated['account_name']);
        $account->profile_url = trim((string) $validated['profile_url']);
        $account->channel_id = $this->nullableTrim($validated['channel_id'] ?? null);
        $account->api_key = $this->nullableTrim($validated['api_key'] ?? null);
        $account->followers_count = (int) ($validated['followers_count'] ?? 0);
        $account->total_views = (int) ($validated['total_views'] ?? 0);
        $account->total_likes = (int) ($validated['total_likes'] ?? 0);
        $account->engagement_rate = isset($validated['engagement_rate']) && $validated['engagement_rate'] !== ''
            ? (float) $validated['engagement_rate']
            : null;
        $account->top_video_title = $this->nullableTrim($validated['top_video_title'] ?? null);
        $account->top_video_url = $this->nullableTrim($validated['top_video_url'] ?? null);
        $account->top_video_views = (int) ($validated['top_video_views'] ?? 0);
        $account->top_video_likes = (int) ($validated['top_video_likes'] ?? 0);
        $account->notes = $this->nullableTrim($validated['notes'] ?? null);
        $account->sync_source = 'manual';
        $account->last_synced_at = ($account->followers_count > 0 || $account->total_views > 0) ? now() : null;
        $account->created_by = $request->user()->id;
        $account->save();

        ActivityLogger::log(
            action: 'marketing.account_added',
            subject: $account,
            meta: [
                'platform' => $account->platform,
                'account_name' => $account->account_name,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.marketing.index', ['platform' => $account->platform])
            ->with('status', "{$account->platformLabel()} account \"{$account->account_name}\" connected successfully.");
    }

    public function updateMetrics(Request $request, MarketingAccount $marketingAccount): RedirectResponse
    {
        $validated = $request->validate([
            'account_name' => ['required', 'string', 'max:150'],
            'profile_url' => ['required', 'url:http,https', 'max:500'],
            'channel_id' => ['nullable', 'string', 'max:150'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'followers_count' => ['required', 'integer', 'min:0'],
            'total_views' => ['required', 'integer', 'min:0'],
            'total_likes' => ['required', 'integer', 'min:0'],
            'engagement_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'top_video_title' => ['nullable', 'string', 'max:255'],
            'top_video_url' => ['nullable', 'url:http,https', 'max:500'],
            'top_video_views' => ['nullable', 'integer', 'min:0'],
            'top_video_likes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $marketingAccount->account_name = trim((string) $validated['account_name']);
        $marketingAccount->profile_url = trim((string) $validated['profile_url']);
        $marketingAccount->channel_id = $this->nullableTrim($validated['channel_id'] ?? null);
        if (! empty($validated['api_key'])) {
            $marketingAccount->api_key = trim((string) $validated['api_key']);
        }
        $marketingAccount->followers_count = (int) $validated['followers_count'];
        $marketingAccount->total_views = (int) $validated['total_views'];
        $marketingAccount->total_likes = (int) $validated['total_likes'];
        $marketingAccount->engagement_rate = isset($validated['engagement_rate']) && $validated['engagement_rate'] !== ''
            ? (float) $validated['engagement_rate']
            : null;
        $marketingAccount->top_video_title = $this->nullableTrim($validated['top_video_title'] ?? null);
        $marketingAccount->top_video_url = $this->nullableTrim($validated['top_video_url'] ?? null);
        $marketingAccount->top_video_views = (int) ($validated['top_video_views'] ?? 0);
        $marketingAccount->top_video_likes = (int) ($validated['top_video_likes'] ?? 0);
        $marketingAccount->notes = $this->nullableTrim($validated['notes'] ?? null);
        $marketingAccount->last_synced_at = now();
        $marketingAccount->sync_source = 'manual';
        $marketingAccount->save();

        ActivityLogger::log(
            action: 'marketing.metrics_updated',
            subject: $marketingAccount,
            meta: [
                'platform' => $marketingAccount->platform,
                'followers_count' => $marketingAccount->followers_count,
                'total_views' => $marketingAccount->total_views,
                'total_likes' => $marketingAccount->total_likes,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.marketing.index', ['platform' => $marketingAccount->platform])
            ->with('status', "Metrics updated for \"{$marketingAccount->account_name}\".");
    }

    public function sync(Request $request, MarketingAccount $marketingAccount): RedirectResponse
    {
        $result = $this->metricsService->syncYouTube($marketingAccount);

        ActivityLogger::log(
            action: 'marketing.sync_attempted',
            subject: $marketingAccount,
            meta: [
                'platform' => $marketingAccount->platform,
                'ok' => $result['ok'],
                'source' => $result['source'],
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($result['ok']) {
            return redirect()
                ->route('admin.marketing.index', ['platform' => $marketingAccount->platform])
                ->with('status', $result['message']);
        }

        return redirect()
            ->route('admin.marketing.index', ['platform' => $marketingAccount->platform])
            ->withErrors(['sync' => $result['message']]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'youtube_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $this->nullableTrim($validated['youtube_api_key'] ?? null);
        AcademySetting::setValue('youtube_api_key', $key);

        ActivityLogger::log(
            action: 'marketing.youtube_api_key_updated',
            meta: ['configured' => ! empty($key)],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.marketing.index')
            ->with('status', $key ? 'YouTube Data API v3 key saved.' : 'YouTube Data API v3 key cleared.');
    }

    public function destroy(Request $request, MarketingAccount $marketingAccount): RedirectResponse
    {
        $name = $marketingAccount->account_name;
        $platform = $marketingAccount->platform;
        $marketingAccount->delete();

        ActivityLogger::log(
            action: 'marketing.account_deleted',
            meta: ['platform' => $platform, 'account_name' => $name],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.marketing.index')
            ->with('status', "Disconnected \"{$name}\" from Marketing Analytics.");
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
