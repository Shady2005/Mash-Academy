<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FinalProjectRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['required', 'string', 'min:20', 'max:30000'],
            'required_completion_percentage' => ['required', 'integer', 'min:50', 'max:100'],
            'is_published' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,zip,png,jpg,jpeg', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachment.mimes' => 'Project brief attachment must be a PDF, DOC, DOCX, ZIP, PNG, or JPG file.',
            'attachment.max' => 'Project brief attachment may not exceed 20 MB.',
        ];
    }
}
