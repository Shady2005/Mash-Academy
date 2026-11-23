<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Student->value)
                    ->whereNull('deleted_at'),
            ],
            'course_id' => [
                'nullable',
                'required_without:course_package_id',
                'integer',
                Rule::exists('courses', 'id')->whereNull('deleted_at'),
            ],
            'course_package_id' => [
                'nullable',
                'required_without:course_id',
                'integer',
                Rule::exists('course_packages', 'id')->whereNull('deleted_at'),
            ],
            'payment_request_id' => ['nullable', 'integer', Rule::exists('payment_requests', 'id')],
            'duration_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'duration_unit' => ['required', Rule::in([Enrollment::UNIT_DAYS, Enrollment::UNIT_MONTHS])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'Please select a valid active student account.',
        ];
    }
}
