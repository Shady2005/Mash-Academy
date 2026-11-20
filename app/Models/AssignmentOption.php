<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentOption extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'question_id',
        'option_text',
        'is_correct',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssignmentQuestion::class, 'question_id');
    }
}
