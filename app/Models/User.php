<?php

/**
 * User model (admin accounts: local or LDAP-synced).
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function withRoles(): array
    {
        return $this->db()->all(
            'SELECT u.*, r.name AS role_name, r.label AS role_label, r.is_location_scoped, l.name AS location_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN locations l ON l.id = u.location_id
             ORDER BY u.username ASC'
        );
    }
}
