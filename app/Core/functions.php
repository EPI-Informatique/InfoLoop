<?php

/**
 * Global view helper functions (loaded eagerly by bootstrap so they are
 * available regardless of which class was autoloaded first).
 */

declare(strict_types=1);

namespace InfoLoop\Core;

/**
 * Escapes a value for HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Marks a string as safe (already escaped / trusted HTML).
 */
function raw(string $value): string
{
    return $value;
}

/**
 * Reads an environment variable with a fallback default.
 *
 * @param string $key     Environment variable name.
 * @param mixed  $default Fallback value.
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}
