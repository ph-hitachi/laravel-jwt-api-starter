<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user() ? $this->user()->id : null;
        return [
            /**
             * Updated full name of the user.
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * Updated username. Must be unique.
             */
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:users,username,' . $userId],

            /**
             * Phone number attributes.
             */
            'phone_number' => ['nullable', 'string', 'max:30'],
            'phone_iso_code' => ['nullable', 'string', 'max:10'],
            'phone_dial_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
