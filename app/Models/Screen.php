<?php

/**
 * Screen (kiosk) model.
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class Screen extends Model
{
    protected string $table = 'screens';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forLocation(int $locationId): array
    {
        return $this->db->all(
            'SELECT s.*, l.name AS location_name, p.title AS default_page_title
             FROM screens s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN pages p ON p.id = s.default_page_id
             WHERE s.location_id = :lid ORDER BY s.name ASC',
            ['lid' => $locationId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allWithLocations(): array
    {
        return $this->db->all(
            'SELECT s.*, l.name AS location_name, p.title AS default_page_title, pl.name AS playlist_name
             FROM screens s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN pages p ON p.id = s.default_page_id
             LEFT JOIN playlists pl ON pl.id = s.playlist_id
             ORDER BY l.name ASC, s.name ASC'
        );
    }
}
