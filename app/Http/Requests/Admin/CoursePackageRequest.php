<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoursePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active && ! $user->isBlocked() && $user->isSuperAdmin());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'short_description_ar' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'min:10', 'max:20000'],
            'description_ar' => ['nullable', 'string', 'max:20000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['nullable', 'string', 'max:10'],
            'thumbnail' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'instapay_qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'instapay_note' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_ids.required' => 'Please select at least one course to include in this package.',
            'course_ids.min' => 'Please select at least one course to include in this package.',
            'thumbnail.mimes' => 'The package thumbnail must be a PNG, JPG, JPEG, or WebP image.',
            'instapay_qr.mimes' => 'The package InstaPay QR must be a PNG, JPG, JPEG, or WebP image.',
        ];
    }
}
