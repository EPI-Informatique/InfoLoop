<?php

/**
 * InfoLoop application container.
 *
 * Holds the configuration, the shared PDO connection and acts as a tiny
 * service locator so controllers can pull dependencies without globals.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

use PDO;

final class App
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, object> Shared service instances. */
    private array $services = [];

    private ?PDO $pdo = null;

    private ?Logger $logger = null;

    private ?Session $session = null;

    /**
     * @param array<string, mixed> $config Loaded configuration array.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        date_default_timezone_set((string) ($config['app']['timezone'] ?? 'UTC'));
    }

    /**
     * @param string $key Dot-notated config key, e.g. "db.host".
     * @param mixed  $default
     * @return mixed
     */
    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function allConfig(): array
    {
        return $this->config;
    }

    /**
     * Returns the shared, lazily created PDO connection.
     */
    public function db(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }
        $db = $this->config('db', $this->config('app.db', []));
        if (!is_array($db) || $db === []) {
            throw new \RuntimeException(
                'Database configuration is missing: no "db" section was found in the loaded config. ' .
                'Check that app/config/app.php is readable and returns an array (see app/bootstrap.php).'
            );
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($db['host'] ?? '127.0.0.1'),
            (int) ($db['port'] ?? 3306),
            (string) ($db['name'] ?? 'infoloop'),
            (string) ($db['charset'] ?? 'utf8mb4')
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        $this->pdo = new PDO($dsn, (string) $db['user'], (string) $db['pass'], $options);
        return $this->pdo;
    }

    /**
     * Overrides the PDO connection (used by tests and the CLI installer).
     */
    public function setDb(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    public function logger(): Logger
    {
        return $this->logger ??= new Logger(
            (string) ($this->config('log.dir') ?? $this->config('app.log.dir', INFOLOOP_STORAGE . '/logs')),
            (string) ($this->config('log.level') ?? $this->config('app.log.level', 'info'))
        );
    }

    public function session(): Session
    {
        return $this->session ??= new Session(
            (string) ($this->config('session.name') ?? $this->config('app.session.name', 'infoloopsess')),
            (int) ($this->config('session.lifetime') ?? $this->config('app.session.lifetime', 7200))
        );
    }

    /**
     * Registers or fetches a shared service instance.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param callable(App):T|null $factory
     * @return T
     */
    public function service(string $class, ?callable $factory = null): object
    {
        if (isset($this->services[$class])) {
            return $this->services[$class];
        }
        if ($factory !== null) {
            $instance = $factory($this);
        } else {
            $instance = $this->instantiate($class);
        }
        $this->services[$class] = $instance;
        return $instance;
    }

    /**
     * Instantiates a class injecting the App only when its constructor
     * asks for one (by type), leaving other params at their defaults.
     *
     * @param class-string $class
     */
    private function instantiate(string $class): object
    {
        $ctor = (new \ReflectionMethod($class, '__construct'))->getParameters();
        $args = [];
        foreach ($ctor as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), App::class, true)) {
                $args[] = $this;
            } elseif ($param->isDefaultValueAvailable()) {
                break; // leave the rest at defaults
            } else {
                $args[] = null;
            }
        }
        return new $class(...$args);
    }

    /**
     * Replaces a shared service (mainly for tests).
     *
     * @param object $instance
     */
    public function setService(object $instance): void
    {
        $this->services[$instance::class] = $instance;
    }
}
