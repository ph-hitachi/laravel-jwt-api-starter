<?php
namespace App\Support\Traits;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait Authenticatable
{
    public function responseWithToken(string $access_token, User $user = null)
    {
        return new JsonResponse([
            'user'          => $user ?: auth()->user(),
            'authorization' => [
                'access_token' => $access_token,
                'token_type'   => 'bearer',
                'expires_in'   => auth()->factory()->getTTL() * 60,
            ],
        ]);
    }
}
