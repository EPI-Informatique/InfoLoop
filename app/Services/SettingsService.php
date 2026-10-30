<?php

/**
 * Settings & branding service.
 *
 * Stores JSON key/value settings (branding colours, logo, slogan, global
 * defaults, LDAP config) in the `settings` table with a typed cache.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;

final class SettingsService
{
    private ?Database $db = null;

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    private const DEFAULTS = [
        'branding' => [
            'brand_name' => 'InfoLoop',
            'slogan' => 'Digital signage for everyone',
            'logo_file_id' => null,
            'primary_color' => '#2563eb',
            'secondary_color' => '#0f172a',
            'accent_color' => '#f59e0b',
            'background_color' => '#ffffff',
            'text_color' => '#0f172a',
            'font_family' => 'Inter, system-ui, sans-serif',
        ],
        'defaults' => [
            'inactivity_timeout_sec' => 30,
            'timeout_enabled' => true,
            'default_playlist_duration_sec' => 15,
        ],
        'ldap' => [
            'enabled' => false,
            'host' => '',
            'port' => 389,
            'base_dn' => '',
            'bind_dn' => '',
            'bind_password' => '',
            'user_filter' => '(uid={username})',
            'group_filter' => '(member={dn})',
            'attr_username' => 'uid',
            'attr_email' => 'mail',
            'attr_display_name' => 'displayName',
            'default_role' => 'viewer',
            'allowed_group' => '',
        ],
    ];

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * Returns all settings merged over the built-in defaults.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $merged = self::DEFAULTS;
        try {
            $rows = $this->db()->all('SELECT `key`, `value` FROM settings');
        } catch (\PDOException) {
            // Database not installed yet (installer / first 404): use defaults.
            return $this->cache = $merged;
        }
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            $merged[(string) $row['key']] = is_array($decoded) ? array_merge($merged[(string) $row['key']] ?? [], $decoded) : $decoded;
        }
        return $this->cache = $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        $branding = $this->all()['branding'] ?? [];
        return is_array($branding) ? $branding : self::DEFAULTS['branding'];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Persists one settings group and clears the cache.
     *
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void
    {
        $stmt = $this->db()->pdo()->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([':k' => $key, ':v' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
        $this->cache = null;
    }

    /**
     * CSS custom properties block derived from the branding palette.
     */
    public function cssVariables(): string
    {
        $b = $this->branding();
        $pairs = [
            '--il-primary' => $b['primary_color'] ?? '#2563eb',
            '--il-secondary' => $b['secondary_color'] ?? '#0f172a',
            '--il-accent' => $b['accent_color'] ?? '#f59e0b',
            '--il-bg' => $b['background_color'] ?? '#ffffff',
            '--il-text' => $b['text_color'] ?? '#0f172a',
            '--il-font' => $b['font_family'] ?? 'Inter, system-ui, sans-serif',
        ];
        return ':root{' . implode(';', array_map(
            static fn (string $k, string $v): string => $k . ':' . $v,
            array_keys($pairs),
            array_values($pairs)
        )) . '}';
    }
}
