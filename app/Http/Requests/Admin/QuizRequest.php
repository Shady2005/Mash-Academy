<?php

namespace App\Http\Requests\Admin;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'lesson_id' => [
                'nullable',
                'integer',
                Rule::exists('lessons', 'id')->where('course_id', $course->id),
            ],
            'pass_mark' => ['required', 'integer', 'min:0', 'max:100'],
            'is_required_for_next' => ['nullable', 'boolean'],
        ];
    }
}
