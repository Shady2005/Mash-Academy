<?php

namespace App\Services;

use App\Models\AcademySetting;
use App\Models\MarketingAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

class MarketingMetricsService
{
    /**
     * Fetch live YouTube channel statistics & top-performing video via YouTube Data API v3
     * (using account-level API key or global `youtube_api_key` setting), or fall back gracefully
     * to public channel metadata parsing when no API key is configured.
     *
     * @return array{ok: bool, message: string, source: string}
     */
    public function syncYouTube(MarketingAccount $account): array
    {
        if ($account->platform !== MarketingAccount::PLATFORM_YOUTUBE) {
            return [
                'ok' => false,
                'message' => 'Live API sync is supported for YouTube accounts. Use "Sync / Update Metrics" to log performance numbers for '.$account->platformLabel().'.',
                'source' => 'manual',
            ];
        }

        $apiKey = trim((string) ($account->api_key ?: AcademySetting::getValue('youtube_api_key', '')));

        if ($apiKey !== '') {
            return $this->syncViaYouTubeApi($account, $apiKey);
        }

        return $this->syncViaPublicYouTubeMetadata($account);
    }

    /**
     * @return array{ok: bool, message: string, source: string}
     */
    private function syncViaYouTubeApi(MarketingAccount $account, string $apiKey): array
    {
        try {
            $channelParams = [
                'part' => 'snippet,statistics',
                'key' => $apiKey,
            ];

            $channelId = $this->extractChannelId($account);
            if ($channelId !== null) {
                $channelParams['id'] = $channelId;
            } else {
                $handle = $this->extractHandle($account->profile_url);
                if ($handle === null) {
                    return [
                        'ok' => false,
                        'message' => 'Provide a valid YouTube Channel ID (starting with UC...) or @handle URL to sync via YouTube Data API v3.',
                        'source' => 'youtube_api',
                    ];
                }
                $channelParams['forHandle'] = ltrim($handle, '@');
            }

            $channelRes = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/channels', $channelParams);
            if (! $channelRes->ok()) {
                return [
                    'ok' => false,
                    'message' => 'YouTube API returned HTTP '.$channelRes->status().'. Check your API key and Channel ID.',
                    'source' => 'youtube_api',
                ];
            }

            $items = $channelRes->json('items', []);
            if (empty($items)) {
                return [
                    'ok' => false,
                    'message' => 'No matching YouTube channel was found for the provided Channel ID or @handle.',
                    'source' => 'youtube_api',
                ];
            }

            $channel = $items[0];
            $resolvedChannelId = (string) ($channel['id'] ?? $channelId ?? '');
            $stats = $channel['statistics'] ?? [];

            $account->channel_id = $resolvedChannelId ?: $account->channel_id;
            $account->followers_count = (int) ($stats['subscriberCount'] ?? $account->followers_count);
            $account->total_views = (int) ($stats['viewCount'] ?? $account->total_views);

            // Fetch top-performing video by viewCount
            if ($resolvedChannelId !== '') {
                $searchRes = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/search', [
                    'part' => 'snippet',
                    'channelId' => $resolvedChannelId,
                    'order' => 'viewCount',
                    'type' => 'video',
                    'maxResults' => 1,
                    'key' => $apiKey,
                ]);

                $topItem = $searchRes->ok() ? ($searchRes->json('items.0') ?? null) : null;
                $topVideoId = $topItem['id']['videoId'] ?? null;

                if ($topVideoId) {
                    $account->top_video_title = html_entity_decode((string) ($topItem['snippet']['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $account->top_video_url = 'https://www.youtube.com/watch?v='.$topVideoId;

                    $videoRes = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', [
                        'part' => 'statistics',
                        'id' => $topVideoId,
                        'key' => $apiKey,
                    ]);

                    if ($videoRes->ok()) {
                        $vStats = $videoRes->json('items.0.statistics', []);
                        $account->top_video_views = (int) ($vStats['viewCount'] ?? 0);
                        $account->top_video_likes = (int) ($vStats['likeCount'] ?? 0);
                        if ($account->total_likes < $account->top_video_likes) {
                            $account->total_likes = $account->top_video_likes;
                        }
                    }
                }
            }

            $account->last_synced_at = now();
            $account->sync_source = 'youtube_api';
            $account->save();

            return [
                'ok' => true,
                'message' => "Synced live YouTube metrics for \"{$account->account_name}\" via YouTube Data API v3.",
                'source' => 'youtube_api',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Could not reach YouTube Data API v3: '.$e->getMessage(),
                'source' => 'youtube_api',
            ];
        }
    }

    /**
     * Best-effort public HTML metadata check when no YouTube API key is configured.
     *
     * @return array{ok: bool, message: string, source: string}
     */
    private function syncViaPublicYouTubeMetadata(MarketingAccount $account): array
    {
        try {
            $res = Http::timeout(6)
                ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
                ->get($account->profile_url);

            if (! $res->ok()) {
                return [
                    'ok' => false,
                    'message' => 'No YouTube API key is configured and public channel page could not be parsed (HTTP '.$res->status().'). Add a YouTube Data API v3 key or use "Sync / Update Metrics".',
                    'source' => 'public_parse',
                ];
            }

            $html = $res->body();
            $updatedAny = false;

            if (preg_match('/"channelId":"(UC[a-zA-Z0-9_-]+)"/', $html, $m)) {
                $account->channel_id = $m[1];
                $updatedAny = true;
            }

            if (preg_match('/"subscriberCountText":\{"simpleText":"([^"]+)"\}/', $html, $m)) {
                $parsed = $this->parseCompactCount($m[1]);
                if ($parsed !== null) {
                    $account->followers_count = $parsed;
                    $updatedAny = true;
                }
            }

            if (preg_match('/"viewCountText":"([0-9,]+)\s+views"/i', $html, $m)) {
                $account->total_views = (int) str_replace(',', '', $m[1]);
                $updatedAny = true;
            }

            if ($updatedAny) {
                $account->last_synced_at = now();
                $account->sync_source = 'public_parse';
                $account->save();

                return [
                    'ok' => true,
                    'message' => "Parsed public YouTube metadata for \"{$account->account_name}\". For complete video & like metrics, configure a YouTube Data API v3 key or update metrics manually.",
                    'source' => 'public_parse',
                ];
            }

            return [
                'ok' => false,
                'message' => 'No YouTube API key configured and YouTube public page did not expose live statistics. Add a YouTube Data API v3 key or use "Sync / Update Metrics".',
                'source' => 'public_parse',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Could not fetch public YouTube page: '.$e->getMessage().' Use "Sync / Update Metrics" or configure a YouTube API key.',
                'source' => 'public_parse',
            ];
        }
    }

    private function extractChannelId(MarketingAccount $account): ?string
    {
        if (! empty($account->channel_id) && str_starts_with(trim($account->channel_id), 'UC')) {
            return trim($account->channel_id);
        }

        if (preg_match('#youtube\.com/channel/(UC[a-zA-Z0-9_-]+)#i', $account->profile_url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extractHandle(string $url): ?string
    {
        if (preg_match('#youtube\.com/(@[a-zA-Z0-9._-]+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function parseCompactCount(string $text): ?int
    {
        if (! preg_match('/([\d.,]+)\s*([KMB])?/i', $text, $m)) {
            return null;
        }

        $num = (float) str_replace(',', '', $m[1]);
        $suffix = strtoupper($m[2] ?? '');

        $mult = match ($suffix) {
            'K' => 1_000,
            'M' => 1_000_000,
            'B' => 1_000_000_000,
            default => 1,
        };

        return (int) round($num * $mult);
    }
}
