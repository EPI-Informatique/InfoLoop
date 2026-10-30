<?php

/**
 * CSRF token manager.
 *
 * Tokens are random 32-byte hex strings stored in the session and compared
 * with hash_equals() to avoid timing attacks.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Csrf
{
    public function __construct(
        private readonly App $app,
        private readonly string $tokenName = '_csrf',
    ) {
    }

    public function token(): string
    {
        $session = $this->app->service(Session::class);
        $token = $session->get($this->tokenName);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set($this->tokenName, $token);
        }
        return $token;
    }

    /**
     * Validates a token coming from POST data or the X-CSRF-Token header.
     */
    public function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        return hash_equals($this->token(), $token);
    }

    public function verifyRequest(Request $request): bool
    {
        $token = $request->post($this->tokenName) ?? $request->header('X-CSRF-Token');
        return $this->verify(is_string($token) ? $token : null);
    }

    /**
     * Hidden input field snippet for forms.
     */
    public function field(): string
    {
        return sprintf('<input type="hidden" name="%s" value="%s">', e($this->tokenName), e($this->token()));
    }
}
