<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessVerificationRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'assigned_unit' => ['nullable', 'string', 'max:120', 'required_if:action,approve'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assigned_unit.required_if' => 'An assigned unit is required when approving an incident.',
        ];
    }
}
