<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\PaymentRequest;
use App\Models\User;

class WhatsAppHelper
{
    /**
     * Normalize an Egyptian or international phone number to digits-only format for wa.me links.
     */
    public static function formatPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        // Convert leading 00 international prefix
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Convert Egyptian local mobile format (01xxxxxxxxx -> 201xxxxxxxxx)
        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            $digits = '2'.$digits;
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    public static function buildUrl(?string $phone, string $message): ?string
    {
        $formatted = self::formatPhone($phone);
        if (! $formatted) {
            return null;
        }

        return 'https://wa.me/'.$formatted.'?text='.rawurlencode($message);
    }

    public static function forEnrollment(Enrollment $enrollment, string $locale = 'en'): ?string
    {
        $student = $enrollment->user;
        $course = $enrollment->course;
        if (! $student || ! $course) {
            return null;
        }

        $remaining = $enrollment->remainingDays();
        $expiryDate = $enrollment->expires_at->format('M d, Y');

        if ($remaining <= 3 && $enrollment->isCurrentlyActive()) {
            $msg = $locale === 'ar'
                ? "مرحباً {$student->name}، تذكير من أكاديمية ماش: اشتراكك في دورة «{$course->localizedTitle('ar')}» ينتهي خلال {$remaining} أيام ({$expiryDate}). يمكنك التجديد للاستمرار دون انقطاع."
                : "Hi {$student->name}, reminder from Mash Academy: your enrollment in \"{$course->title}\" expires in {$remaining} day(s) ({$expiryDate}). Reply or renew anytime to keep your access active!";
        } else {
            $msg = $locale === 'ar'
                ? "مرحباً {$student->name}، تم تفعيل اشتراكك في دورة «{$course->localizedTitle('ar')}» على أكاديمية ماش بنجاح حتى تاريخ {$expiryDate}. نتمنى لك رحلة تعلم ممتعة!"
                : "Hi {$student->name}, your enrollment in \"{$course->title}\" at Mash Academy is active until {$expiryDate}. Happy learning!";
        }

        return self::buildUrl($student->phone, $msg);
    }

    public static function forPayment(PaymentRequest $payment, string $locale = 'en'): ?string
    {
        $item = $payment->itemTitle($locale);
        $amount = number_format((float) $payment->final_price, 0).' '.$payment->currency;

        if ($payment->isApproved()) {
            $msg = $locale === 'ar'
                ? "مرحباً {$payment->name}، تم قبول طلب الدفع رقم #{$payment->id} لـ «{$item}» ({$amount}) بنجاح على أكاديمية ماش!"
                : "Hi {$payment->name}, your payment request #{$payment->id} for \"{$item}\" ({$amount}) has been approved at Mash Academy!";
        } elseif ($payment->isRejected()) {
            $msg = $locale === 'ar'
                ? "مرحباً {$payment->name}، بخصوص طلب الدفع رقم #{$payment->id} لـ «{$item}» على أكاديمية ماش، يُرجى مراجعة الملاحظة وإعادة رفع الإيصال."
                : "Hi {$payment->name}, regarding payment request #{$payment->id} for \"{$item}\" at Mash Academy, please check the review note on My Payments and resubmit.";
        } else {
            $msg = $locale === 'ar'
                ? "مرحباً {$payment->name}، استلمنا طلب الدفع رقم #{$payment->id} لـ «{$item}» ({$amount}) وجارٍ مراجعته من إدارة أكاديمية ماش."
                : "Hi {$payment->name}, we received your payment request #{$payment->id} for \"{$item}\" ({$amount}) at Mash Academy and are reviewing it now.";
        }

        return self::buildUrl($payment->phone ?: $payment->user?->phone, $msg);
    }

    public static function forStudent(User $student, string $locale = 'en'): ?string
    {
        $msg = $locale === 'ar'
            ? "مرحباً {$student->name}، معك فريق الدعم الفني في أكاديمية ماش. كيف يمكننا مساعدتك اليوم؟"
            : "Hi {$student->name}, this is Mash Academy Student Support. How can we assist you today?";

        return self::buildUrl($student->phone, $msg);
    }
}
