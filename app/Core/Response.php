<?php

/**
 * Immutable-ish HTTP response value object.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param int                  $status  HTTP status code.
     * @param string               $body    Response body.
     * @param array<string,string> $headers HTTP headers.
     */
    public function __construct(
        private int $status = 200,
        private string $body = '',
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            $status,
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, '', ['Location' => $location]);
    }

    public static function file(string $path, string $downloadName, string $contentType): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return new self(404, 'File not found', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $body = (string) file_get_contents($path);
        return new self(200, $body, [
            'Content-Type' => $contentType,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => sprintf('inline; filename="%s"', str_replace('"', '', $downloadName)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    /**
     * Sends headers and body to the client. Extracted so tests can skip it.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
