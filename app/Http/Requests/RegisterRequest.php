<?php

namespace App\Http\Requests;

use App\Support\CamalBarangays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)],
            'contact_number' => ['nullable', 'string', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'barangay' => ['nullable', 'string', 'in:'.implode(',', CamalBarangays::all())],
        ];
    }
}
