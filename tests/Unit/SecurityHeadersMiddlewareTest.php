<?php

/**
 * Unit tests for the security headers middleware.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\SecurityHeadersMiddleware;

final class SecurityHeadersMiddlewareTest extends \TestCase
{
    public function testAddsCoreHeaders(): void
    {
        $app = new App(['app' => ['url' => 'http://localhost', 'timezone' => 'UTC']]);
        $mw = new SecurityHeadersMiddleware($app);
        $request = new Request('GET', '/', [], [], [], [], [], '');
        $response = $mw->handle($request, static fn (Request $r): Response => new Response(200, 'ok'));
        $this->assertSame('ok', $response->body());
        // Inspect via send-less reflection: re-run and capture headers through withHeader chain
        $headers = (new \ReflectionProperty(Response::class, 'headers'));
        $headers->setAccessible(true);
        $value = $headers->getValue($response);
        $this->assertSame('nosniff', $value['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $value['X-Frame-Options']);
        $this->assertStringContains('default-src', $value['Content-Security-Policy']);
        $this->assertStringContains('object-src \'none\'', $value['Content-Security-Policy']);
    }

    public function testHstsOnlyOnHttps(): void
    {
        $http = new App(['app' => ['url' => 'http://example.com', 'timezone' => 'UTC']]);
        $https = new App(['app' => ['url' => 'https://example.com', 'timezone' => 'UTC']]);
        $request = new Request('GET', '/', [], [], [], [], [], '');
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $prop->setAccessible(true);

        $r1 = (new SecurityHeadersMiddleware($http))->handle($request, static fn (Request $r): Response => new Response());
        $this->assertFalse(isset($prop->getValue($r1)['Strict-Transport-Security']));

        $r2 = (new SecurityHeadersMiddleware($https))->handle($request, static fn (Request $r): Response => new Response());
        $this->assertStringContains('max-age=31536000', $prop->getValue($r2)['Strict-Transport-Security']);
    }
}
