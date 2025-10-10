<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ChangePasswordRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string|min:8', // Current password must be at least 8 characters
            'new_password' => 'required|string|min:8|confirmed', // Ensure the new password is confirmed
            'new_password_confirmation' => 'required|string|min:8', // Add the confirmation field validation
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'new_password.confirmed' => 'The new password and confirmation password do not match.',
            'current_password.required' => 'The current password field is required.',
            'current_password.min' => 'The current password must be at least 8 characters.',
        ];
    }
}

