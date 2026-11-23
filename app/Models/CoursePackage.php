<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class CoursePackage extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'title_ar',
        'slug',
        'short_description',
        'short_description_ar',
        'description',
        'description_ar',
        'price',
        'currency',
        'thumbnail_path',
        'instapay_qr_path',
        'instapay_note',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'package';
        $slug = $base;
        $counter = 2;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function localizedTitle(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->title_ar)) {
            return $this->title_ar;
        }

        return $this->title;
    }

    public function localizedShortDescription(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->short_description_ar)) {
            return $this->short_description_ar;
        }

        return (string) ($this->short_description ?: Str::limit(strip_tags($this->localizedDescription($lang)), 140));
    }

    public function localizedDescription(?string $locale = null): string
    {
        $lang = $locale ?? App::getLocale();
        if ($lang === 'ar' && ! empty($this->description_ar)) {
            return $this->description_ar;
        }

        return $this->description;
    }

    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return asset('storage/'.ltrim($this->thumbnail_path, '/'));
    }

    public function packageInstaPayQrUrl(): ?string
    {
        if (! $this->instapay_qr_path) {
            return null;
        }

        return asset('storage/'.ltrim($this->instapay_qr_path, '/'));
    }

    /**
     * Resolve active InstaPay QR for package checkout:
     * 1. Package-specific QR -> 2. Creator personal default QR -> 3. Global Academy QR.
     *
     * @return array{url: ?string, source: string, note: ?string}
     */
    public function resolveInstaPayDetails(): array
    {
        $creator = $this->relationLoaded('creator') ? $this->creator : $this->creator()->first();

        $url = null;
        $source = 'none';

        if (! empty($this->instapay_qr_path)) {
            $url = asset('storage/'.ltrim($this->instapay_qr_path, '/'));
            $source = 'package';
        } elseif ($creator && ! empty($creator->instapay_qr_path)) {
            $url = asset('storage/'.ltrim($creator->instapay_qr_path, '/'));
            $source = 'instructor';
        } else {
            $globalUrl = AcademySetting::instapayQrUrl();
            if ($globalUrl) {
                $url = $globalUrl;
                $source = 'global';
            }
        }

        $note = $this->instapay_note
            ?: ($creator?->instapay_note ?: AcademySetting::getValue('instapay_account_note'));

        return [
            'url' => $url,
            'source' => $source,
            'note' => $note,
        ];
    }

    public function formattedPrice(): string
    {
        return number_format((float) $this->price, 0).' '.($this->currency ?: 'EGP');
    }

    public function individualCoursesTotal(): float
    {
        $courses = $this->relationLoaded('courses') ? $this->courses : $this->courses()->get();

        return round((float) $courses->sum('price'), 2);
    }

    public function savingsAmount(): float
    {
        return max(0.0, round($this->individualCoursesTotal() - (float) $this->price, 2));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_package_course')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('course_package_course.position')
            ->orderBy('courses.title');
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class, 'course_package_id');
    }
}
