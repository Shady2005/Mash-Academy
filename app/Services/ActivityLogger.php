<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Record a sensitive action in the activity_logs audit trail.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        ?User $actor = null,
        ?string $ip = null
    ): ActivityLog {
        $resolvedActor = $actor ?? Auth::user();

        return ActivityLog::query()->create([
            'actor_id' => $resolvedActor instanceof User ? $resolvedActor->id : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta !== [] ? $meta : null,
            'ip' => $ip ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
