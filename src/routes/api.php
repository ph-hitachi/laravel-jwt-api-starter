<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\User\ProfileController as UserProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are under /api (prefix applied by bootstrap/app.php).
| Rate limiting: throttle:60,1 applied globally via the api middleware group.
|
*/

// ── Public routes (no auth) ────────────────────────────────────────────────
Route::post('/auth/authenticate', [AuthController::class, 'authenticate']);
// Route::post('/auth/login',        [AuthController::class, 'login']);

// ── Authenticated routes (any role) ────────────────────────────────────────
Route::middleware(['auth:api', 'active'])->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // ── Profile (Shared Customer/Seller/Admin) ─────────────────────────
    Route::middleware('role:user,admin')->prefix('user')->group(function () {
        Route::get('/me',                       [UserProfileController::class, 'me']);
        Route::put('/profile',                  [UserProfileController::class, 'update']);
        Route::patch('/avatar',                 [UserProfileController::class, 'updateAvatar']);
        Route::patch('/settings',               [UserProfileController::class, 'updateSettings']);
        Route::get('/username',                 [UserProfileController::class, 'checkUsername']);
        // Route::put('/password',                 [UserProfileController::class, 'updatePassword']);
    });

    // ── Admin ─────────────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // Users
        Route::get('/users',                    [AdminUserController::class, 'index']);
        Route::get('/users/{user}',             [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/activate',  [AdminUserController::class, 'activate']);
        Route::patch('/users/{user}/deactivate',[AdminUserController::class, 'deactivate']);
        Route::patch('/users/{user}/role',      [AdminUserController::class, 'updateRole']);
        Route::delete('/users/{user}',          [AdminUserController::class, 'destroy']);
    });
});
