<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'in:'.implode(',', AnnouncementCategory::values())],
            'severity' => ['required', 'string', 'in:'.implode(',', Severity::values())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
