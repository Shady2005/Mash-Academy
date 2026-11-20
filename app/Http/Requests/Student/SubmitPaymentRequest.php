<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->isStudent() || $this->user()?->isSuperAdmin());
    }

    /**
     * Strictly validate phone, transfer screenshot, and optional promo code.
     * Name and email are NEVER accepted from the client; they are always taken from the authenticated student.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,25}$/'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'screenshot' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Please provide your phone number for payment confirmation.',
            'phone.regex' => 'Please enter a valid phone number.',
            'screenshot.required' => 'Please upload your InstaPay transfer screenshot.',
            'screenshot.mimes' => 'The transfer screenshot must be a JPG, PNG, or WebP image.',
            'screenshot.max' => 'The transfer screenshot may not exceed 5 MB.',
        ];
    }
}
