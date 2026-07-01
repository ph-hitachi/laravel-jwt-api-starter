<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /**
             * User's full name.
             */
            'name'                  => ['required', 'string', 'max:255'],

            /**
             * Unique email address.
             */
            'email'                 => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            /**
             * Password (must be confirmed).
             */
            'password'              => ['required', 'confirmed', Password::defaults()],

            /**
             * Password confirmation (must match password).
             */
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
