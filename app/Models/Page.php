<?php

/**
 * Page model.
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class Page extends Model
{
    protected string $table = 'pages';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listWithMeta(?int $locationId = null): array
    {
        $sql = 'SELECT p.*, l.name AS location_name, u.username AS author,
                       (SELECT COUNT(*) FROM page_revisions r WHERE r.page_id = p.id) AS revision_count
                FROM pages p
                LEFT JOIN locations l ON l.id = p.location_id
                LEFT JOIN users u ON u.id = p.updated_by';
        $params = [];
        if ($locationId !== null) {
            $sql .= ' WHERE p.location_id = :lid OR p.location_id IS NULL';
            $params['lid'] = $locationId;
        }
        $sql .= ' ORDER BY p.menu_path ASC, p.menu_order ASC, p.title ASC';
        return $this->db->all($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function published(?int $locationId = null): array
    {
        $sql = 'SELECT * FROM pages WHERE is_published = 1';
        $params = [];
        if ($locationId !== null) {
            $sql .= ' AND (location_id = :lid OR location_id IS NULL)';
            $params['lid'] = $locationId;
        }
        return $this->db->all($sql . ' ORDER BY menu_order ASC, title ASC', $params);
    }
}
