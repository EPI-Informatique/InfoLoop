<?php

/**
 * Core application configuration.
 *
 * Values are read from the environment (.env) with sane defaults so the
 * application also runs without any .env file in development.
 */

declare(strict_types=1);

use function InfoLoop\Core\env;

return [
    'name' => env('APP_NAME', 'InfoLoop'),
    'env' => env('APP_ENV', 'production'),
    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Paris'),
    'debug' => (bool) env('APP_DEBUG', false),

    'session' => [
        'name' => env('SESSION_NAME', 'infoloopsess'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
    ],

    'csrf' => [
        'token_name' => env('CSRF_TOKEN_NAME', '_csrf'),
    ],

    'db' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_NAME', 'infoloop'),
        'user' => env('DB_USER', 'infoloop'),
        'pass' => (string) env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],

    'log' => [
        'level' => strtolower((string) env('LOG_LEVEL', 'info')),
        'dir' => env('LOG_DIR', INFOLOOP_STORAGE . '/logs'),
    ],

    'upload' => [
        'max_mb' => (int) env('MAX_UPLOAD_MB', 200),
    ],

    'files' => [
        'base_dir' => INFOLOOP_PUBLIC . '/files',
        'base_url' => '/files',
    ],

    'ldap' => [
        'enabled' => (bool) env('LDAP_ENABLED', false),
        'host' => env('LDAP_HOST', ''),
        'port' => (int) env('LDAP_PORT', 389),
        'base_dn' => env('LDAP_BASE_DN', ''),
        'bind_dn' => env('LDAP_BIND_DN', ''),
        'bind_password' => (string) env('LDAP_BIND_PASSWORD', ''),
        'user_filter' => env('LDAP_USER_FILTER', '(uid={username})'),
        'group_filter' => env('LDAP_GROUP_FILTER', '(member={dn})'),
        'attr_username' => env('LDAP_ATTR_USERNAME', 'uid'),
        'attr_email' => env('LDAP_ATTR_EMAIL', 'mail'),
        'attr_display_name' => env('LDAP_ATTR_DISPLAYNAME', 'displayName'),
        'default_role' => env('LDAP_DEFAULT_ROLE', 'viewer'),
    ],
];
