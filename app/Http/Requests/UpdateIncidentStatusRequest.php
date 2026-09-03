<?php

namespace App\Http\Requests;

use App\Enums\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(IncidentStatus::values())],
            'note' => ['nullable', 'string', 'max:2000'],
            'assigned_unit' => ['nullable', 'string', 'max:120'],
        ];
    }
}
