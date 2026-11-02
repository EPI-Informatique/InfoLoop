<?php

/**
 * InfoLoop application bootstrap.
 *
 * Registers the PSR-4 autoloader, loads the .env file (when present),
 * loads the configuration files and prepares the error/exception handling.
 *
 * @return array<string, mixed> The loaded configuration array.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');

define('INFOLOOP_ROOT', dirname(__DIR__));
define('INFOLOOP_APP', INFOLOOP_ROOT . '/app');
define('INFOLOOP_PUBLIC', INFOLOOP_ROOT . '/public');
define('INFOLOOP_STORAGE', INFOLOOP_ROOT . '/storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'InfoLoop\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = INFOLOOP_APP . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

/**
 * Loads a .env style file into $_ENV / putenv without overriding existing values.
 *
 * @param string $path Absolute path to the .env file.
 * @return array<string, string> Parsed key/value pairs.
 */
function infoloop_load_dotenv(string $path): array
{
    $parsed = [];
    if (!is_file($path) || !is_readable($path)) {
        return $parsed;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = stripcslashes(substr($value, 1, -1));
        }
        $parsed[$key] = $value;
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
    return $parsed;
}

require_once INFOLOOP_APP . '/Core/functions.php';

infoloop_load_dotenv(INFOLOOP_ROOT . '/.env');

$config = [];
foreach (glob(INFOLOOP_APP . '/config/*.php') ?: [] as $configFile) {
    $name = basename($configFile, '.php');
    try {
        $loaded = require $configFile;
    } catch (\Throwable $e) {
        error_log(sprintf(
            '[infoloop-bootstrap] config file %s failed to load: %s (%s:%d)',
            $configFile, $e->getMessage(), $e->getFile(), $e->getLine()
        ));
        continue;
    }
    if (is_array($loaded)) {
        $config[$name] = $loaded;
    } else {
        error_log(sprintf(
            '[infoloop-bootstrap] config file %s did not return an array (got %s)',
            $configFile, get_debug_type($loaded)
        ));
    }
}

if (!isset($config['app']) || !is_array($config['app'])) {
    error_log('[infoloop-bootstrap] WARNING: app/config/app.php did not load - falling back to empty app config');
    $config['app'] = [];
}

set_exception_handler(static function (Throwable $e): void {
    $logDir = INFOLOOP_STORAGE . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $line = sprintf(
        "[%s] [error] Uncaught %s: %s in %s:%d\n%s\n",
        date('Y-m-d H:i:s'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logDir . '/exceptions-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo "<h1>Internal server error</h1><p>An unexpected error occurred. The incident has been logged.</p>";
});

return $config;
