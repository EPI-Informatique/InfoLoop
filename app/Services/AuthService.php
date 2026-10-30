<?php

/**
 * Authentication service.
 *
 * Handles local password verification (argon2id via password_hash), LDAP
 * authentication, account lockout, MFA (TOTP) verification and session
 * establishment. All external calls (LDAP) are wrapped in error handling.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;
use InfoLoop\Core\Session;

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private ?Database $db = null;

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * Attempts to authenticate a user with username + password.
     *
     * Returns an array describing the outcome; never leaks which part failed.
     *
     * @return array{ok: bool, mfa_required?: bool, user?: array<string, mixed>, error?: string}
     */
    public function attempt(string $username, string $password, string $ip): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }
        $this->assertNotLockedOut($username, $ip);

        $user = $this->db()->one('SELECT * FROM users WHERE username = :u', ['u' => $username]);
        $authenticated = false;
        $viaLdap = false;

        if (is_array($user) && (int) $user['is_active'] === 1) {
            if ($user['source'] === 'ldap') {
                $ldap = $this->app->service(LdapService::class);
                $authenticated = $ldap->isEnabled() && $ldap->authenticate($username, $password);
                $viaLdap = $authenticated;
            } elseif (is_string($user['password_hash']) && $user['password_hash'] !== '') {
                $authenticated = password_verify($password, $user['password_hash']);
            }
        }

        $this->recordAttempt($username, $ip, $authenticated);
        if (!$authenticated) {
            $this->registerFailure($username);
            $this->app->logger()->warning('Failed login attempt', ['username' => $username, 'ip' => $ip]);
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        $this->clearFailures((int) $user['id']);
        if ($viaLdap) {
            $this->syncLdapUser($user);
        }
        $this->db()->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $user['id']]);

        if ((int) $user['mfa_enabled'] === 1 && is_string($user['mfa_secret']) && $user['mfa_secret'] !== '') {
            return ['ok' => true, 'mfa_required' => true, 'user' => $user];
        }
        $this->establishSession((int) $user['id']);
        return ['ok' => true, 'user' => $user];
    }

    /**
     * Completes login after a valid TOTP code.
     *
     * @param array<string, mixed> $user
     */
    public function verifyMfa(array $user, string $code, string $ip): bool
    {
        $totp = $this->app->service(TotpService::class);
        $secret = $totp->decrypt((string) $user['mfa_secret']);
        if ($totp->verify($secret, $code)) {
            $this->establishSession((int) $user['id']);
            return true;
        }
        $recovery = json_decode((string) ($user['mfa_recovery_codes'] ?? '[]'), true);
        if (is_array($recovery)) {
            foreach ($recovery as $i => $hash) {
                if (is_string($hash) && hash_equals($hash, hash('sha256', $code))) {
                    unset($recovery[$i]);
                    $this->db()->update('users', ['mfa_recovery_codes' => json_encode(array_values($recovery))], 'id = :id', ['id' => (int) $user['id']]);
                    $this->establishSession((int) $user['id']);
                    return true;
                }
            }
        }
        $this->app->logger()->warning('Failed MFA attempt', ['user_id' => $user['id'] ?? null, 'ip' => $ip]);
        return false;
    }

    /**
     * @return array<string, mixed>|null Current session user (without secrets).
     */
    public function currentUser(): ?array
    {
        $session = $this->app->service(Session::class);
        $userId = $session->get('user_id');
        if (!is_int($userId)) {
            return null;
        }
        $user = $this->db()->one(
            'SELECT u.*, r.name AS role_name, r.label AS role_label, r.is_location_scoped
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id AND u.is_active = 1',
            ['id' => $userId]
        );
        if (!is_array($user)) {
            return null;
        }
        unset($user['password_hash'], $user['mfa_secret'], $user['mfa_recovery_codes']);
        return $user;
    }

    public function logout(): void
    {
        $this->app->service(Session::class)->destroy();
    }

    public function establishSession(int $userId): void
    {
        $session = $this->app->service(Session::class);
        $session->regenerate();
        $session->set('user_id', $userId);
        $session->set('login_at', time());
    }

    /**
     * Role helpers used by middleware and controllers.
     *
     * @param array<string, mixed>|null $user
     */
    public function isSuperAdmin(?array $user): bool
    {
        return ($user['role_name'] ?? '') === 'super_admin';
    }

    /**
     * Whether the user may administer the given location (null = global).
     */
    public function canManageLocation(?array $user, ?int $locationId): bool
    {
        if ($user === null) {
            return false;
        }
        if ($this->isSuperAdmin($user) || ($user['role_name'] ?? '') === 'admin') {
            return true;
        }
        return (int) ($user['is_location_scoped'] ?? 0) === 1
            && $locationId !== null
            && (int) ($user['location_id'] ?? 0) === $locationId;
    }

    /**
     * Password hashing with argon2id when available.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    private function assertNotLockedOut(string $username, string $ip): void
    {
        $locked = $this->db()->one(
            'SELECT locked_until FROM users WHERE username = :u',
            ['u' => $username]
        );
        if (is_array($locked) && !empty($locked['locked_until']) && strtotime((string) $locked['locked_until']) > time()) {
            throw new \RuntimeException('Account temporarily locked. Try again later.');
        }
        $recentFailures = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND succeeded = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
            ['ip' => $ip]
        );
        if ($recentFailures >= self::MAX_ATTEMPTS * 3) {
            throw new \RuntimeException('Too many failed attempts from this address. Try again later.');
        }
    }

    private function recordAttempt(string $username, string $ip, bool $succeeded): void
    {
        $this->db()->insert('login_attempts', [
            'username' => $username,
            'ip_address' => $ip,
            'succeeded' => $succeeded ? 1 : 0,
        ]);
    }

    private function registerFailure(string $username): void
    {
        $user = $this->db()->one('SELECT id, failed_attempts FROM users WHERE username = :u', ['u' => $username]);
        if (!is_array($user)) {
            return;
        }
        $attempts = (int) $user['failed_attempts'] + 1;
        $lockedUntil = $attempts >= self::MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
        $this->db()->update('users', [
            'failed_attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ], 'id = :id', ['id' => (int) $user['id']]);
    }

    private function clearFailures(int $userId): void
    {
        $this->db()->update('users', ['failed_attempts' => 0, 'locked_until' => null], 'id = :id', ['id' => $userId]);
    }

    /**
     * Refreshes mutable LDAP attributes on each successful LDAP login.
     *
     * @param array<string, mixed> $user
     */
    private function syncLdapUser(array $user): void
    {
        $ldap = $this->app->service(LdapService::class);
        $attrs = $ldap->fetchAttributes((string) $user['username']);
        if ($attrs === null) {
            return;
        }
        $this->db()->update('users', [
            'email' => $attrs['email'] ?? $user['email'],
            'display_name' => $attrs['display_name'] ?? $user['display_name'],
        ], 'id = :id', ['id' => (int) $user['id']]);
    }
}
