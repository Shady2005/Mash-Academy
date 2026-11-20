<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    public const MAX_FEATURED = 3;

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
        'is_featured',
        'featured_order',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_featured' => 'boolean',
            'featured_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $counter = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('is_featured', true)
            ->orderByRaw('CASE WHEN featured_order IS NULL THEN 99 ELSE featured_order END ASC')
            ->orderByDesc('created_at');
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

        return Storage::disk('public')->url($this->thumbnail_path);
    }

    public function courseInstaPayQrUrl(): ?string
    {
        if (! $this->instapay_qr_path) {
            return null;
        }

        return Storage::disk('public')->url($this->instapay_qr_path);
    }

    /**
     * Resolve the active InstaPay QR URL for student checkout:
     * 1. Course-specific QR -> 2. Course Instructor's personal default QR -> 3. Global Academy QR fallback.
     *
     * @return array{url: ?string, source: string, note: ?string}
     */
    public function resolveInstaPayDetails(): array
    {
        $creator = $this->relationLoaded('creator') ? $this->creator : $this->creator()->first();

        $url = null;
        $source = 'none';

        if (! empty($this->instapay_qr_path)) {
            $url = Storage::disk('public')->url($this->instapay_qr_path);
            $source = 'course';
        } elseif ($creator && ! empty($creator->instapay_qr_path)) {
            $url = Storage::disk('public')->url($creator->instapay_qr_path);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position')->orderBy('id');
    }

    public function publishedLessons(): HasMany
    {
        return $this->lessons()->where('is_published', true);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class)->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('id');
    }

    public function finalProject(): HasOne
    {
        return $this->hasOne(FinalProject::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
