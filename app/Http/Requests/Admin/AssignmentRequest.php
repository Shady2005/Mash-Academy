<?php

namespace App\Http\Requests\Admin;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignmentRequest extends FormRequest
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
        $type = (string) $this->input('submission_type', Assignment::TYPE_FILE);

        return [
            'title' => ['required', 'string', 'max:255'],
            'submission_type' => ['required', Rule::in(Assignment::submissionTypes())],
            'lesson_id' => [
                'nullable',
                'integer',
                Rule::exists('lessons', 'id')->where('course_id', $course->id),
            ],
            'instructions' => [
                in_array($type, [Assignment::TYPE_FILE, Assignment::TYPE_BOTH], true) ? 'required' : 'nullable',
                'string',
                'max:20000',
            ],
            'pass_mark' => [
                in_array($type, [Assignment::TYPE_MCQ, Assignment::TYPE_BOTH], true) ? 'required' : 'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'due_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instructions.required' => 'Written instructions are required for File Upload or Combined assignments.',
            'pass_mark.required' => 'A pass mark percentage (0–100) is required for MCQ or Combined assignments.',
        ];
    }
}
