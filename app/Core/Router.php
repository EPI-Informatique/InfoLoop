<?php

/**
 * Minimal front-controller router with named routes, parameters and
 * per-route middleware support.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Router
{
    /** @var array<int, array{methods: string[], pattern: string, handler: callable, name: ?string, middleware: string[]}> */
    private array $routes = [];

    /** @var array<string, string> Middleware aliases resolved to class names. */
    private array $middlewareAliases = [];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Registers the middleware alias map.
     *
     * @param array<string, class-string> $aliases
     */
    public function middlewareAliases(array $aliases): void
    {
        $this->middlewareAliases = $aliases;
    }

    /**
     * @param string[] $methods
     * @param callable|string $handler "Class@method" or closure.
     */
    public function add(array $methods, string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'pattern' => $pattern,
            'handler' => $this->normalizeHandler($handler),
            'name' => $name,
            'middleware' => $middleware,
        ];
    }

    public function get(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler, $name, $middleware);
    }

    public function post(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['POST'], $pattern, $handler, $name, $middleware);
    }

    public function put(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['PUT'], $pattern, $handler, $name, $middleware);
    }

    public function delete(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['DELETE'], $pattern, $handler, $name, $middleware);
    }

    public function any(string $pattern, callable|string $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $pattern, $handler, $name, $middleware);
    }

    /**
     * Registers a whole route table as returned by config/routes.php.
     *
     * @param array<int, array{0: string[], 1: string, 2: callable|string, 3?: string, 4?: string[]}> $routes
     */
    public function registerRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->add($route[0], $route[1], $route[2], $route[3] ?? null, $route[4] ?? []);
        }
    }

    /**
     * Dispatches the request and returns the produced Response.
     */
    public function dispatch(Request $request): Response
    {
        $path = rtrim($request->path(), '/') ?: '/';
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }
            if (!in_array($request->method(), $route['methods'], true)) {
                $allowed = array_merge($allowed, $route['methods']);
                continue;
            }
            $handler = $route['handler'];
            return $this->runMiddleware($route['middleware'], $request, static fn (Request $req): Response => $handler($req, $params));
        }
        if ($allowed !== []) {
            return new Response(405, 'Method Not Allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        try {
            $body = $this->app->service(View::class)->render('errors/404', ['title' => 'Not found']);
        } catch (\Throwable) {
            $body = '404 Not Found';
        }
        return new Response(404, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Convenience entry point used by public/index.php.
     */
    public function routerQuery(): void
    {
        $this->dispatch(Request::fromGlobals())->send();
    }

    /**
     * Builds a URL for a named route.
     *
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $name) {
                $url = $route['pattern'];
                foreach ($params as $key => $value) {
                    $url = str_replace('{' . $key . '}', rawurlencode((string) $value), $url);
                }
                return $url;
            }
        }
        return '#unknown-route-' . $name;
    }

    /**
     * @return array<string, string|int>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        if (!is_string($regex) || !str_starts_with($regex, '#') && !str_starts_with($regex, '~')) {
            $regex = '#^' . $regex . '$#';
        } elseif (!str_ends_with($regex, '#') && !str_ends_with($regex, '~')) {
            $regex .= (str_ends_with($regex, '$') ? '' : '$') . '#';
        }
        if (preg_match($regex, $path, $matches) === 1) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return null;
    }

    /**
     * @param callable|string $handler
     * @return callable(Request, array<string, string|int>): Response
     */
    private function normalizeHandler(callable|string $handler): callable
    {
        if (is_callable($handler)) {
            return static fn (Request $req, array $params): Response => $handler($req, $params);
        }
        [$class, $method] = explode('@', $handler, 2);
        $controller = new $class($this->app);
        return static fn (Request $req, array $params): Response => $controller->{$method}($req, $params);
    }

    /**
     * @param callable(Request): Response $next
     */
    private function runMiddleware(array $middleware, Request $request, callable $next): Response
    {
        $pipeline = $next;
        foreach (array_reverse($middleware) as $alias) {
            $instance = $this->buildMiddleware($alias);
            if ($instance === null) {
                continue;
            }
            $current = $pipeline;
            $pipeline = static fn (Request $req): Response => $instance->handle($req, $current);
        }
        return $pipeline($request);
    }

    /**
     * Resolves a middleware alias, supporting parameterised aliases such as
     * "role:super_admin,admin".
     */
    private function buildMiddleware(string $alias): ?Middleware
    {
        if (str_starts_with($alias, 'role:')) {
            $roles = array_values(array_filter(explode(',', substr($alias, 5))));
            return new \InfoLoop\Http\Middleware\RoleMiddleware($this->app, $roles !== [] ? $roles : ['super_admin']);
        }
        $class = $this->middlewareAliases[$alias] ?? $alias;
        if (!is_a($class, Middleware::class, true)) {
            return null;
        }
        return new $class($this->app);
    }

    /**
     * @param callable(Request, array<string, string|int>): Response $handler
     * @param array<string, string|int> $params
     */
    private function callHandler(callable $handler, Request $request, array $params): Response
    {
        return $handler($request, $params);
    }
}
