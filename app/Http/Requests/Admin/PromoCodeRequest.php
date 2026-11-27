<?php

namespace App\Http\Requests\Admin;

use App\Models\PromoCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isInstructor()) {
            return false;
        }

        /** @var PromoCode|null $promoCode */
        $promoCode = $this->route('promoCode');
        if ($promoCode && $user->isTeacher() && (int) $promoCode->created_by !== (int) $user->id) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PromoCode|null $promoCode */
        $promoCode = $this->route('promoCode');
        $discountType = (string) $this->input('discount_type', PromoCode::TYPE_PERCENTAGE);

        return [
            'code' => [
                'required',
                'string',
                'min:2',
                'max:64',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->ignore($promoCode?->id),
            ],
            'discount_type' => ['required', Rule::in([PromoCode::TYPE_PERCENTAGE, PromoCode::TYPE_FIXED])],
            'discount_value' => array_filter([
                'required',
                'numeric',
                'gt:0',
                $discountType === PromoCode::TYPE_PERCENTAGE ? 'max:100' : 'max:999999.99',
            ]),
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Promo code may only contain uppercase letters, numbers, hyphens, and underscores.',
            'discount_value.max' => 'Percentage discount cannot exceed 100%.',
        ];
    }
}
