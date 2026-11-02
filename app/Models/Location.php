<?php

/**
 * Location model (office / campus / building).
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class Location extends Model
{
    protected string $table = 'locations';

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        return $this->db()->all('SELECT * FROM locations WHERE is_active = 1 ORDER BY name ASC');
    }
}
