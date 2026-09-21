<?php

namespace App\Http\Requests;

use App\Enums\IncidentType;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;

class CallerIncidentRequest extends FormRequest
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
            'incident_type' => ['required', 'string', 'in:'.implode(',', IncidentType::values())],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'string', 'in:'.implode(',', Priority::values())],
            'caller_name' => ['nullable', 'string', 'max:120'],
            'caller_contact' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'assigned_unit' => ['nullable', 'string', 'max:120'],
        ];
    }
}
