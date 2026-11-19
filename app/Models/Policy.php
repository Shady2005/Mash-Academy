<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Policy extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'version',
        'is_active',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
    }

    public static function createNewVersion(string $title, string $body, ?User $actor = null): self
    {
        return DB::transaction(function () use ($title, $body, $actor): self {
            $nextVersion = ((int) static::query()->max('version')) + 1;

            static::query()->where('is_active', true)->update(['is_active' => false]);

            return static::query()->create([
                'title' => $title,
                'body' => $body,
                'version' => $nextVersion,
                'is_active' => true,
                'updated_by' => $actor?->id,
            ]);
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(PolicyAcceptance::class);
    }
}
