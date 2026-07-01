<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limits before the tests run
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_rate_limit_applies_correctly(): void
    {
        // Hit the API 60 times. They should all pass.
        for ($i = 0; $i < 60; $i++) {
            $response = $this->postJson('/api/auth/authenticate');
            $response->assertStatus(422);
        }

        // The 61st hit should be throttled (429).
        $response = $this->postJson('/api/auth/authenticate');
        $response->assertStatus(429);
    }

    public function test_rate_limit_cannot_be_bypassed_via_spoofed_ip_headers(): void
    {
        $spoofHeaders = [
            'X-Forwarded-For',
            'X-Forwarded-Host',
            'X-Real-IP',
            'Client-IP',
            'True-Client-IP',
            'CF-Connecting-IP'
        ];

        foreach ($spoofHeaders as $headerName) {
            // Flushed the cache state between each header iteration to isolate test results.
            \Illuminate\Support\Facades\Cache::flush();

            // Hit 60 times with different spoof header values.
            // If the application is secure, it will ignore untrusted proxy/gateway headers
            // and track the requests using the actual TCP client IP (which is static).
            for ($i = 0; $i < 60; $i++) {
                $response = $this->withHeader($headerName, "192.168.1.{$i}")
                    ->postJson('/api/auth/authenticate');
                $response->assertStatus(422);
            }

            // The 61st request should be throttled (429) regardless of the spoofed header value
            $response = $this->withHeader($headerName, '1.2.3.4')
                ->postJson('/api/auth/authenticate');
            $response->assertStatus(429);
        }
    }
}
