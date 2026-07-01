<?php

namespace App\Http\Requests\User;

use App\Enums\UserSettingKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [];
        foreach (UserSettingKey::cases() as $key) {
            $rules[$key->value] = ['nullable', 'boolean'];
        }
        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allowedKeys = array_map(fn($case) => $case->value, UserSettingKey::cases());
            $requestKeys = array_keys($this->except(['_method', '_token']));

            foreach ($requestKeys as $key) {
                if (!in_array($key, $allowedKeys)) {
                    $validator->errors()->add($key, "invalid settings key");
                }
            }
        });
    }
}
