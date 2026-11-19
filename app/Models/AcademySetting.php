<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AcademySetting extends Model
{
    public const CACHE_KEY = 'mash_academy.settings.all';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string|null>
     */
    public static function defaults(): array
    {
        return [
            'academy_name' => 'Mash Academy',
            'tagline' => 'Structured, practical online learning built for measurable student progress.',
            'about' => 'Mash Academy delivers high-impact, instructor-led recorded courses designed with sequential mastery, hands-on assignments, and continuous progress tracking.',
            'vision' => 'To empower learners across Egypt and the region with accessible, disciplined, and career-ready education.',
            'logo_path' => null,
            'contact_email' => 'info@mashacademy.com',
            'contact_phone' => '+20 100 000 0000',
            'facebook_url' => null,
            'instagram_url' => null,
            'youtube_url' => null,
            'whatsapp_number' => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function allSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $stored = static::query()->pluck('value', 'key')->all();

            return array_merge(self::defaults(), $stored);
        });
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $all = self::allSettings();

        return $all[$key] ?? $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        self::clearCache();
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        self::clearCache();
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function logoUrl(): ?string
    {
        $path = self::getValue('logo_path');

        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
