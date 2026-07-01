<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

        public function rules(): array
    {
        return [
            /**
             * Authenticating user's email address.
             */
            'email'    => ['required', 'string', 'email'],

            /**
             * Authenticating user's password.
             */
            'password' => ['required', 'string'],
        ];
    }
}
