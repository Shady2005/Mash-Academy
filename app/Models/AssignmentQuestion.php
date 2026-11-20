<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentQuestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'assignment_id',
        'question_text',
        'position',
        'points',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(AssignmentOption::class, 'question_id')->orderBy('id');
    }
}
