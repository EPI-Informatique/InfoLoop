<?php

/**
 * Role-based access control middleware.
 *
 * Constructed with the list of roles allowed to pass, e.g. ['super_admin', 'admin'].
 * Location-scoped admins additionally pass through CanAccess checks in controllers.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Middleware;

use InfoLoop\Core\App;
use InfoLoop\Core\Middleware;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\AuthService;

final class RoleMiddleware implements Middleware
{
    /** @param string[] $roles */
    public function __construct(
        private readonly App $app,
        private readonly array $roles = ['super_admin'],
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = $this->app->service(AuthService::class);
        $user = $auth->currentUser();
        if ($user === null) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }
        if (!in_array($user['role'], $this->roles, true)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
            return new Response(403, 'Forbidden', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return $next($request);
    }
}
