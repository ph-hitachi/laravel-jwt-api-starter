<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            /**
             * The user's current password.
             */
            'current_password' => ['required', 'string', 'current_password:api'],

            /**
             * The new password (must be confirmed and meet security requirements).
             */
            'password' => ['required', 'confirmed', Password::defaults()],

            /**
             * Confirmation of the new password.
             */
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
