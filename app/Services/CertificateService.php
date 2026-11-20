<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\FinalProjectSubmission;
use App\Models\User;

class CertificateService
{
    public function __construct(
        private readonly DripLockService $dripLock
    ) {}

    /**
     * Evaluate whether a student has earned a certificate for a course.
     * STRICT RULE: Locked until course completion >= required % (default 95%) AND Final Project is submitted.
     *
     * @return array{
     *   eligible: bool,
     *   reason: ?string,
     *   completion_percentage: float,
     *   required_percentage: int,
     *   submission: ?FinalProjectSubmission,
     *   certificate: ?Certificate
     * }
     */
    public function eligibilityFor(User $student, Course $course): array
    {
        $summary = $this->dripLock->courseProgressSummary($student, $course);
        $finalProject = $course->relationLoaded('finalProject')
            ? $course->finalProject
            : $course->finalProject()->first();

        $existingCertificate = Certificate::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $finalProject || ! $finalProject->is_published) {
            return [
                'eligible' => false,
                'reason' => 'This course does not have a published Final Project configured.',
                'completion_percentage' => $summary['completion_percentage'],
                'required_percentage' => $summary['required_final_project_percentage'],
                'submission' => null,
                'certificate' => $existingCertificate,
            ];
        }

        $submission = FinalProjectSubmission::query()
            ->where('final_project_id', $finalProject->id)
            ->where('user_id', $student->id)
            ->first();

        if (! $summary['final_project_unlocked']) {
            return [
                'eligible' => false,
                'reason' => "Complete at least {$summary['required_final_project_percentage']}% of the course (current: {$summary['completion_percentage']}%) and submit the Final Project to unlock your certificate.",
                'completion_percentage' => $summary['completion_percentage'],
                'required_percentage' => $summary['required_final_project_percentage'],
                'submission' => $submission,
                'certificate' => $existingCertificate,
            ];
        }

        if (! $submission) {
            return [
                'eligible' => false,
                'reason' => 'Submit your Final Project to unlock and download your Completion Certificate.',
                'completion_percentage' => $summary['completion_percentage'],
                'required_percentage' => $summary['required_final_project_percentage'],
                'submission' => null,
                'certificate' => $existingCertificate,
            ];
        }

        if ($existingCertificate && $existingCertificate->isRevoked()) {
            return [
                'eligible' => false,
                'reason' => 'This certificate has been revoked by Mash Academy administration.',
                'completion_percentage' => $summary['completion_percentage'],
                'required_percentage' => $summary['required_final_project_percentage'],
                'submission' => $submission,
                'certificate' => $existingCertificate,
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'completion_percentage' => $summary['completion_percentage'],
            'required_percentage' => $summary['required_final_project_percentage'],
            'submission' => $submission,
            'certificate' => $existingCertificate,
        ];
    }

    /**
     * Issue (or retrieve existing valid) certificate ONLY if student satisfies all requirements.
     */
    public function issueIfEligible(User $student, Course $course, ?string $ip = null): ?Certificate
    {
        $eval = $this->eligibilityFor($student, $course);

        if (! $eval['eligible'] || ! $eval['submission']) {
            return null;
        }

        if ($eval['certificate'] && $eval['certificate']->isValid()) {
            return $eval['certificate'];
        }

        $certificate = Certificate::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'final_project_submission_id' => $eval['submission']->id,
            'certificate_code' => Certificate::generateUniqueCode(),
            'issued_at' => now(),
        ]);

        ActivityLogger::log(
            action: 'certificate.issued',
            subject: $certificate,
            meta: [
                'certificate_code' => $certificate->certificate_code,
                'course_id' => $course->id,
                'course_title' => $course->title,
                'student_id' => $student->id,
            ],
            actor: $student,
            ip: $ip
        );

        return $certificate;
    }

    public function revoke(Certificate $certificate, User $actor, string $reason, ?string $ip = null): Certificate
    {
        $certificate->revoked_at = now();
        $certificate->revoked_by = $actor->id;
        $certificate->revocation_reason = trim($reason);
        $certificate->save();

        ActivityLogger::log(
            action: 'certificate.revoked',
            subject: $certificate,
            meta: [
                'certificate_code' => $certificate->certificate_code,
                'reason' => $certificate->revocation_reason,
            ],
            actor: $actor,
            ip: $ip
        );

        return $certificate;
    }

    public function reinstate(Certificate $certificate, User $actor, ?string $ip = null): Certificate
    {
        $certificate->revoked_at = null;
        $certificate->revoked_by = null;
        $certificate->revocation_reason = null;
        $certificate->save();

        ActivityLogger::log(
            action: 'certificate.reinstated',
            subject: $certificate,
            meta: ['certificate_code' => $certificate->certificate_code],
            actor: $actor,
            ip: $ip
        );

        return $certificate;
    }
}
