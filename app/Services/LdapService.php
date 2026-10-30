<?php

/**
 * LDAP / Active Directory authentication service.
 *
 * All ldap_* calls are guarded: when the ldap extension is missing or the
 * server is unreachable the service fails closed (no login) and logs the
 * error instead of crashing.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;

final class LdapService
{
    private ?Database $db = null;

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    public function isEnabled(): bool
    {
        $config = $this->config();
        return (bool) ($config['enabled'] ?? false) && extension_loaded('ldap');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        $fromDb = $this->app->service(SettingsService::class)->get('ldap', []);
        $fromEnv = $this->app->config('ldap', []);
        $merged = array_merge($fromEnv, is_array($fromDb) ? $fromDb : []);
        return is_array($merged) ? $merged : [];
    }

    /**
     * Authenticates against the directory and returns the user's DN.
     */
    public function authenticate(string $username, string $password): bool
    {
        if ($password === '' || !$this->isEnabled()) {
            return false;
        }
        $conn = $this->connect();
        if ($conn === null) {
            return false;
        }
        try {
            $dn = $this->findUserDn($conn, $username);
            if ($dn === null) {
                $this->app->logger()->warning('LDAP user not found', ['username' => $username]);
                return false;
            }
            if ($this->isInAllowedGroup($conn, $dn)) {
                $bound = @ldap_bind($conn, $dn, $password);
                if ($bound) {
                    $this->app->logger()->info('LDAP authentication succeeded', ['username' => $username]);
                }
                return $bound;
            }
            $this->app->logger()->warning('LDAP user not in allowed group', ['username' => $username]);
            return false;
        } catch (\Throwable $e) {
            $this->app->logger()->error('LDAP authentication error', ['error' => $e->getMessage()]);
            return false;
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Fetches email/display name for syncing a local user record.
     *
     * @return array{email?: string, display_name?: string}|null
     */
    public function fetchAttributes(string $username): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $conn = $this->connect();
        if ($conn === null) {
            return null;
        }
        try {
            $dn = $this->findUserDn($conn, $username);
            if ($dn === null) {
                return null;
            }
            $config = $this->config();
            $attrs = [$config['attr_email'] ?? 'mail', $config['attr_display_name'] ?? 'displayName'];
            $result = @ldap_search($conn, $dn, '(objectClass=*)', $attrs);
            if ($result === false) {
                return null;
            }
            $entries = @ldap_get_entries($conn, $result);
            if (!is_array($entries) || !isset($entries[0])) {
                return null;
            }
            $entry = $entries[0];
            $emailAttr = $config['attr_email'] ?? 'mail';
            $nameAttr = $config['attr_display_name'] ?? 'displayName';
            return [
                'email' => $this->firstAttr($entry, $emailAttr),
                'display_name' => $this->firstAttr($entry, $nameAttr),
            ];
        } catch (\Throwable $e) {
            $this->app->logger()->error('LDAP attribute fetch error', ['error' => $e->getMessage()]);
            return null;
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Synchronises LDAP users into the local users table.
     *
     * @return array{created: int, updated: int}
     */
    public function syncUsers(): array
    {
        if (!$this->isEnabled()) {
            return ['created' => 0, 'updated' => 0];
        }
        $conn = $this->connect();
        if ($conn === null) {
            throw new \RuntimeException('Cannot connect to LDAP server.');
        }
        $config = $this->config();
        try {
            $filter = str_replace('{username}', '*', (string) $config['user_filter']);
            $result = @ldap_search($conn, (string) $config['base_dn'], $filter, [
                $config['attr_username'] ?? 'uid',
                $config['attr_email'] ?? 'mail',
                $config['attr_display_name'] ?? 'displayName',
            ]);
            if ($result === false) {
                throw new \RuntimeException('LDAP search failed: ' . ldap_error($conn));
            }
            $entries = @ldap_get_entries($conn, $result);
            $created = 0;
            $updated = 0;
            $roleId = (int) ($this->db()->scalar('SELECT id FROM roles WHERE name = :r', ['r' => $config['default_role'] ?? 'viewer']) ?? 5);
            foreach (is_array($entries) ? $entries : [] as $entry) {
                if (!is_array($entry) || !isset($entry['count'])) {
                    continue;
                }
                $username = $this->firstAttr($entry, $config['attr_username'] ?? 'uid');
                if ($username === null) {
                    continue;
                }
                $email = $this->firstAttr($entry, $config['attr_email'] ?? 'mail') ?? $username . '@ldap.local';
                $displayName = $this->firstAttr($entry, $config['attr_display_name'] ?? 'displayName') ?? $username;
                $existing = $this->db()->one('SELECT id FROM users WHERE username = :u', ['u' => $username]);
                if (is_array($existing)) {
                    $this->db()->update('users', [
                        'email' => $email,
                        'display_name' => $displayName,
                        'source' => 'ldap',
                        'is_active' => 1,
                    ], 'id = :id', ['id' => (int) $existing['id']]);
                    $updated++;
                } else {
                    $this->db()->insert('users', [
                        'username' => $username,
                        'email' => $email,
                        'display_name' => $displayName,
                        'password_hash' => null,
                        'role_id' => $roleId,
                        'source' => 'ldap',
                        'is_active' => 1,
                    ]);
                    $created++;
                }
            }
            $this->app->logger()->info('LDAP sync completed', ['created' => $created, 'updated' => $updated]);
            return ['created' => $created, 'updated' => $updated];
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * @return resource|\LDAP\Connection|null
     */
    private function connect(): mixed
    {
        $config = $this->config();
        if (($config['host'] ?? '') === '') {
            return null;
        }
        try {
            $conn = @ldap_connect((string) $config['host'], (int) ($config['port'] ?? 389));
            if ($conn === false) {
                return null;
            }
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);
            $bindDn = (string) ($config['bind_dn'] ?? '');
            $bindPass = (string) ($config['bind_password'] ?? '');
            if ($bindDn !== '') {
                if (!@ldap_bind($conn, $bindDn, $bindPass)) {
                    $this->app->logger()->error('LDAP bind failed', ['error' => ldap_error($conn)]);
                    return null;
                }
            } elseif (!@ldap_bind($conn)) {
                $this->app->logger()->error('LDAP anonymous bind failed', ['error' => ldap_error($conn)]);
                return null;
            }
            return $conn;
        } catch (\Throwable $e) {
            $this->app->logger()->error('LDAP connection error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param resource|\LDAP\Connection $conn
     */
    private function findUserDn(mixed $conn, string $username): ?string
    {
        $config = $this->config();
        $filter = str_replace('{username}', $this->escapeLdap($username), (string) $config['user_filter']);
        $result = @ldap_search($conn, (string) $config['base_dn'], $filter, ['dn']);
        if ($result === false) {
            return null;
        }
        $entries = @ldap_get_entries($conn, $result);
        if (is_array($entries) && ($entries['count'] ?? 0) > 0) {
            return $entries[0]['dn'] ?? null;
        }
        return null;
    }

    /**
     * @param resource|\LDAP\Connection $conn
     */
    private function isInAllowedGroup(mixed $conn, string $dn): bool
    {
        $config = $this->config();
        $group = (string) ($config['allowed_group'] ?? '');
        if ($group === '') {
            return true; // no restriction configured
        }
        $filter = str_replace('{dn}', $this->escapeLdap($dn), (string) ($config['group_filter'] ?? '(member={dn})'));
        $result = @ldap_search($conn, (string) $config['base_dn'], $filter, ['cn']);
        if ($result === false) {
            return false;
        }
        $entries = @ldap_get_entries($conn, $result);
        if (is_array($entries) && ($entries['count'] ?? 0) > 0) {
            return true;
        }
        // Fall back to checking the user's memberOf attribute.
        $result = @ldap_search($conn, $dn, '(objectClass=*)', ['memberOf']);
        if ($result === false) {
            return false;
        }
        $entries = @ldap_get_entries($conn, $result);
        $memberOf = is_array($entries) && isset($entries[0]['memberof']) ? $entries[0]['memberof'] : [];
        foreach (is_array($memberOf) ? $memberOf : [] as $key => $value) {
            if (is_int($key) && stripos((string) $value, $group) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function firstAttr(array $entry, string $attr): ?string
    {
        $lower = strtolower($attr);
        if (!isset($entry[$lower]) || !is_array($entry[$lower])) {
            return null;
        }
        $value = $entry[$lower][0] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function escapeLdap(string $value): string
    {
        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value
        );
    }
}
