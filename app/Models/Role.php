<?php

/**
 * Role model.
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class Role extends Model
{
    protected string $table = 'roles';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allOrdered(): array
    {
        return $this->db()->all('SELECT * FROM roles ORDER BY is_location_scoped ASC, id ASC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        return $this->findBy('name', $name);
    }
}
