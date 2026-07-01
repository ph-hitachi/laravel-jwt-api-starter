<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ETagMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Dynamically register a test route that uses the etag middleware alias
        Route::get('_test_etag', function () {
            return response()->json(['message' => 'hello world']);
        })->middleware('etag');
    }

    public function test_it_sets_etag_header_on_get_response()
    {
        $response = $this->get('_test_etag');

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertHeader('X-Cache-Status');
        
        $etag = $response->headers->get('ETag');
        $this->assertEquals('"' . md5($response->getContent()) . '"', $etag);
    }

    public function test_it_returns_304_when_etag_matches()
    {
        // First request to get the ETag
        $response = $this->get('_test_etag');
        $etag = $response->headers->get('ETag');

        // Second request with If-None-Match header
        $response304 = $this->get('_test_etag', [
            'If-None-Match' => $etag,
        ]);

        $response304->assertStatus(304);
        $this->assertEmpty($response304->getContent());
    }

    public function test_it_does_not_set_etag_on_post_requests()
    {
        // Register a POST route for testing
        Route::post('_test_etag_post', function () {
            return response()->json(['message' => 'created']);
        })->middleware('etag');

        $response = $this->post('_test_etag_post');

        $response->assertStatus(200);
        $response->assertHeaderMissing('ETag');
    }

    public function test_it_does_not_set_etag_on_binary_file_responses()
    {
        // Register a route that returns a binary file response
        Route::get('_test_etag_file', function () {
            return response()->download(
                __FILE__,
                'ETagMiddlewareTest.php'
            );
        })->middleware('etag');

        $response = $this->get('_test_etag_file');

        $response->assertStatus(200);
        $response->assertHeaderMissing('ETag');
    }

    public function test_it_does_not_set_etag_on_no_store_responses()
    {
        Route::get('_test_etag_no_store', function () {
            return response()->json(['message' => 'no cache'])
                ->header('Cache-Control', 'no-store, private');
        })->middleware('etag');

        $response = $this->get('_test_etag_no_store');

        $response->assertStatus(200);
        $response->assertHeaderMissing('ETag');
    }

    public function test_it_respects_existing_etag_headers()
    {
        $customEtag = '"custom-etag-value"';
        Route::get('_test_etag_existing', function () use ($customEtag) {
            return response()->json(['message' => 'custom'])
                ->header('ETag', $customEtag);
        })->middleware('etag');

        $response = $this->get('_test_etag_existing');

        $response->assertStatus(200);
        $response->assertHeader('ETag', $customEtag);

        // Test that If-None-Match works with the custom ETag
        $response304 = $this->get('_test_etag_existing', [
            'If-None-Match' => $customEtag,
        ]);
        $response304->assertStatus(304);
    }

    public function test_it_integrates_with_security_headers_middleware()
    {
        // Register route matching api/*
        Route::get('api/_test_etag_security', function () {
            return response()->json(['message' => 'secure etag']);
        })->middleware([\App\Http\Middleware\SecurityHeaders::class, 'etag']);

        $response = $this->get('api/_test_etag_security');

        $response->assertStatus(200);
        
        // ETag and Cache status should be set
        $response->assertHeader('ETag');
        $response->assertHeader('X-Cache-Status');
        
        // Cache-Control should be overridden to 'must-revalidate, no-cache, private' (alphabetically normalized by Symfony) instead of 'no-store...'
        $response->assertHeader('Cache-Control', 'must-revalidate, no-cache, private');
        
        // Vary should contain Accept and Authorization to protect against cache poisoning
        $response->assertHeader('Vary', 'Accept, Authorization');
    }
}
