<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Lesson extends Model
{
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_BUNNY = 'bunny';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'position',
        'video_source_type',
        'bunny_library_id',
        'bunny_video_id',
        'video_path',
        'video_original_name',
        'video_mime',
        'video_size_bytes',
        'duration_seconds',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'video_size_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function isBunnyStream(): bool
    {
        return ($this->video_source_type ?: self::SOURCE_UPLOAD) === self::SOURCE_BUNNY
            && ! empty($this->bunny_video_id);
    }

    public function hasUploadedVideo(): bool
    {
        return ! empty($this->video_path) && Storage::disk('local')->exists($this->video_path);
    }

    public function hasVideo(): bool
    {
        if ($this->isBunnyStream()) {
            return $this->bunnyEmbedUrl() !== null;
        }

        return $this->hasUploadedVideo();
    }

    public function bunnyEmbedUrl(): ?string
    {
        if (! $this->isBunnyStream()) {
            return null;
        }

        $raw = trim((string) $this->bunny_video_id);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'https://iframe.mediadelivery.net/') || str_starts_with($raw, 'https://video.bunnycdn.com/')) {
            return $raw;
        }

        if (preg_match('#^(\d+)/([A-Za-z0-9-]+)$#', $raw, $m)) {
            return "https://iframe.mediadelivery.net/embed/{$m[1]}/{$m[2]}?autoplay=false&preload=true";
        }

        $libraryId = trim((string) ($this->bunny_library_id ?: AcademySetting::getValue('bunny_library_id', '')));
        if ($libraryId === '') {
            return null;
        }

        return "https://iframe.mediadelivery.net/embed/{$libraryId}/{$raw}?autoplay=false&preload=true";
    }

    public function formattedDuration(): string
    {
        if (! $this->duration_seconds || $this->duration_seconds <= 0) {
            return '—';
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function formattedFileSize(): string
    {
        if (! $this->video_size_bytes) {
            return '—';
        }

        if ($this->video_size_bytes >= 1048576) {
            return number_format($this->video_size_bytes / 1048576, 1).' MB';
        }

        return number_format($this->video_size_bytes / 1024, 1).' KB';
    }
}
