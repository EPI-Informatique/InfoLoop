<?php

/**
 * Current-admin account page: profile + MFA management entry point.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\AuthService;

final class AccountController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        if ($user === null) {
            return $this->redirect('/admin/login');
        }
        $full = $this->app->service(\InfoLoop\Models\User::class)->find((int) $user['id']);
        return $this->view('admin/account', ['user' => $user, 'mfaEnabled' => is_array($full) && (int) $full['mfa_enabled'] === 1]);
    }
}
