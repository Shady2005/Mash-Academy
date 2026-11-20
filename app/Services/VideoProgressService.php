<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Carbon\CarbonImmutable;

class VideoProgressService
{
    /**
     * Process a video playback heartbeat and enforce strict 100% full-watch + anti-skip rules.
     *
     * @return array{
     *   progress: LessonProgress,
     *   completed: bool,
     *   just_completed: bool,
     *   skipped_blocked: bool,
     *   allowed_position: int,
     *   effective_duration: int,
     *   watched_percentage: float,
     *   message: ?string
     * }
     */
    public function recordHeartbeat(
        User $student,
        Lesson $lesson,
        float $currentTime,
        float $reportedDuration,
        int $deltaSeconds,
        bool $ended
    ): array {
        $now = CarbonImmutable::now();

        $progress = LessonProgress::query()->firstOrNew([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
        ]);

        if (! $progress->exists) {
            $progress->watched_seconds = 0;
            $progress->max_reached_seconds = 0;
            $progress->last_position_seconds = 0;
        }

        // Determine authoritative duration (server lesson duration takes precedence to prevent client tampering)
        $effectiveDuration = $this->resolveAuthoritativeDuration($lesson, $progress, $reportedDuration);
        if (! $progress->duration_seconds && $effectiveDuration > 0) {
            $progress->duration_seconds = $effectiveDuration;
        }

        $safeCurrentTime = max(0, min($effectiveDuration, (int) round($currentTime)));
        $safeDelta = max(0, min(30, $deltaSeconds));

        // Calculate maximum plausible elapsed watch time since previous heartbeat
        if ($progress->last_heartbeat_at) {
            $realElapsed = max(0, $now->diffInSeconds($progress->last_heartbeat_at, true));
            // Allow up to 1.5x playback speed + 5s network jitter buffer, capped at 30s per ping
            $maxAllowedDelta = min(30, (int) ceil(($realElapsed * 1.5) + 5));
        } else {
            // First heartbeat in a session cannot claim more than 15 seconds of watch time
            $maxAllowedDelta = min(15, $effectiveDuration);
        }

        $wasCompleted = $progress->isCompleted();
        $skippedBlocked = false;
        $message = null;

        // Detect forward skip attempt beyond verified frontier (only enforced prior to completion)
        $maxReachablePosition = min($effectiveDuration, $progress->max_reached_seconds + $maxAllowedDelta);
        if (! $wasCompleted && $safeCurrentTime > ($maxReachablePosition + 3)) {
            $skippedBlocked = true;
            $message = 'Fast-forwarding ahead is disabled. Watch the full video from start to finish to unlock the next lesson.';
            // Do NOT advance max_reached_seconds or accept skipped watch time
            $progress->last_position_seconds = $progress->max_reached_seconds;
        } else {
            $verifiedDelta = min($safeDelta, $maxAllowedDelta);
            $progress->watched_seconds = min($effectiveDuration, $progress->watched_seconds + $verifiedDelta);
            $progress->max_reached_seconds = min($effectiveDuration, max($progress->max_reached_seconds, $safeCurrentTime));
            $progress->last_position_seconds = $safeCurrentTime;
        }

        $progress->last_heartbeat_at = $now;

        // Strict 100% full-watch completion rule:
        // Triggered ONLY when video reaches actual end (`ended === true`), verified frontier reached the end,
        // AND cumulative verified watched_seconds confirms full continuous watch (>= 98% for browser rounding tolerance).
        $justCompleted = false;
        if (! $wasCompleted && $ended && ! $skippedBlocked) {
            $endThreshold = max(1, $effectiveDuration - 2);
            $minVerifiedWatched = max(1, (int) floor($effectiveDuration * 0.98));

            if ($progress->max_reached_seconds >= $endThreshold && $progress->watched_seconds >= $minVerifiedWatched) {
                $progress->watched_seconds = $effectiveDuration;
                $progress->max_reached_seconds = $effectiveDuration;
                $progress->last_position_seconds = $effectiveDuration;
                $progress->completed_at = $now;
                $justCompleted = true;
                $message = 'Lesson completed (100% watched)! Next lesson is now unlocked.';
            } else {
                $skippedBlocked = true;
                $message = 'You must watch 100% of the entire video from start to finish before this lesson can be marked complete.';
            }
        }

        $progress->save();

        $watchedPercentage = $effectiveDuration > 0
            ? min(100.0, round(($progress->watched_seconds / $effectiveDuration) * 100, 1))
            : ($progress->isCompleted() ? 100.0 : 0.0);

        return [
            'progress' => $progress,
            'completed' => $progress->isCompleted(),
            'just_completed' => $justCompleted,
            'skipped_blocked' => $skippedBlocked,
            'allowed_position' => $progress->max_reached_seconds,
            'effective_duration' => $effectiveDuration,
            'watched_percentage' => $watchedPercentage,
            'message' => $message,
        ];
    }

    private function resolveAuthoritativeDuration(Lesson $lesson, LessonProgress $progress, float $reportedDuration): int
    {
        if ($lesson->duration_seconds && $lesson->duration_seconds > 0) {
            return (int) $lesson->duration_seconds;
        }

        if ($progress->duration_seconds && $progress->duration_seconds > 0) {
            return (int) $progress->duration_seconds;
        }

        return max(1, (int) round($reportedDuration));
    }
}
