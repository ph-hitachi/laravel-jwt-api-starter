<?php

namespace Tests\Unit\Middleware;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ETagMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Register dummy routes for testing the middleware
        Route::get('/api/test-etag-get', function () {
            return response()->json(['message' => 'hello world']);
        })->middleware('api');

        Route::post('/api/test-etag-post', function () {
            return response()->json(['message' => 'created'], 201);
        })->middleware('api');

        Route::get('/api/test-etag-error', function () {
            return response()->json(['error' => 'not found'], 404);
        })->middleware('api');

        Route::get('/api/test-etag-empty', function () {
            return response('');
        })->middleware('api');
    }

    public function test_etag_is_generated_on_successful_get_request()
    {
        $response = $this->getJson('/api/test-etag-get');

        $response->assertStatus(200);
        $response->assertHeader('ETag');

        $etag = $response->headers->get('ETag');
        $expectedEtag = '"' . md5(json_encode(['message' => 'hello world'])) . '"';

        $this->assertEquals($expectedEtag, $etag);
    }

    public function test_returns_304_when_client_sends_matching_etag()
    {
        // First get to find the ETag
        $response = $this->getJson('/api/test-etag-get');
        $etag = $response->headers->get('ETag');

        // Send second request with If-None-Match header
        $response304 = $this->getJson('/api/test-etag-get', [
            'If-None-Match' => $etag,
        ]);

        $response304->assertStatus(304);
        $this->assertEmpty($response304->getContent());
    }

    public function test_returns_200_when_client_sends_mismatched_etag()
    {
        $response = $this->getJson('/api/test-etag-get', [
            'If-None-Match' => '"wrong-etag-value"',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $this->assertNotEmpty($response->getContent());
    }

    public function test_no_etag_generated_for_post_request()
    {
        $response = $this->postJson('/api/test-etag-post', ['data' => 'test']);

        $response->assertStatus(201);
        $response->assertHeaderMissing('ETag');
    }

    public function test_returns_304_for_wildcard_if_none_match()
    {
        $response = $this->getJson('/api/test-etag-get', [
            'If-None-Match' => '*',
        ]);

        $response->assertStatus(304);
        $this->assertEmpty($response->getContent());
    }

    public function test_no_etag_for_unsuccessful_request()
    {
        $response = $this->getJson('/api/test-etag-error');

        $response->assertStatus(404);
        $response->assertHeaderMissing('ETag');
    }

    public function test_no_etag_for_empty_response()
    {
        $response = $this->getJson('/api/test-etag-empty');

        $response->assertStatus(200);
        $response->assertHeaderMissing('ETag');
    }
}
