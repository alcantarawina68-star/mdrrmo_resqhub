<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\CamalBarangays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', 'string', 'in:'.implode(',', UserRole::values())],
            'contact_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'barangay' => ['nullable', 'string', 'in:'.implode(',', CamalBarangays::all())],
            'status' => ['required', 'string', 'in:'.implode(',', UserStatus::values())],
        ];
    }
}
