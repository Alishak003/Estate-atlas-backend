<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email:rfc,dns',
                'max:50'
            ],
            'phone' => 'nullable|regex:/^\+?[0-9]*$/', // Example phone regex
            'message' => 'required|string',
            'g-recaptcha-response' => 'required',  // You can add custom validation if necessary
        ];
    }
}
