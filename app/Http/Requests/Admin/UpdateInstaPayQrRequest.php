<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstaPayQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'instapay_qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'instapay_account_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instapay_qr.mimes' => 'The InstaPay QR image must be a PNG, JPG, JPEG, or WebP file.',
            'instapay_qr.max' => 'The InstaPay QR image may not exceed 4 MB.',
        ];
    }
}
