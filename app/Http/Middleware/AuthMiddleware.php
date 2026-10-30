<?php

/**
 * Requires an authenticated admin session.
 *
 * Redirects anonymous users to the admin login page and rejects API calls
 * with a 401 JSON response.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Middleware;

use InfoLoop\Core\App;
use InfoLoop\Core\Middleware;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Session;
use InfoLoop\Services\AuthService;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $session = $this->app->service(Session::class);
        $userId = $session->get('user_id');
        $auth = $this->app->service(AuthService::class);
        if (!is_int($userId) || !$auth->currentUser()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }
            $session->flash('warning', 'Please sign in to continue.');
            return Response::redirect('/admin/login?redirect=' . rawurlencode($request->path()));
        }
        return $next($request);
    }
}
