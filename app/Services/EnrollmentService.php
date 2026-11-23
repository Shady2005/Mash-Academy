<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EnrollmentService
{
    /**
     * Accurately compute expiry date for days or months (handling month-end dates without overflow).
     */
    public function calculateExpiresAt(CarbonInterface $startsAt, int $durationValue, string $durationUnit): CarbonImmutable
    {
        if ($durationValue < 1) {
            throw new InvalidArgumentException('Duration value must be at least 1.');
        }

        $base = CarbonImmutable::instance($startsAt);

        return match ($durationUnit) {
            Enrollment::UNIT_DAYS => $base->addDays($durationValue),
            Enrollment::UNIT_MONTHS => $base->addMonthsNoOverflow($durationValue),
            default => throw new InvalidArgumentException("Unsupported duration unit: {$durationUnit}"),
        };
    }

    public function create(
        User $student,
        Course $course,
        int $durationValue,
        string $durationUnit,
        User $actor,
        ?PaymentRequest $paymentRequest = null,
        ?CarbonInterface $startsAt = null,
        ?string $ip = null
    ): Enrollment {
        $start = $startsAt ? CarbonImmutable::instance($startsAt) : CarbonImmutable::now();
        $expiresAt = $this->calculateExpiresAt($start, $durationValue, $durationUnit);

        $enrollment = new Enrollment();
        $enrollment->user_id = $student->id;
        $enrollment->course_id = $course->id;
        $enrollment->payment_request_id = $paymentRequest?->id;
        $enrollment->starts_at = $start;
        $enrollment->expires_at = $expiresAt;
        $enrollment->duration_value = $durationValue;
        $enrollment->duration_unit = $durationUnit;
        $enrollment->status = Enrollment::STATUS_ACTIVE;
        $enrollment->created_by = $actor->id;
        $enrollment->save();

        ActivityLogger::log(
            action: 'enrollment.created',
            subject: $enrollment,
            meta: [
                'student_id' => $student->id,
                'student_email' => $student->email,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'duration' => "{$durationValue} {$durationUnit}",
                'starts_at' => $start->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'payment_request_id' => $paymentRequest?->id,
            ],
            actor: $actor,
            ip: $ip
        );

        return $enrollment;
    }

    /**
     * Create or extend active individual enrollments for EVERY course inside a CoursePackage
     * using the exact same expiration window.
     *
     * @return Collection<int, Enrollment>
     */
    public function enrollPackage(
        User $student,
        CoursePackage $package,
        int $durationValue,
        string $durationUnit,
        User $actor,
        ?PaymentRequest $paymentRequest = null,
        ?CarbonInterface $startsAt = null,
        ?string $ip = null
    ): Collection {
        $start = $startsAt ? CarbonImmutable::instance($startsAt) : CarbonImmutable::now();
        $targetExpiresAt = $this->calculateExpiresAt($start, $durationValue, $durationUnit);
        $now = CarbonImmutable::now();

        $courses = $package->courses()->get();
        $results = collect();

        foreach ($courses as $course) {
            $existingActive = Enrollment::query()
                ->where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', Enrollment::STATUS_ACTIVE)
                ->where('expires_at', '>=', $now)
                ->orderByDesc('expires_at')
                ->first();

            if ($existingActive) {
                if ($existingActive->expires_at->lt($targetExpiresAt)) {
                    $existingActive->expires_at = $targetExpiresAt;
                }
                if ($paymentRequest && ! $existingActive->payment_request_id) {
                    $existingActive->payment_request_id = $paymentRequest->id;
                }
                $existingActive->save();
                $results->push($existingActive);
            } else {
                $enrollment = new Enrollment();
                $enrollment->user_id = $student->id;
                $enrollment->course_id = $course->id;
                $enrollment->payment_request_id = $paymentRequest?->id;
                $enrollment->starts_at = $start;
                $enrollment->expires_at = $targetExpiresAt;
                $enrollment->duration_value = $durationValue;
                $enrollment->duration_unit = $durationUnit;
                $enrollment->status = Enrollment::STATUS_ACTIVE;
                $enrollment->created_by = $actor->id;
                $enrollment->save();
                $results->push($enrollment);
            }
        }

        ActivityLogger::log(
            action: 'enrollment.package_granted',
            subject: $package,
            meta: [
                'student_id' => $student->id,
                'student_email' => $student->email,
                'package_id' => $package->id,
                'package_title' => $package->title,
                'courses_unlocked' => $courses->pluck('id')->all(),
                'duration' => "{$durationValue} {$durationUnit}",
                'expires_at' => $targetExpiresAt->toIso8601String(),
                'payment_request_id' => $paymentRequest?->id,
            ],
            actor: $actor,
            ip: $ip
        );

        return $results;
    }

    public function extend(
        Enrollment $enrollment,
        int $durationValue,
        string $durationUnit,
        User $actor,
        ?string $ip = null
    ): Enrollment {
        $now = CarbonImmutable::now();
        $anchor = ($enrollment->expires_at && $enrollment->expires_at->gt($now))
            ? CarbonImmutable::instance($enrollment->expires_at)
            : $now;

        $previousExpiresAt = $enrollment->expires_at?->toIso8601String();
        $newExpiresAt = $this->calculateExpiresAt($anchor, $durationValue, $durationUnit);

        $enrollment->expires_at = $newExpiresAt;
        $enrollment->status = Enrollment::STATUS_ACTIVE;
        $enrollment->save();

        ActivityLogger::log(
            action: 'enrollment.extended',
            subject: $enrollment,
            meta: [
                'added_duration' => "{$durationValue} {$durationUnit}",
                'previous_expires_at' => $previousExpiresAt,
                'new_expires_at' => $newExpiresAt->toIso8601String(),
            ],
            actor: $actor,
            ip: $ip
        );

        return $enrollment;
    }

    public function revoke(Enrollment $enrollment, User $actor, ?string $ip = null): Enrollment
    {
        $enrollment->status = Enrollment::STATUS_REVOKED;
        $enrollment->save();

        ActivityLogger::log(
            action: 'enrollment.revoked',
            subject: $enrollment,
            meta: [
                'student_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
            ],
            actor: $actor,
            ip: $ip
        );

        return $enrollment;
    }

    /**
     * Flip all overdue active enrollments (`expires_at < now`) to `expired`.
     */
    public function expireOverdue(?CarbonInterface $at = null): int
    {
        $now = $at ?? now();

        return Enrollment::query()
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->where('expires_at', '<', $now)
            ->update([
                'status' => Enrollment::STATUS_EXPIRED,
                'updated_at' => $now,
            ]);
    }
}
