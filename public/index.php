<?php

/**
 * InfoLoop public entry point.
 *
 * This is the ONLY PHP file that should live inside the nginx document root.
 * All application code lives in ../app and is never directly reachable.
 */

declare(strict_types=1);

$config = require_once dirname(__DIR__) . '/app/bootstrap.php';

use InfoLoop\Core\App;
use InfoLoop\Core\Request;
use InfoLoop\Core\Router;

$app = new App($config);
$router = new Router($app);
$router->middlewareAliases([
    'web' => \InfoLoop\Core\SecurityHeadersMiddleware::class,
    'auth' => \InfoLoop\Http\Middleware\AuthMiddleware::class,
]);
$router->registerRoutes(require dirname(__DIR__) . '/app/config/routes.php');

$router->dispatch(Request::fromGlobals())->send();
