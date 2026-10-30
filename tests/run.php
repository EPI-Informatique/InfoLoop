<?php

/**
 * InfoLoop test runner (no PHPUnit dependency).
 *
 * Usage: php tests/run.php [filter]
 * Discovers tests/Unit/*Test.php, runs every public test* method and
 * reports pass/fail with a summary. Exit code is non-zero on failure.
 */

declare(strict_types=1);

error_reporting(E_ALL);

define('INFOLOOP_ROOT', dirname(__DIR__));
define('INFOLOOP_APP', INFOLOOP_ROOT . '/app');
define('INFOLOOP_PUBLIC', INFOLOOP_ROOT . '/public');
define('INFOLOOP_STORAGE', INFOLOOP_ROOT . '/storage');

spl_autoload_register(static function (string $class): void {
    $prefix = 'InfoLoop\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = INFOLOOP_APP . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once INFOLOOP_APP . '/Core/functions.php';
putenv('APP_KEY=test-key-for-unit-tests-0123456789abcdef');
$_ENV['APP_KEY'] = 'test-key-for-unit-tests-0123456789abcdef';

abstract class TestCase
{
    private int $assertions = 0;

    public function assertions(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(mixed $condition, string $message = ''): void
    {
        $this->assertions++;
        if ($condition !== true) {
            throw new RuntimeException('assertTrue failed' . ($message !== '' ? ": {$message}" : ''));
        }
    }

    protected function assertFalse(mixed $condition, string $message = ''): void
    {
        $this->assertTrue($condition === false, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                'assertSame failed%s: expected %s, got %s',
                $message !== '' ? " ({$message})" : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            throw new RuntimeException(sprintf(
                'assertEquals failed%s: expected %s, got %s',
                $message !== '' ? " ({$message})" : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertSame(null, $actual, $message);
    }

    protected function assertNotNull(mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($actual === null) {
            throw new RuntimeException('assertNotNull failed' . ($message !== '' ? ": {$message}" : ''));
        }
    }

    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("assertStringContains failed{$message}: '{$needle}' not in '{$haystack}'");
        }
    }

    protected function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException("assertStringNotContains failed{$message}: '{$needle}' found in '{$haystack}'");
        }
    }

    protected function assertMatchesRegex(string $pattern, string $subject, string $message = ''): void
    {
        $this->assertions++;
        if (preg_match($pattern, $subject) !== 1) {
            throw new RuntimeException("assertMatchesRegex failed{$message}: {$pattern} vs '{$subject}'");
        }
    }

    protected function expectException(string $class, callable $fn, string $message = ''): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $class) {
                return;
            }
            throw new RuntimeException("expectException: got " . $e::class . " instead of {$class} {$message}");
        }
        throw new RuntimeException("expectException failed{$message}: no exception thrown, expected {$class}");
    }
}

$filter = $argv[1] ?? '';
$testFiles = glob(__DIR__ . '/Unit/*Test.php') ?: [];
sort($testFiles);

$passed = 0;
$failed = 0;
$failures = [];
$totalAssertions = 0;

foreach ($testFiles as $file) {
    require_once $file;
    $class = 'InfoLoop\\Tests\\Unit\\' . basename($file, '.php');
    if (!class_exists($class)) {
        continue;
    }
    $methods = array_filter(
        get_class_methods($class),
        static fn (string $m): bool => str_starts_with($m, 'test')
    );
    foreach ($methods as $method) {
        $name = $class . '::' . $method;
        if ($filter !== '' && !str_contains($name, $filter)) {
            continue;
        }
        $instance = new $class();
        try {
            $instance->$method();
            $totalAssertions += $instance->assertions();
            $passed++;
            fwrite(STDOUT, "[PASS] {$name} ({$instance->assertions()} assertions)\n");
        } catch (Throwable $e) {
            $failed++;
            $failures[] = "{$name}: {$e->getMessage()}";
            fwrite(STDOUT, "[FAIL] {$name}\n       {$e->getMessage()}\n");
        }
    }
}

fwrite(STDOUT, sprintf("\n%d passed, %d failed, %d assertions total.\n", $passed, $failed, $totalAssertions));
if ($failed > 0) {
    fwrite(STDERR, "\nFailures:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
exit(0);
