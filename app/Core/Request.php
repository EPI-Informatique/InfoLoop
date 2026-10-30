<?php

/**
 * Value object wrapping the current HTTP request.
 *
 * Normalises superglobals and provides typed accessors plus input
 * validation helpers. All user input must go through these accessors.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Request
{
    /**
     * @param string               $method  HTTP method, upper-cased.
     * @param string               $uri     Request URI path.
     * @param array<string,mixed>  $query   $_GET.
     * @param array<string,mixed>  $post    $_POST.
     * @param array<string,mixed>  $cookies $_COOKIE.
     * @param array<string,mixed>  $files   $_FILES.
     * @param array<string,mixed>  $server  $_SERVER.
     * @param string               $body    Raw request body.
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $post,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
        private readonly string $body = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $uri,
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        return $path === '' ? '/' : $path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function allInput(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Returns a trimmed string input, or the default when absent/empty.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Returns an integer input within an optional range.
     */
    public function int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = $this->input($key, $default);
        $int = is_numeric($value) ? (int) $value : $default;
        if ($min !== null && $int < $min) {
            $int = $min;
        }
        if ($max !== null && $int > $max) {
            $int = $max;
        }
        return $int;
    }

    /**
     * Returns a bool input, accepting checkbox-style values.
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, null);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @return array<int, string>
     */
    public function arrayOfStrings(string $key): array
    {
        $value = $this->input($key, []);
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(array_map(
            static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
            $value
        ), static fn (string $v): bool => $v !== ''));
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>|null Normalised $_FILES entry or null.
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['tmp_name'], $file['name'], $file['error'])) {
            return null;
        }
        return $file;
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * Decodes a JSON request body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? $this->server[$name] ?? null;
        return $value === null ? null : (string) $value;
    }

    public function ip(): string
    {
        $trusted = (string) ($this->server['TRUSTED_PROXIES'] ?? '');
        if ($trusted === '' && isset($this->server['HTTP_X_FORWARDED_FOR'])) {
            $candidates = array_map('trim', explode(',', (string) $this->server['HTTP_X_FORWARDED_FOR']));
            return filter_var(end($candidates), FILTER_VALIDATE_IP) ?: '0.0.0.0';
        }
        return filter_var($this->server['REMOTE_ADDR'] ?? '0.0.0.0', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = (string) $this->header('Accept');
        return $this->isAjax() || str_contains($accept, 'application/json');
    }

    /**
     * @return array<string, string>
     */
    public function server(): array
    {
        return $this->server;
    }
}
