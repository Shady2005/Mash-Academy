<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAccount extends Model
{
    public const PLATFORM_TIKTOK = 'tiktok';

    public const PLATFORM_YOUTUBE = 'youtube';

    public const PLATFORM_FACEBOOK = 'facebook';

    public const PLATFORM_INSTAGRAM = 'instagram';

    /**
     * @var list<string>
     */
    public const PLATFORMS = [
        self::PLATFORM_TIKTOK,
        self::PLATFORM_YOUTUBE,
        self::PLATFORM_FACEBOOK,
        self::PLATFORM_INSTAGRAM,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'platform',
        'account_name',
        'profile_url',
        'channel_id',
        'api_key',
        'followers_count',
        'total_views',
        'total_likes',
        'engagement_rate',
        'top_video_title',
        'top_video_url',
        'top_video_views',
        'top_video_likes',
        'last_synced_at',
        'sync_source',
        'notes',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'followers_count' => 'integer',
            'total_views' => 'integer',
            'total_likes' => 'integer',
            'engagement_rate' => 'decimal:2',
            'top_video_views' => 'integer',
            'top_video_likes' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function platformLabel(): string
    {
        return match ($this->platform) {
            self::PLATFORM_TIKTOK => 'TikTok',
            self::PLATFORM_YOUTUBE => 'YouTube',
            self::PLATFORM_FACEBOOK => 'Facebook',
            self::PLATFORM_INSTAGRAM => 'Instagram',
            default => ucfirst($this->platform),
        };
    }

    public function platformIcon(): string
    {
        return match ($this->platform) {
            self::PLATFORM_TIKTOK => 'bi-tiktok',
            self::PLATFORM_YOUTUBE => 'bi-youtube',
            self::PLATFORM_FACEBOOK => 'bi-facebook',
            self::PLATFORM_INSTAGRAM => 'bi-instagram',
            default => 'bi-megaphone',
        };
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->api_key);
    }

    public function computedEngagementRate(): float
    {
        if ($this->engagement_rate !== null) {
            return (float) $this->engagement_rate;
        }

        if ($this->total_views > 0) {
            return round(($this->total_likes / $this->total_views) * 100, 2);
        }

        if ($this->followers_count > 0) {
            return round(($this->total_likes / $this->followers_count) * 100, 2);
        }

        return 0.0;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
