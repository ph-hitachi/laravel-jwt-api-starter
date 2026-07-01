<?php

namespace Tests\Unit;

use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\ServerErrorException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_account_deactivated_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-account-deactivated', function () {
            throw new AccountDeactivatedException();
        });

        $response = $this->getJson('/api/test-exception-account-deactivated');

        $response->assertStatus(403)
                 ->assertJson([
                     'error_code'     => 'ACCOUNT_DEACTIVATED',
                     'exception_type' => 'AccountDeactivatedException',
                 ]);
    }

    public function test_unexpected_error_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-unexpected-error', function () {
            throw new ServerErrorException('Custom unexpected error');
        });

        $response = $this->getJson('/api/test-exception-unexpected-error');

        $response->assertStatus(500)
                 ->assertJson([
                     'error_code'     => 'INTERNAL_ERROR',
                     'exception_type' => 'ServerErrorException',
                     'message'        => 'Custom unexpected error',
                 ]);
    }

    public function test_authentication_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-auth', function () {
            throw new \Illuminate\Auth\AuthenticationException();
        });

        $response = $this->getJson('/api/test-exception-auth');

        $response->assertStatus(401)
                 ->assertJson([
                     'error_code'     => 'UNAUTHENTICATED',
                     'exception_type' => 'AuthenticationException',
                     'message'        => 'You are not authenticated. Please provide a valid token.',
                 ]);
    }

    public function test_authorization_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-authz', function () {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        });

        $response = $this->getJson('/api/test-exception-authz');

        $response->assertStatus(403)
                 ->assertJson([
                     'error_code'     => 'FORBIDDEN',
                     'exception_type' => 'AccessDeniedHttpException',
                     'message'        => 'You do not have permission to perform this action.',
                 ]);
    }

    public function test_access_denied_http_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-access-denied', function () {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        });

        $response = $this->getJson('/api/test-exception-access-denied');

        $response->assertStatus(403)
                 ->assertJson([
                     'error_code'     => 'FORBIDDEN',
                     'exception_type' => 'AccessDeniedHttpException',
                     'message'        => 'You do not have permission to access this resource.',
                 ]);
    }

    public function test_not_found_http_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-not-found', function () {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        });

        $response = $this->getJson('/api/test-exception-not-found');

        $response->assertStatus(404)
                 ->assertJson([
                     'error_code'     => 'NOT_FOUND',
                     'exception_type' => 'NotFoundHttpException',
                     'message'        => 'The requested endpoint does not exist.',
                 ]);
    }

    public function test_model_not_found_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-model-not-found', function () {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        });

        $response = $this->getJson('/api/test-exception-model-not-found');

        $response->assertStatus(404)
                 ->assertJson([
                     'error_code'     => 'NOT_FOUND',
                     'exception_type' => 'ModelNotFoundException',
                     'message'        => 'The requested resource was not found.',
                 ]);
    }

    public function test_validation_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-validation', function () {
            throw new \Illuminate\Validation\ValidationException(\Illuminate\Support\Facades\Validator::make([], ['field' => 'required']));
        });

        $response = $this->getJson('/api/test-exception-validation');

        $response->assertStatus(422)
                 ->assertJson([
                     'error_code'     => 'VALIDATION_ERROR',
                     'exception_type' => 'ValidationException',
                     'message'        => 'The given data was invalid.',
                 ]);
    }

    public function test_too_many_requests_http_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-too-many-requests', function () {
            throw new \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException();
        });

        $response = $this->getJson('/api/test-exception-too-many-requests');

        $response->assertStatus(429)
                 ->assertJson([
                     'error_code'     => 'TOO_MANY_REQUESTS',
                     'exception_type' => 'TooManyRequestsHttpException',
                     'message'        => 'Too many requests. Please slow down and try again in a moment.',
                 ]);
     }

    public function test_bad_request_http_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-bad-request', function () {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Malformed request syntax.');
        });

        $response = $this->getJson('/api/test-exception-bad-request');

        $response->assertStatus(400)
                 ->assertJson([
                     'error_code'     => 'BAD_REQUEST',
                     'exception_type' => 'BadRequestHttpException',
                     'message'        => 'Malformed request syntax.',
                 ]);
    }

    public function test_fallback_500_error_renders_unexpected_error_exception_format()
    {
        Route::get('/api/test-fallback-exception', function () {
            throw new \Exception('This is a completely random unhandled error.');
        });

        $response = $this->getJson('/api/test-fallback-exception');

        $response->assertStatus(500)
                 ->assertJson([
                     'error_code'     => 'INTERNAL_ERROR',
                     'exception_type' => 'ServerErrorException',
                     'message'        => 'Sorry, something went wrong on the server. Please try again later.',
                 ]);
    }

    public function test_jwt_exception_renders_correct_json_format()
    {
        Route::get('/api/test-exception-jwt', function () {
            throw new \PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException('The token could not be parsed.');
        });

        $response = $this->getJson('/api/test-exception-jwt');

        $response->assertStatus(400)
                 ->assertJson([
                     'error_code'     => 'TOKEN_COULD_NOT_PARSE',
                     'exception_type' => 'JWTException',
                     'message'        => 'The token could not be parsed.',
                 ]);
    }
}
