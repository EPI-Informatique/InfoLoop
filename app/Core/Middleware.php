<?php

/**
 * Middleware contract. Each middleware receives the request and a $next
 * callable and must return a Response (short-circuit or pass-through).
 */

declare(strict_types=1);

namespace InfoLoop\Core;

interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response;
}
