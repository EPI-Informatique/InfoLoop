<?php

/**
 * Engagement statistics service for the admin dashboard.
 *
 * Aggregates page_views per page, per location and per screen over a
 * configurable time window. All queries are aggregate-only (no personal
 * data is stored: viewers are anonymous tokens).
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;

final class StatsService
{
    private ?Database $db = null;

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * Records a kiosk-side event (view/interaction) - called by the public API.
     */
    public function record(int $pageId, ?int $screenId, ?int $locationId, ?string $viewerToken, string $eventType = 'view'): void
    {
        if (!in_array($eventType, ['view', 'interaction'], true)) {
            $eventType = 'view';
        }
        $this->db()->insert('page_views', [
            'page_id' => $pageId,
            'screen_id' => $screenId,
            'location_id' => $locationId,
            'viewer_token' => $viewerToken !== null ? hash('sha256', $viewerToken) : null,
            'event_type' => $eventType,
        ]);
    }

    /**
     * Top pages by views within the last N days.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topPages(int $days = 30, ?int $locationId = null, int $limit = 10): array
    {
        [$where, $params] = $this->locationFilter($locationId);
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        return $this->db()->all(
            "SELECT p.id, p.title, p.slug, p.location_id, p.page_type,
                    COUNT(*) AS views,
                    SUM(event_type = 'interaction') AS interactions,
                    MAX(occurred_at) AS last_view_at
             FROM page_views pv JOIN pages p ON p.id = pv.page_id
             WHERE pv.occurred_at >= :since {$where}
             GROUP BY p.id ORDER BY views DESC LIMIT " . $limit,
            array_merge(['since' => $since], $params)
        );
    }

    /**
     * Views per location.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byLocation(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        return $this->db()->all(
            "SELECT l.id, l.name, l.slug, COUNT(*) AS views,
                    COUNT(DISTINCT pv.page_id) AS distinct_pages,
                    COUNT(DISTINCT pv.screen_id) AS active_screens
             FROM page_views pv
             LEFT JOIN locations l ON l.id = pv.location_id
             WHERE pv.occurred_at >= :since
             GROUP BY l.id, l.name, l.slug ORDER BY views DESC",
            ['since' => $since]
        );
    }

    /**
     * Daily view series for charts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dailySeries(int $days = 30, ?int $locationId = null): array
    {
        [$where, $params] = $this->locationFilter($locationId);
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $rows = $this->db()->all(
            "SELECT DATE(pv.occurred_at) AS day, COUNT(*) AS views, SUM(pv.event_type='interaction') AS interactions
             FROM page_views pv LEFT JOIN pages p ON p.id = pv.page_id
             WHERE pv.occurred_at >= :since {$where}
             GROUP BY DATE(pv.occurred_at) ORDER BY day ASC",
            array_merge(['since' => $since], $params)
        );
        $series = [];
        $cursor = strtotime(date('Y-m-d', time() - $days * 86400));
        $today = strtotime(date('Y-m-d'));
        $byDay = array_column($rows, null, 'day');
        while ($cursor <= $today) {
            $day = date('Y-m-d', $cursor);
            $series[] = [
                'day' => $day,
                'views' => (int) ($byDay[$day]['views'] ?? 0),
                'interactions' => (int) ($byDay[$day]['interactions'] ?? 0),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }
        return $series;
    }

    /**
     * Per-screen activity summary.
     *
     * @return array<int, array<string, mixed>>
     */
    public function screens(?int $locationId = null, int $days = 7): array
    {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $where = '';
        $params = ['since' => $since];
        if ($locationId !== null) {
            $where = ' AND s.location_id = :lid';
            $params['lid'] = $locationId;
        }
        return $this->db()->all(
            "SELECT s.id, s.name, s.token, s.is_active, s.last_seen_at, l.name AS location_name,
                    (SELECT COUNT(*) FROM page_views pv WHERE pv.screen_id = s.id AND pv.occurred_at >= :since) AS views_7d
             FROM screens s LEFT JOIN locations l ON l.id = s.location_id
             WHERE 1=1 {$where} ORDER BY views_7d DESC",
            $params
        );
    }

    /**
     * Headline numbers for the dashboard.
     *
     * @return array<string, int>
     */
    public function headline(?int $locationId = null, int $days = 30): array
    {
        [$where, $params] = $this->locationFilter($locationId);
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $params['since'] = $since;
        return [
            'views' => (int) $this->db()->scalar("SELECT COUNT(*) FROM page_views pv LEFT JOIN pages p ON p.id = pv.page_id WHERE pv.occurred_at >= :since {$where}", $params),
            'interactions' => (int) $this->db()->scalar("SELECT COUNT(*) FROM page_views pv LEFT JOIN pages p ON p.id = pv.page_id WHERE pv.occurred_at >= :since AND pv.event_type='interaction' {$where}", $params),
            'pages' => (int) $this->db()->scalar('SELECT COUNT(*) FROM pages WHERE is_published = 1'),
            'screens' => (int) $this->db()->scalar('SELECT COUNT(*) FROM screens WHERE is_active = 1'),
            'media' => (int) $this->db()->scalar('SELECT COUNT(*) FROM media_files'),
            'online_screens' => (int) $this->db()->scalar('SELECT COUNT(*) FROM screens WHERE is_active = 1 AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)'),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function locationFilter(?int $locationId): array
    {
        if ($locationId === null) {
            return ['', []];
        }
        // All page_views queries must alias the table as "pv" (and join
        // pages as "p" when present) for these predicates to resolve.
        return [' AND (pv.location_id = :lid OR p.location_id = :lid2)', ['lid' => $locationId, 'lid2' => $locationId]];
    }
}
