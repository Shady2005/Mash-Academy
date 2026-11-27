<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\PromoCode;

class PromoCodeService
{
    /**
     * Validate a promo code against a single course (strictly scoped to course creator/instructor).
     *
     * @return array{
     *   valid: bool,
     *   promo: ?PromoCode,
     *   error: ?string,
     *   original_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   currency: string
     * }
     */
    public function evaluate(string $rawCode, Course $course): array
    {
        $result = $this->evaluateAmount($rawCode, (float) $course->price, $course->currency ?: 'EGP');

        if (! $result['valid'] || ! $result['promo']) {
            return $result;
        }

        if ((int) $result['promo']->created_by !== (int) $course->created_by) {
            return $this->fail(
                __('messages.promo_wrong_instructor'),
                $result['original_price'],
                $result['currency']
            );
        }

        return $result;
    }

    /**
     * Validate a promo code against a course package:
     * valid ONLY if package.created_by matches promo.created_by AND every course in the package belongs to that same instructor.
     *
     * @return array{
     *   valid: bool,
     *   promo: ?PromoCode,
     *   error: ?string,
     *   original_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   currency: string
     * }
     */
    public function evaluateForPackage(string $rawCode, CoursePackage $package): array
    {
        $result = $this->evaluateAmount($rawCode, (float) $package->price, $package->currency ?: 'EGP');

        if (! $result['valid'] || ! $result['promo']) {
            return $result;
        }

        $ownerId = (int) $result['promo']->created_by;
        $package->loadMissing('courses');

        if ((int) $package->created_by !== $ownerId || $package->courses->isEmpty()) {
            return $this->fail(
                __('messages.promo_wrong_instructor'),
                $result['original_price'],
                $result['currency']
            );
        }

        $allCoursesMatchOwner = $package->courses->every(
            fn (Course $course): bool => (int) $course->created_by === $ownerId
        );

        if (! $allCoursesMatchOwner) {
            return $this->fail(
                __('messages.promo_wrong_instructor'),
                $result['original_price'],
                $result['currency']
            );
        }

        return $result;
    }

    /**
     * Validate a promo code against a paid 1-on-1 consultation:
     * valid ONLY if the selected consultation instructor matches promo.created_by.
     *
     * @return array{
     *   valid: bool,
     *   promo: ?PromoCode,
     *   error: ?string,
     *   original_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   currency: string
     * }
     */
    public function evaluateForConsultation(string $rawCode, ?int $instructorId, float $price, string $currency = 'EGP'): array
    {
        $result = $this->evaluateAmount($rawCode, $price, $currency);

        if (! $result['valid'] || ! $result['promo']) {
            return $result;
        }

        if ($instructorId === null || (int) $result['promo']->created_by !== $instructorId) {
            return $this->fail(
                __('messages.promo_wrong_instructor'),
                $result['original_price'],
                $result['currency']
            );
        }

        return $result;
    }

    /**
     * @return array{
     *   valid: bool,
     *   promo: ?PromoCode,
     *   error: ?string,
     *   original_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   currency: string
     * }
     */
    public function evaluateAmount(string $rawCode, float $price, string $currency = 'EGP'): array
    {
        $originalPrice = round($price, 2);
        $currency = $currency ?: 'EGP';
        $normalized = strtoupper(trim($rawCode));

        if ($normalized === '') {
            return $this->fail('Please enter a promo code.', $originalPrice, $currency);
        }

        $promo = PromoCode::query()->where('code', $normalized)->first();

        if (! $promo) {
            return $this->fail('This promo code does not exist.', $originalPrice, $currency);
        }

        if (! $promo->is_active) {
            return $this->fail('This promo code is currently disabled.', $originalPrice, $currency);
        }

        if ($promo->isExpired()) {
            return $this->fail('This promo code has expired.', $originalPrice, $currency);
        }

        if ($promo->isUsageLimitReached()) {
            return $this->fail('This promo code has reached its maximum usage limit.', $originalPrice, $currency);
        }

        $discountAmount = $promo->calculateDiscount($originalPrice);
        $finalPrice = $promo->calculateFinalPrice($originalPrice);

        return [
            'valid' => true,
            'promo' => $promo,
            'error' => null,
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'currency' => $currency,
        ];
    }

    /**
     * @return array{
     *   valid: bool,
     *   promo: ?PromoCode,
     *   error: string,
     *   original_price: float,
     *   discount_amount: float,
     *   final_price: float,
     *   currency: string
     * }
     */
    private function fail(string $error, float $originalPrice, string $currency): array
    {
        return [
            'valid' => false,
            'promo' => null,
            'error' => $error,
            'original_price' => $originalPrice,
            'discount_amount' => 0.0,
            'final_price' => $originalPrice,
            'currency' => $currency,
        ];
    }
}
