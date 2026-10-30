<?php

/**
 * Adds a hardened set of security response headers to every response:
 * CSP, X-Frame-Options, nosniff, referrer policy and HSTS on HTTPS.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class SecurityHeadersMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $csp = "default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob: https:; "
            . "script-src 'self' 'unsafe-inline' https://www.youtube.com https://www.youtube-nocookie.com; "
            . "style-src 'self' 'unsafe-inline'; frame-src https://www.youtube.com https://www.youtube-nocookie.com; "
            . "connect-src 'self'; font-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'";
        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
        ];
        $isHttps = str_starts_with((string) $this->app->config('app.url'), 'https://');
        if ($isHttps) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
