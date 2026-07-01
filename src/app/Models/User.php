<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

use App\Support\Cacheable;

#[Fillable(['name', 'username', 'email', 'avatar_url', 'google_id', 'facebook_id', 'is_onboarding_completed', 'phone_number', 'phone_iso_code', 'phone_dial_code', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
/**
 * @mixin \App\Support\CacheBuilder
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Cacheable;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_onboarding_completed' => 'boolean',
        ];
    }

    public function places()
    {
        return $this->hasMany(Place::class);
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    protected static function booted()
    {
        static::saved(function ($user) {
            if ($user->isDirty('username')) {
                $oldUsername = $user->getOriginal('username');
                if ($oldUsername) {
                    \Illuminate\Support\Facades\Cache::forget('username_available_' . $oldUsername);
                }
                if ($user->username) {
                    \Illuminate\Support\Facades\Cache::forget('username_available_' . $user->username);
                }
            }
        });
    }
}
