<?php

/**
 * Admin authentication controller: login, logout, MFA enrolment and
 * MFA verification. Enforces CSRF, lockouts (in AuthService) and session
 * regeneration.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Session;
use InfoLoop\Core\Validator;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\SettingsService;
use InfoLoop\Services\TotpService;

final class AuthController extends Controller
{
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->app->service(AuthService::class)->currentUser() !== null) {
            return $this->redirect('/admin');
        }
        return $this->view('admin/login', [
            'redirect' => (string) $request->query('redirect', '/admin'),
            'branding' => $this->app->service(SettingsService::class)->branding(),
        ]);
    }

    public function login(Request $request, array $params): Response
    {
        $csrf = $this->app->service(Csrf::class);
        if (!$csrf->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('username')->string('username', 1, 100)
            ->required('password')->string('password', 1, 255);
        if (!$v->valid()) {
            $this->flash('error', 'Username and password are required.');
            return $this->redirect('/admin/login');
        }
        $input = $v->sanitized();
        try {
            $result = $this->app->service(AuthService::class)->attempt(
                (string) $input['username'],
                (string) $input['password'],
                $request->ip()
            );
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/admin/login');
        }
        if (!$result['ok']) {
            $this->flash('error', $result['error'] ?? 'Invalid credentials.');
            return $this->redirect('/admin/login');
        }
        if (!empty($result['mfa_required'])) {
            $this->app->service(Session::class)->set('mfa_user_id', (int) $result['user']['id']);
            return $this->redirect('/admin/mfa');
        }
        $redirect = (string) $request->post('redirect', '/admin');
        if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            $redirect = '/admin';
        }
        return $this->redirect($redirect);
    }

    public function logout(Request $request, array $params): Response
    {
        $this->app->service(AuthService::class)->logout();
        return $this->redirect('/admin/login');
    }

    /**
     * MFA challenge page (after password, before session).
     */
    public function showMfa(Request $request, array $params): Response
    {
        $userId = $this->app->service(Session::class)->get('mfa_user_id');
        if (!is_int($userId)) {
            return $this->redirect('/admin/login');
        }
        return $this->view('admin/mfa', []);
    }

    public function verifyMfa(Request $request, array $params): Response
    {
        $csrf = $this->app->service(Csrf::class);
        if (!$csrf->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $session = $this->app->service(Session::class);
        $userId = $session->get('mfa_user_id');
        if (!is_int($userId)) {
            return $this->redirect('/admin/login');
        }
        $user = $this->app->service(\InfoLoop\Models\User::class)->find($userId);
        if (!is_array($user) || (int) $user['mfa_enabled'] !== 1) {
            return $this->redirect('/admin/login');
        }
        $code = $request->string('code');
        $ok = $this->app->service(AuthService::class)->verifyMfa($user, $code, $request->ip());
        if (!$ok) {
            $this->flash('error', 'Invalid authentication code.');
            return $this->redirect('/admin/mfa');
        }
        $session->remove('mfa_user_id');
        return $this->redirect('/admin');
    }

    /**
     * MFA enrolment: shows QR code + secret; confirms with a code.
     */
    public function showMfaSetup(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        if ($user === null) {
            return $this->redirect('/admin/login');
        }
        $session = $this->app->service(Session::class);
        $totp = $this->app->service(TotpService::class);
        if (!$session->has('mfa_enroll_secret')) {
            $session->set('mfa_enroll_secret', $totp->generateSecret());
        }
        $secret = (string) $session->get('mfa_enroll_secret');
        $issuer = (string) $this->app->service(SettingsService::class)->branding()['brand_name'];
        return $this->view('admin/mfa_setup', [
            'secret' => $secret,
            'uri' => $totp->provisioningUri($secret, (string) $user['username'], $issuer),
        ]);
    }

    public function enableMfa(Request $request, array $params): Response
    {
        $csrf = $this->app->service(Csrf::class);
        if (!$csrf->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $session = $this->app->service(Session::class);
        $user = $this->app->service(AuthService::class)->currentUser();
        $secret = $session->get('mfa_enroll_secret');
        if ($user === null || !is_string($secret)) {
            return $this->redirect('/admin');
        }
        $totp = $this->app->service(TotpService::class);
        if (!$totp->verify($secret, $request->string('code'))) {
            $this->flash('error', 'Invalid code. Check your authenticator app clock.');
            return $this->redirect('/admin/mfa/setup');
        }
        $recovery = $totp->generateRecoveryCodes();
        $db = new \InfoLoop\Core\Database($this->app->db());
        $db->update('users', [
            'mfa_enabled' => 1,
            'mfa_secret' => $totp->encrypt($secret),
            'mfa_recovery_codes' => json_encode($totp->hashRecoveryCodes($recovery)),
        ], 'id = :id', ['id' => (int) $user['id']]);
        $session->remove('mfa_enroll_secret');
        $this->app->logger()->info('MFA enabled', ['user_id' => $user['id']]);
        return $this->view('admin/mfa_recovery', ['codes' => $recovery]);
    }

    public function disableMfa(Request $request, array $params): Response
    {
        $csrf = $this->app->service(Csrf::class);
        if (!$csrf->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $user = $this->app->service(AuthService::class)->currentUser();
        if ($user !== null) {
            $db = new \InfoLoop\Core\Database($this->app->db());
            $db->update('users', [
                'mfa_enabled' => 0,
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
            ], 'id = :id', ['id' => (int) $user['id']]);
            $this->app->logger()->info('MFA disabled', ['user_id' => $user['id']]);
            $this->flash('success', 'Two-factor authentication has been disabled.');
        }
        return $this->redirect('/admin/account');
    }
}
