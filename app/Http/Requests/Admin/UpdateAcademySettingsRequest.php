<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAcademySettingsRequest extends FormRequest
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
            'academy_name' => ['required', 'string', 'max:120'],
            'academy_name_ar' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'tagline_ar' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:3000'],
            'about_ar' => ['nullable', 'string', 'max:3000'],
            'vision' => ['nullable', 'string', 'max:2000'],
            'vision_ar' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ];
    }

    /**
     * Guard against unsafe active content inside uploaded SVG logos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $file = $this->file('logo');
                if (! $file || ! $file->isValid()) {
                    return;
                }

                $ext = strtolower((string) $file->getClientOriginalExtension());
                $mime = strtolower((string) $file->getMimeType());
                if ($ext !== 'svg' && ! str_contains($mime, 'svg')) {
                    return;
                }

                $contents = (string) file_get_contents($file->getRealPath());
                if (
                    ! preg_match('/<svg\b/i', $contents)
                    || preg_match('/<\s*script\b|on[a-z]+\s*=|javascript:|<\s*foreignObject\b/i', $contents)
                ) {
                    $validator->errors()->add('logo', 'The uploaded SVG logo must be a valid static SVG without scripts or event handlers.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.mimes' => 'The academy logo must be a PNG, JPG, JPEG, SVG, or WebP image.',
            'logo.max' => 'The academy logo may not be larger than 2 MB.',
        ];
    }
}
