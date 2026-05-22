<?php

namespace Tests\Feature\Admin;

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

        // Without trust the proxy IP is returned (spoofing is ignored).
        $this->assertSame('10.0.0.1', $request->ip());

        // Configure exactly the same header constants used in bootstrap/app.php.
        Request::setTrustedProxies(
            ['10.0.0.1'],
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Now the real client IP is resolved from X-Forwarded-For.
        $this->assertSame('203.0.113.55', $request->ip());
    }

    /** @test */
    public function test_untrusted_proxy_x_forwarded_for_is_ignored(): void
    {
        $request = Request::create('/api/up', 'GET');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');
        $request->headers->set('X-Forwarded-For', '9.9.9.9');

        // No trusted proxies configured — forwarded header is ignored.
        Request::setTrustedProxies([], -1);

        $this->assertSame('1.2.3.4', $request->ip());
    }

    protected function tearDown(): void
    {
        // Reset global trusted-proxy state so other tests are unaffected.
        Request::setTrustedProxies([], -1);
        parent::tearDown();
    }
}
