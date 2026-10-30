<?php

/**
 * PSR-3 style file logger with level filtering and date/time formatting.
 *
 * Replaces any print/echo debugging: every message is written as
 * "[Y-m-d H:i:s] [level] message {context-json}" to a daily log file.
 */

declare(strict_types=1);

namespace InfoLoop\Core;

final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5];

    private int $threshold;

    public function __construct(
        private readonly string $dir,
        string $level = 'info',
    ) {
        $this->threshold = self::LEVELS[strtolower($level)] ?? self::LEVELS['info'];
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (($this->levelValue($level) ?? 99) < $this->threshold) {
            return;
        }
        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : (string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        @file_put_contents($this->dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function levelValue(string $level): ?int
    {
        return self::LEVELS[$level] ?? null;
    }
}
