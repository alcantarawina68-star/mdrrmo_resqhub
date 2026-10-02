<?php

namespace App\Http\Requests;

use App\Enums\IncidentType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
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
            'incident_type' => ['sometimes', 'string', 'in:'.implode(',', IncidentType::values())],
            'description' => ['sometimes', 'string', 'min:20', 'max:5000'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'assigned_unit' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
