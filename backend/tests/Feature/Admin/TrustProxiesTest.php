<?php

namespace Tests\Feature\Admin;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    /** @test */
    public function test_trusted_proxy_x_forwarded_for_resolves_to_real_client_ip(): void
    {
        // Build a synthetic request as if it arrived from a proxy at 10.0.0.1
        // with the real client's IP in X-Forwarded-For.
        $request = Request::create('/api/up', 'GET');
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->headers->set('X-Forwarded-For', '203.0.113.55');

        TrustProxies::at('*');
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $resolvedIp = null;

        // Exercise Laravel's TrustProxies middleware path rather than mutating
        // the Request object directly.
        app(TrustProxies::class)->handle($request, function (Request $handled) use (&$resolvedIp) {
            $resolvedIp = $handled->ip();

            return response()->noContent();
        });

        $this->assertSame('203.0.113.55', $resolvedIp);
    }

    /** @test */
    public function test_untrusted_proxy_x_forwarded_for_is_ignored(): void
    {
        $request = Request::create('/api/up', 'GET');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('X-Forwarded-For', '9.9.9.9');

        $resolvedIp = null;

        app(TrustProxies::class)->handle($request, function (Request $handled) use (&$resolvedIp) {
            $resolvedIp = $handled->ip();

            return response()->noContent();
        });

        $this->assertSame('1.2.3.4', $resolvedIp);
    }

    protected function tearDown(): void
    {
        // Reset global trusted-proxy state so other tests are unaffected.
        TrustProxies::flushState();
        parent::tearDown();
    }
}
