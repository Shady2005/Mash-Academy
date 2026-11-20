<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LessonRequest extends FormRequest
{
    /**
     * Default max video upload size in KB (500 MB; also bounded by php.ini upload_max_filesize/post_max_size).
     */
    public static function maxVideoKilobytes(): int
    {
        return (int) config('mash.max_video_upload_kb', 512000);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = self::maxVideoKilobytes();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'is_published' => ['nullable', 'boolean'],
            'video' => [
                'nullable',
                'file',
                'mimes:mp4,webm',
                'mimetypes:video/mp4,video/webm,application/mp4',
                "max:{$maxKb}",
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round(self::maxVideoKilobytes() / 1024);

        return [
            'video.mimes' => 'Only MP4 and WebM video files are accepted.',
            'video.mimetypes' => 'The uploaded file is not a valid MP4 or WebM video.',
            'video.max' => "The video file exceeds the maximum allowed size ({$maxMb} MB). Check php.ini upload_max_filesize/post_max_size as well.",
        ];
    }
}
