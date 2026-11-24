<?php

namespace App\Http\Requests\Admin;

use App\Models\AcademySetting;
use App\Models\Lesson;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LessonRequest extends FormRequest
{
    /**
     * Allowed video file extensions.
     *
     * @var list<string>
     */
    public const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v'];

    /**
     * Default max video upload size in KB (2097152 KB = 2 GB; also bounded by php.ini upload_max_filesize/post_max_size).
     */
    public static function maxVideoKilobytes(): int
    {
        return (int) config('mash.max_video_upload_kb', 2097152);
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
            'video_source_type' => ['nullable', Rule::in([Lesson::SOURCE_UPLOAD, Lesson::SOURCE_BUNNY])],
            'bunny_library_id' => ['nullable', 'string', 'max:64'],
            'bunny_video_id' => ['nullable', 'string', 'max:500'],
            'video' => [
                'nullable',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime,video/x-m4v,application/mp4,application/octet-stream',
                "max:{$maxKb}",
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, self::ALLOWED_VIDEO_EXTENSIONS, true)) {
                        $fail('Only MP4, WebM, MOV, and M4V video files are accepted.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sourceType = (string) ($this->input('video_source_type') ?: Lesson::SOURCE_UPLOAD);
                if ($sourceType !== Lesson::SOURCE_BUNNY) {
                    return;
                }

                $videoId = trim((string) $this->input('bunny_video_id', ''));
                if ($videoId === '') {
                    $validator->errors()->add('bunny_video_id', 'Please enter a Bunny.net Stream Video ID or Embed URL.');

                    return;
                }

                $isFullEmbed = str_starts_with($videoId, 'https://iframe.mediadelivery.net/')
                    || str_starts_with($videoId, 'https://video.bunnycdn.com/')
                    || preg_match('#^\d+/[A-Za-z0-9-]+$#', $videoId);

                if (! $isFullEmbed) {
                    $libraryId = trim((string) ($this->input('bunny_library_id') ?: AcademySetting::getValue('bunny_library_id', '')));
                    if ($libraryId === '') {
                        $validator->errors()->add(
                            'bunny_library_id',
                            'Enter a Bunny.net Library ID (or configure the default Bunny Library ID once in Academy & Branding Settings).'
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round(self::maxVideoKilobytes() / 1024);

        return [
            'video.uploaded' => 'The video failed to upload. Ensure the file does not exceed PHP upload_max_filesize ('.ini_get('upload_max_filesize').') or post_max_size ('.ini_get('post_max_size').').',
            'video.mimetypes' => 'Only standard video formats (MP4, WebM, MOV, M4V) are accepted.',
            'video.max' => "The video file exceeds the maximum allowed size ({$maxMb} MB / 2 GB).",
        ];
    }
}
