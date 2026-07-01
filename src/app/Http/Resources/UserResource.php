<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * The "data" wrapper that should be applied.
     *
     * @var string|null
     */
    public static $wrap = 'user';
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user('api') ?: auth('api')->user() ?: $request->user();

        $allowed = $user && ($user->id === $this->id || $user->role === 'admin');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->when($allowed, $this->email),
            'email_verified_at' => $this->when($allowed, $this->email_verified_at),
            'avatar' => $this->avatar_url,
            'google_id' => $this->when($allowed, $this->google_id),
            'facebook_id' => $this->when($allowed, $this->facebook_id),
            'phone_number' => $this->phone_number,
            'phone_iso_code' => $this->phone_iso_code,
            'phone_dial_code' => $this->phone_dial_code,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

