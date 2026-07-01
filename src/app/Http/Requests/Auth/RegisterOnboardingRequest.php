<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->check();
    }

    public function rules(): array
    {
        $userId = auth('api')->id();

        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:users,username,' . $userId],
            'home.place_name' => ['required', 'string', 'max:255'],
            'home.address' => ['required', 'string', 'max:1000'],
            'home.lat' => ['required', 'numeric', 'between:-90,90'],
            'home.lng' => ['required', 'numeric', 'between:-180,180'],
            'home.radius' => ['nullable', 'numeric', 'min:10', 'max:1000'],
            'safety_preference.sound_recording' => ['required', 'boolean'],
            'safety_preference.silent_mode' => ['required', 'boolean'],
        ];
    }
}
