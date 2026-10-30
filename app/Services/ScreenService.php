<?php

/**
 * Screen / kiosk service.
 *
 * Resolves what a physical screen should display: its playlist (ordered
 * pages with per-page durations) for non-interactive rotation, its default
 * page for the inactivity timeout, and the location-scoped menu.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;

final class ScreenService
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
     * Authenticates a screen by its secret token.
     *
     * @return array<string, mixed>|null
     */
    public function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $screen = $this->db()->one('SELECT * FROM screens WHERE token = :t AND is_active = 1', ['t' => $token]);
        if (is_array($screen)) {
            $this->db()->run('UPDATE screens SET last_seen_at = NOW() WHERE id = :id', ['id' => (int) $screen['id']]);
        }
        return $screen ?: null;
    }

    /**
     * Builds the full kiosk payload for a screen: branding, menu, playlist,
     * timeouts and timezone.
     *
     * @param array<string, mixed> $screen
     * @return array<string, mixed>
     */
    public function kioskConfig(array $screen): array
    {
        $locationId = (int) $screen['location_id'];
        $pages = $this->app->service(PageService::class);
        $settings = $this->app->service(SettingsService::class);
        $branding = $settings->branding();

        $playlist = [];
        if (!empty($screen['playlist_id'])) {
            $playlist = $this->playlistItems((int) $screen['playlist_id']);
        }
        $menu = $pages->menuTree($locationId);
        $defaultPage = !empty($screen['default_page_id'])
            ? $pages->effectiveContent((int) $screen['default_page_id'])
            : null;

        return [
            'screen' => [
                'id' => (int) $screen['id'],
                'name' => $screen['name'],
                'orientation' => $screen['orientation'],
            ],
            'location' => $this->db()->one('SELECT id, name, slug, timezone FROM locations WHERE id = :id', ['id' => $locationId]),
            'branding' => $branding,
            'css_variables' => $settings->cssVariables(),
            'menu' => $menu,
            'playlist' => $playlist,
            'default_page' => $defaultPage !== null ? $this->publicPagePayload($defaultPage) : null,
            'timeouts' => [
                'enabled' => (bool) ((int) $screen['timeout_enabled'] === 1 && (int) $settings->get('defaults')['timeout_enabled'] !== false),
                'inactivity_sec' => (int) $screen['inactivity_timeout_sec'],
            ],
            'server_time' => date('c'),
        ];
    }

    /**
     * Ordered playlist with resolved page payloads.
     *
     * @return array<int, array<string, mixed>>
     */
    public function playlistItems(int $playlistId): array
    {
        $items = $this->db()->all(
            'SELECT pi.*, p.title, p.slug, p.page_type, p.location_id
             FROM playlist_items pi JOIN pages p ON p.id = pi.page_id
             WHERE pi.playlist_id = :pl AND p.is_published = 1
             ORDER BY pi.position ASC',
            ['pl' => $playlistId]
        );
        $pages = $this->app->service(PageService::class);
        return array_values(array_map(function (array $item) use ($pages): array {
            $content = $pages->effectiveContent((int) $item['page_id']);
            return [
                'page_id' => (int) $item['page_id'],
                'title' => $item['title'],
                'slug' => $item['slug'],
                'page_type' => $item['page_type'],
                'duration_sec' => (int) $item['duration_sec'],
                'payload' => $content !== null ? $this->publicPagePayload($content) : null,
            ];
        }, $items));
    }

    /**
     * Strips admin-only fields from a page payload before sending to a kiosk.
     *
     * @param array<string, mixed> $content Result of PageService::effectiveContent().
     * @return array<string, mixed>
     */
    public function publicPagePayload(array $content): array
    {
        $page = $content['page'];
        return [
            'id' => (int) $page['id'],
            'title' => $page['title'],
            'slug' => $page['slug'],
            'page_type' => $page['page_type'],
            'menu_path' => $page['menu_path'],
            'body_html' => $content['body_html'],
            'body_json' => $content['body_json'],
            'settings' => $content['settings'],
            'custom_css' => $content['custom_css'],
            'custom_js' => $content['custom_js'],
            'head_html' => $content['head_html'],
        ];
    }

    /**
     * Regenerates the screen token (e.g. after a suspected leak).
     */
    public function regenerateToken(int $screenId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db()->update('screens', ['token' => $token], 'id = :id', ['id' => $screenId]);
        return $token;
    }
}
