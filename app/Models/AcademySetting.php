<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
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
            'academy_name_ar' => 'أكاديمية ماش',
            'tagline' => 'Structured, practical online learning built for measurable student progress.',
            'tagline_ar' => 'تعليم إلكتروني منظم وعملي مصمم لتحقيق تقدم حقيقي وقابل للقياس للطلاب.',
            'about' => 'Mash Academy delivers high-impact, instructor-led recorded courses designed with sequential mastery, hands-on assignments, and continuous progress tracking.',
            'about_ar' => 'تقدم أكاديمية ماش دورات مسجلة عالية الجودة بإشراف نخبة من المحاضرين، بنظام تعلم متدرج وواجبات عملية ومتابعة مستمرة لتقدم الطالب.',
            'vision' => 'To empower learners across Egypt and the region with accessible, disciplined, and career-ready education.',
            'vision_ar' => 'تمكين المتعلمين في مصر والمنطقة العربية من خلال تعليم احترافي منضبط وسهل الوصول يؤهلهم لسوق العمل.',
            'logo_path' => null,
            'instapay_qr_path' => null,
            'instapay_account_note' => 'Scan the InstaPay QR code, transfer the exact final amount, and upload your transfer confirmation screenshot below.',
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

    public static function localized(string $key, ?string $locale = null, ?array $settings = null): string
    {
        $resolvedLocale = $locale ?? App::getLocale();
        $source = $settings ?? self::allSettings();

        if ($resolvedLocale === 'ar') {
            $arabic = $source[$key.'_ar'] ?? null;
            if ($arabic !== null && trim($arabic) !== '') {
                return $arabic;
            }
        }

        return (string) ($source[$key] ?? '');
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

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    /**
     * Return a self-contained data URI for the uploaded logo (used in downloaded HTML certificates).
     */
    public static function logoDataUri(): ?string
    {
        $path = self::getValue('logo_path');

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => Storage::disk('public')->mimeType($path) ?: 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    public static function instapayQrUrl(): ?string
    {
        $path = self::getValue('instapay_qr_path');

        if (! $path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
