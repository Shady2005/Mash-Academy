<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active && ($user->isSuperAdmin() || $user->isTeacher()));
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
            'has_demo_video' => ['nullable', 'boolean'],
            'demo_video_type' => ['nullable', 'in:upload,bunny,youtube'],
            'demo_video_value' => ['nullable', 'string', 'max:1000'],
            'demo_video_file' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime,video/x-m4v,application/mp4,application/octet-stream',
                'max:512000',
            ],
            'is_published' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:1', 'max:3'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('is_featured')) {
                    return;
                }

                /** @var Course|null $currentCourse */
                $currentCourse = $this->route('course');

                $featuredCount = Course::query()
                    ->where('is_featured', true)
                    ->when($currentCourse instanceof Course, fn ($q) => $q->where('id', '!=', $currentCourse->id))
                    ->count();

                if ($featuredCount >= Course::MAX_FEATURED) {
                    $validator->errors()->add(
                        'is_featured',
                        'Maximum of 3 featured courses allowed on the Home Page. Please unfeature another course first.'
                    );
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
            'thumbnail.mimes' => 'The course thumbnail must be a PNG, JPG, JPEG, or WebP image.',
            'thumbnail.max' => 'The course thumbnail may not exceed 2 MB.',
            'instapay_qr.mimes' => 'The course InstaPay QR code must be a PNG, JPG, JPEG, or WebP image.',
            'instapay_qr.max' => 'The course InstaPay QR code may not exceed 4 MB.',
        ];
    }
}
