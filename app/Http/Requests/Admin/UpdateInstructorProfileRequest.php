<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstructorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active && $user->isInstructor());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'instructor_title' => ['nullable', 'string', 'max:160'],
            'instructor_title_ar' => ['nullable', 'string', 'max:160'],
            'instructor_bio' => ['nullable', 'string', 'max:2000'],
            'instructor_bio_ar' => ['nullable', 'string', 'max:2000'],
            'portfolio_url' => ['nullable', 'url:http,https', 'max:255'],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'github_url' => ['nullable', 'url:http,https', 'max:255'],
            'avatar' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'instapay_qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'instapay_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'portfolio_url.url' => 'Portfolio / Website URL must be a valid http:// or https:// link.',
            'linkedin_url.url' => 'LinkedIn URL must be a valid http:// or https:// link.',
            'github_url.url' => 'GitHub URL must be a valid http:// or https:// link.',
            'avatar.mimes' => 'Profile photo must be a PNG, JPG, JPEG, or WebP image.',
            'avatar.max' => 'Profile photo may not exceed 2 MB.',
            'instapay_qr.mimes' => 'InstaPay QR Code must be a PNG, JPG, JPEG, or WebP image.',
            'instapay_qr.max' => 'InstaPay QR Code may not exceed 4 MB.',
        ];
    }
}
