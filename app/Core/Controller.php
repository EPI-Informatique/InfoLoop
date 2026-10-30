<?php

/**
 * Base controller providing view rendering, redirects, flash messages,
 * CSRF helpers and JSON responses to all concrete controllers.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        $data['app'] = $this->app;
        $data['request'] ??= null;
        try {
            $data['currentUser'] ??= $this->app->service(\InfoLoop\Services\AuthService::class)->currentUser();
        } catch (\PDOException) {
            $data['currentUser'] ??= null; // DB not installed yet
        }
        $data['flash'] ??= $this->app->service(Session::class)->flashes();
        $data['csrfToken'] ??= $this->app->service(Csrf::class)->token();
        $data['settings'] ??= $this->app->service(\InfoLoop\Services\SettingsService::class)->all();
        return Response::html($this->app->service(View::class)->render($template, $data), $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->service(Session::class)->flash($type, $message);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return $this->redirect(is_string($referer) && $referer !== '' ? $referer : $fallback);
    }

    /**
     * Aborts the request with a 4xx response.
     */
    protected function abort(int $status, string $message = ''): Response
    {
        $templates = [403 => 'errors/403', 404 => 'errors/404'];
        if (isset($templates[$status])) {
            return $this->view($templates[$status], ['message' => $message], $status);
        }
        return new Response($status, $message !== '' ? $message : 'Error', ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
