<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuizQuestionRequest extends FormRequest
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
            'question_text' => ['required', 'string', 'max:2000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*' => ['nullable', 'string', 'max:500'],
            'correct_option' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $options = $this->input('options', []);
                $nonEmpty = [];
                foreach ($options as $idx => $text) {
                    if (trim((string) $text) !== '') {
                        $nonEmpty[$idx] = trim((string) $text);
                    }
                }

                if (count($nonEmpty) < 2) {
                    $validator->errors()->add('options', 'Please provide at least 2 non-empty answer options.');
                }

                $correctIndex = (int) $this->input('correct_option', -1);
                if (! array_key_exists($correctIndex, $nonEmpty)) {
                    $validator->errors()->add('correct_option', 'Please select a non-empty option as the correct answer.');
                }
            },
        ];
    }
}
