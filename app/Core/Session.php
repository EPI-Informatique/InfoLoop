<?php

/**
 * Secure session wrapper.
 *
 * Enforces cookie security flags, absolute session lifetime and provides
 * flash messaging plus regeneration helpers used after login (anti
 * session-fixation).
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Session
{
    private bool $started = false;

    public function __construct(
        private readonly string $name = 'infoloopsess',
        private readonly int $lifetime = 7200,
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        $secure = ($this->isHttps());
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($this->name);
        session_start();
        $this->started = true;
        $this->enforceLifetime();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function forgetAll(): void
    {
        $this->start();
        $_SESSION = [];
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie($this->name, '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->started = false;
    }

    public function flash(string $type, string $message): void
    {
        $this->start();
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * @return array<string, string[]>
     */
    public function flashes(): array
    {
        $this->start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flashes) ? $flashes : [];
    }

    /**
     * Absolute lifetime enforcement: destroys sessions older than the limit.
     */
    private function enforceLifetime(): void
    {
        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);
        if ($now - $last > $this->lifetime) {
            $this->destroy();
            $this->start();
            return;
        }
        $_SESSION['_last_activity'] = $now;
    }

    private function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
