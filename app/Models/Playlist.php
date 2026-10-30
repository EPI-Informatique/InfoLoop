<?php

/**
 * Playlist model (ordered page rotation for non-interactive screens).
 */

declare(strict_types=1);

namespace InfoLoop\Models;

use InfoLoop\Core\Model;

final class Playlist extends Model
{
    protected string $table = 'playlists';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function withItems(int $playlistId): array
    {
        return $this->db->all(
            'SELECT pi.*, p.title, p.slug, p.page_type FROM playlist_items pi
             JOIN pages p ON p.id = pi.page_id
             WHERE pi.playlist_id = :id ORDER BY pi.position ASC',
            ['id' => $playlistId]
        );
    }
}
