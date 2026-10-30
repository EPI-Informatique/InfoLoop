<?php

/**
 * Unit tests for the Router: pattern matching with parameters, named-route
 * URL building, 404/405 handling and middleware pipeline order.
 */

declare(strict_types=1);

namespace InfoLoop\Tests\Unit;

use InfoLoop\Core\App;
use InfoLoop\Core\Middleware;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Router;
use InfoLoop\Core\View;

final class RouterTest extends \TestCase
{
    private function app(): App
    {
        return new App([
            'app' => ['timezone' => 'UTC'],
            'log' => ['dir' => sys_get_temp_dir(), 'level' => 'error'],
        ]);
    }

    private function request(string $method, string $uri): Request
    {
        return new Request($method, $uri, [], [], [], [], [], '');
    }

    public function testMatchesStaticRoute(): void
    {
        $router = new Router($this->app());
        $router->get('/hello', static fn (Request $r, array $p): Response => new Response(200, 'hi'));
        $response = $router->dispatch($this->request('GET', '/hello'));
        $this->assertSame(200, $response->status());
        $this->assertSame('hi', $response->body());
    }

    public function testMatchesParameters(): void
    {
        $router = new Router($this->app());
        $router->get('/page/{slug}', static fn (Request $r, array $p): Response => new Response(200, $p['slug'] ?? ''));
        $response = $router->dispatch($this->request('GET', '/page/company-news'));
        $this->assertSame('company-news', $response->body());
    }

    public function testTrailingSlashIsTolerated(): void
    {
        $router = new Router($this->app());
        $router->get('/x', static fn (Request $r, array $p): Response => new Response(200, 'ok'));
        $this->assertSame(200, $router->dispatch($this->request('GET', '/x/'))->status());
    }

    public function testReturns404ForUnknown(): void
    {
        $router = new Router($this->app());
        $router->get('/x', static fn (Request $r, array $p): Response => new Response(200, 'ok'));
        // View rendering for 404 requires the views dir; instead check status code only
        $response = $router->dispatch($this->request('GET', '/nope'));
        $this->assertSame(404, $response->status());
    }

    public function testReturns405WhenMethodMismatch(): void
    {
        $router = new Router($this->app());
        $router->post('/only-post', static fn (Request $r, array $p): Response => new Response(200, 'ok'));
        $response = $router->dispatch($this->request('GET', '/only-post'));
        $this->assertSame(405, $response->status());
    }

    public function testNamedRouteUrl(): void
    {
        $router = new Router($this->app());
        $router->get('/admin/pages/{id}/edit', static fn (Request $r, array $p): Response => new Response(), 'page.edit');
        $this->assertSame('/admin/pages/42/edit', $router->url('page.edit', ['id' => 42]));
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $app = $this->app();
        $short = new class ($app) implements Middleware {
            public function __construct(private App $app) {}
            public function handle(Request $request, callable $next): Response
            {
                return new Response(403, 'blocked');
            }
        };
        $router = new Router($app);
        $router->middlewareAliases(['short' => $short::class]);
        $router->get('/secret', static fn (Request $r, array $p): Response => new Response(200, 'ok'), null, ['short']);
        $response = $router->dispatch($this->request('GET', '/secret'));
        $this->assertSame(403, $response->status());
        $this->assertSame('blocked', $response->body());
    }

    public function testMiddlewarePassesThroughWhenNotBlocking(): void
    {
        $app = $this->app();
        $tagger = new class ($app) implements Middleware {
            public function __construct(private App $app) {}
            public function handle(Request $request, callable $next): Response
            {
                return $next($request)->withHeader('X-Tagged', 'yes');
            }
        };
        $router = new Router($app);
        $router->middlewareAliases(['tag' => $tagger::class]);
        $router->get('/x', static fn (Request $r, array $p): Response => new Response(200, 'ok'), null, ['tag']);
        $response = $router->dispatch($this->request('GET', '/x'));
        $this->assertSame(200, $response->status());
        $prop = new \ReflectionProperty(Response::class, 'headers');
        $prop->setAccessible(true);
        $this->assertSame('yes', $prop->getValue($response)['X-Tagged'] ?? null);
    }

    public function testRoleAliasParsesParameters(): void
    {
        $app = $this->app();
        $router = new Router($app);
        $router->get('/admin-only', static fn (Request $r, array $p): Response => new Response(200, 'ok'), null, ['role:super_admin']);
        // No session user -> RoleMiddleware returns 401 JSON
        $response = $router->dispatch($this->request('GET', '/admin-only'));
        $this->assertSame(401, $response->status());
    }
}
