<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\CamalBarangays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:190', 'unique:users,email,'.$userId],
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'role' => ['sometimes', 'string', 'in:'.implode(',', UserRole::values())],
            'contact_number' => ['sometimes', 'nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'barangay' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', CamalBarangays::all())],
            'status' => ['sometimes', 'string', 'in:'.implode(',', UserStatus::values())],
        ];
    }
}
