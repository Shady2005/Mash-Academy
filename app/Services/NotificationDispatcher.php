<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\CourseQuestion;
use App\Models\Enrollment;
use App\Models\FinalProjectSubmission;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    public function notifyPaymentSubmitted(PaymentRequest $payment): void
    {
        $recipients = User::query()
            ->whereIn('role', [UserRole::SuperAdmin1->value, UserRole::SuperAdmin2->value])
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->get();

        // Also notify course teacher if the payment is for a teacher-owned course
        if ($payment->course && $payment->course->creator && $payment->course->creator->isTeacher()) {
            $recipients = $recipients->push($payment->course->creator)->unique('id');
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $item = $payment->itemTitle('en');
        $amount = number_format((float) $payment->final_price, 0).' '.$payment->currency;

        Notification::send($recipients, new PlatformNotification(
            title: 'New Payment Request',
            message: "{$payment->name} submitted a transfer screenshot for \"{$item}\" ({$amount}).",
            url: route('admin.payments.index', ['status' => 'pending']),
            icon: 'bi-receipt-cutoff',
            category: 'payment'
        ));
    }

    public function notifyPaymentReviewed(PaymentRequest $payment): void
    {
        $student = $payment->user;
        if (! $student || $student->trashed()) {
            return;
        }

        $item = $payment->itemTitle('en');

        if ($payment->isApproved()) {
            $student->notify(new PlatformNotification(
                title: 'Payment Approved',
                message: "Your payment for \"{$item}\" has been approved! Your access is now active.",
                url: $payment->isConsultationPayment() ? route('consultations.index') : route('student.my-courses.index'),
                icon: 'bi-check-circle-fill',
                category: 'payment'
            ));
        } elseif ($payment->isRejected()) {
            $reason = $payment->admin_note ? " Reason: {$payment->admin_note}" : '';
            $student->notify(new PlatformNotification(
                title: 'Payment Rejected',
                message: "Your payment for \"{$item}\" was rejected.{$reason}",
                url: route('student.payments.index'),
                icon: 'bi-x-circle-fill',
                category: 'payment'
            ));
        }
    }

    public function notifyFinalProjectSubmitted(FinalProjectSubmission $submission): void
    {
        $course = $submission->finalProject?->course;
        if (! $course) {
            return;
        }

        $recipients = User::query()
            ->whereIn('role', [UserRole::SuperAdmin1->value, UserRole::SuperAdmin2->value])
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->get();

        if ($course->creator && $course->creator->isTeacher()) {
            $recipients = $recipients->push($course->creator)->unique('id');
        }

        $studentName = $submission->user?->name ?? 'A student';

        Notification::send($recipients, new PlatformNotification(
            title: 'Final Project Submitted',
            message: "{$studentName} submitted their Final Project for \"{$course->title}\".",
            url: route('admin.courses.submissions.index', $course),
            icon: 'bi-award-fill',
            category: 'final_project'
        ));
    }

    public function notifyFinalProjectReviewed(FinalProjectSubmission $submission): void
    {
        $student = $submission->user;
        $course = $submission->finalProject?->course;
        if (! $student || ! $course || $student->trashed()) {
            return;
        }

        $gradeText = $submission->grade !== null ? " Grade: {$submission->grade}/100." : '';

        $student->notify(new PlatformNotification(
            title: 'Final Project Reviewed',
            message: "Your Final Project for \"{$course->title}\" has been reviewed.{$gradeText}",
            url: route('student.courses.final-project.show', $course),
            icon: 'bi-patch-check-fill',
            category: 'final_project'
        ));
    }

    public function notifyExpiringEnrollment(Enrollment $enrollment): void
    {
        $student = $enrollment->user;
        $course = $enrollment->course;
        if (! $student || ! $course || $student->trashed()) {
            return;
        }

        $daysLeft = max(1, $enrollment->remainingDays());
        $expiryDate = $enrollment->expires_at->format('M d, Y');

        $student->notify(new PlatformNotification(
            title: 'Course Access Expiring Soon',
            message: "Your enrollment in \"{$course->title}\" expires in {$daysLeft} day(s) ({$expiryDate}). Renew to continue uninterrupted.",
            url: route('student.courses.subscribe', $course),
            icon: 'bi-hourglass-split',
            category: 'expiry'
        ));
    }

    public function notifyCourseQuestionReplied(CourseQuestion $question, User $replier): void
    {
        $student = $question->user;
        $course = $question->course;
        if (! $student || ! $course || $student->id === $replier->id || $student->trashed()) {
            return;
        }

        $student->notify(new PlatformNotification(
            title: 'New Q&A Reply',
            message: "{$replier->name} replied to your question in \"{$course->title}\".",
            url: route('student.courses.learn', $course),
            icon: 'bi-chat-left-text-fill',
            category: 'qa'
        ));
    }

    public function notifyConsultationBooked(Consultation $consultation): void
    {
        $recipients = User::query()
            ->whereIn('role', User::INSTRUCTOR_ROLES)
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $studentName = $consultation->user?->name ?? 'A student';
        $date = $consultation->preferred_date->format('M d, Y');

        Notification::send($recipients, new PlatformNotification(
            title: 'New 1-on-1 Consultation Request',
            message: "{$studentName} requested a {$consultation->duration_minutes}-min consultation on {$date} ({$consultation->preferred_time}): \"{$consultation->topic}\".",
            url: route('admin.consultations.index'),
            icon: 'bi-calendar-event-fill',
            category: 'consultation'
        ));
    }

    public function notifyConsultationUpdated(Consultation $consultation): void
    {
        $student = $consultation->user;
        if (! $student || $student->trashed()) {
            return;
        }

        $statusLabel = ucfirst($consultation->status);

        $student->notify(new PlatformNotification(
            title: "Consultation {$statusLabel}",
            message: "Your 1-on-1 consultation (\"{$consultation->topic}\") is now {$statusLabel}.",
            url: route('consultations.index'),
            icon: 'bi-calendar-check-fill',
            category: 'consultation'
        ));
    }
}
