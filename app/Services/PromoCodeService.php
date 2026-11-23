<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePackage;
use App\Models\PromoCode;

class PromoCodeService
{
    /**
     * Validate a promo code against a course and compute the discount breakdown.
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
        return $this->evaluateAmount($rawCode, (float) $course->price, $course->currency ?: 'EGP');
    }

    /**
     * Validate a promo code against a course package and compute the discount breakdown.
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
        return $this->evaluateAmount($rawCode, (float) $package->price, $package->currency ?: 'EGP');
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
            return [
                'valid' => false,
                'promo' => null,
                'error' => 'Please enter a promo code.',
                'original_price' => $originalPrice,
                'discount_amount' => 0.0,
                'final_price' => $originalPrice,
                'currency' => $currency,
            ];
        }

        $promo = PromoCode::query()->where('code', $normalized)->first();

        if (! $promo) {
            return [
                'valid' => false,
                'promo' => null,
                'error' => 'This promo code does not exist.',
                'original_price' => $originalPrice,
                'discount_amount' => 0.0,
                'final_price' => $originalPrice,
                'currency' => $currency,
            ];
        }

        if (! $promo->is_active) {
            return [
                'valid' => false,
                'promo' => null,
                'error' => 'This promo code is currently disabled.',
                'original_price' => $originalPrice,
                'discount_amount' => 0.0,
                'final_price' => $originalPrice,
                'currency' => $currency,
            ];
        }

        if ($promo->isExpired()) {
            return [
                'valid' => false,
                'promo' => null,
                'error' => 'This promo code has expired.',
                'original_price' => $originalPrice,
                'discount_amount' => 0.0,
                'final_price' => $originalPrice,
                'currency' => $currency,
            ];
        }

        if ($promo->isUsageLimitReached()) {
            return [
                'valid' => false,
                'promo' => null,
                'error' => 'This promo code has reached its maximum usage limit.',
                'original_price' => $originalPrice,
                'discount_amount' => 0.0,
                'final_price' => $originalPrice,
                'currency' => $currency,
            ];
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
}
