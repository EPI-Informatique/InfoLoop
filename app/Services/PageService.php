<?php

/**
 * Page & revision service (BookStack-style versioning).
 *
 * Every save creates a new row in page_revisions and points
 * pages.current_revision_id at it. Revisions can be viewed, restored and
 * deleted individually (deleting a revision frees space and detaches any
 * media usage recorded against it).
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;

final class PageService
{
    public const TYPES = ['article', 'gallery', 'pdf', 'iframe', 'video', 'youtube', 'html', 'table', 'clock'];

    private ?Database $db = null;

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * Creates a page with its first revision.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, page_id?: int, error?: string}
     */
    public function create(array $data, ?int $userId): array
    {
        $slug = $this->uniqueSlug((string) ($data['slug'] ?? ''), $data['location_id'] !== null ? (int) $data['location_id'] : null);
        if ($slug === '') {
            return ['ok' => false, 'error' => 'A valid slug is required.'];
        }
        $pageType = in_array($data['page_type'] ?? 'article', self::TYPES, true) ? $data['page_type'] : 'article';
        $this->db()->beginTransaction();
        try {
            $pageId = $this->db()->insert('pages', [
                'location_id' => $data['location_id'] !== null ? (int) $data['location_id'] : null,
                'title' => mb_substr((string) $data['title'], 0, 255),
                'slug' => $slug,
                'menu_path' => mb_substr((string) ($data['menu_path'] ?? ''), 0, 512),
                'page_type' => $pageType,
                'is_published' => !empty($data['is_published']) ? 1 : 0,
                'show_in_menu' => !empty($data['show_in_menu']) ? 1 : 0,
                'menu_order' => (int) ($data['menu_order'] ?? 0),
                'settings' => json_encode($data['settings'] ?? new \stdClass()),
                'custom_css' => $data['custom_css'] ?? null,
                'custom_js' => $data['custom_js'] ?? null,
                'head_html' => $data['head_html'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $revisionId = $this->insertRevision($pageId, 1, $data, $userId, 'Initial revision');
            $this->db()->update('pages', ['current_revision_id' => $revisionId], 'id = :id', ['id' => $pageId]);
            $this->db()->commit();
            $this->app->logger()->info('Page created', ['page_id' => $pageId, 'slug' => $slug]);
            return ['ok' => true, 'page_id' => $pageId];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->app->logger()->error('Page creation failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not create the page.'];
        }
    }

    /**
     * Saves a new revision of an existing page.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, revision_id?: int, error?: string}
     */
    public function saveRevision(int $pageId, array $data, ?int $userId, string $changeSummary = ''): array
    {
        $page = $this->db()->one('SELECT * FROM pages WHERE id = :id', ['id' => $pageId]);
        if (!is_array($page)) {
            return ['ok' => false, 'error' => 'Page not found.'];
        }
        $this->db()->beginTransaction();
        try {
            $next = ((int) $this->db()->scalar('SELECT COALESCE(MAX(revision_number),0)+1 FROM page_revisions WHERE page_id = :p', ['p' => $pageId]));
            $sanitizer = $this->app->service(HtmlSanitizer::class);
            $bodyHtml = isset($data['body_html']) ? $sanitizer->sanitize((string) $data['body_html']) : null;
            $revisionId = $this->insertRevision($pageId, $next, [
                'title' => $data['title'] ?? $page['title'],
                'body_html' => $bodyHtml,
                'body_json' => $data['body_json'] ?? null,
                'settings' => $data['settings'] ?? json_decode((string) $page['settings'], true),
                'custom_css' => $data['custom_css'] ?? $page['custom_css'],
                'custom_js' => $data['custom_js'] ?? $page['custom_js'],
                'head_html' => $data['head_html'] ?? $page['head_html'],
            ], $userId, $changeSummary);

            $updates = [
                'title' => mb_substr((string) ($data['title'] ?? $page['title']), 0, 255),
                'menu_path' => mb_substr((string) ($data['menu_path'] ?? $page['menu_path']), 0, 512),
                'page_type' => in_array($data['page_type'] ?? $page['page_type'], self::TYPES, true) ? $data['page_type'] : $page['page_type'],
                'is_published' => !empty($data['is_published']) ? 1 : 0,
                'show_in_menu' => !empty($data['show_in_menu']) ? 1 : 0,
                'menu_order' => (int) ($data['menu_order'] ?? $page['menu_order']),
                'settings' => json_encode($data['settings'] ?? json_decode((string) $page['settings'], true)),
                'custom_css' => $data['custom_css'] ?? $page['custom_css'],
                'custom_js' => $data['custom_js'] ?? $page['custom_js'],
                'head_html' => $data['head_html'] ?? $page['head_html'],
                'current_revision_id' => $revisionId,
                'updated_by' => $userId,
            ];
            if (isset($data['location_id'])) {
                $updates['location_id'] = $data['location_id'] !== null ? (int) $data['location_id'] : null;
            }
            $this->db()->update('pages', $updates, 'id = :id', ['id' => $pageId]);

            $media = $this->app->service(MediaService::class);
            $payload = ($bodyHtml ?? '') . ' ' . (string) json_encode($data['body_json'] ?? []);
            foreach ($media->extractMediaIds($payload) as $mediaId) {
                $media->recordUsage($mediaId, $pageId, $revisionId, 'revision');
            }
            $this->db()->commit();
            $this->app->logger()->info('Page revision saved', ['page_id' => $pageId, 'revision' => $next]);
            return ['ok' => true, 'revision_id' => $revisionId];
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            $this->app->logger()->error('Revision save failed', ['page_id' => $pageId, 'error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not save the revision.'];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function revisions(int $pageId): array
    {
        return $this->db()->all(
            'SELECT r.*, u.username AS author FROM page_revisions r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.page_id = :p ORDER BY r.revision_number DESC',
            ['p' => $pageId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revision(int $revisionId): ?array
    {
        return $this->db()->one('SELECT * FROM page_revisions WHERE id = :id', ['id' => $revisionId]);
    }

    /**
     * Restores an old revision as the new current revision (new row, keeps history).
     */
    public function restore(int $pageId, int $revisionId, ?int $userId): array
    {
        $revision = $this->revision($revisionId);
        if (!is_array($revision) || (int) $revision['page_id'] !== $pageId) {
            return ['ok' => false, 'error' => 'Revision not found for this page.'];
        }
        return $this->saveRevision($pageId, [
            'title' => $revision['title'],
            'body_html' => $revision['body_html'],
            'body_json' => $revision['body_json'] !== null ? json_decode((string) $revision['body_json'], true) : null,
            'settings' => $revision['settings'] !== null ? json_decode((string) $revision['settings'], true) : [],
            'custom_css' => $revision['custom_css'],
            'custom_js' => $revision['custom_js'],
            'head_html' => $revision['head_html'],
        ], $userId, 'Restored revision #' . $revision['revision_number']);
    }

    /**
     * Deletes a single revision (and its media usage rows). The current
     * revision cannot be deleted.
     */
    public function deleteRevision(int $pageId, int $revisionId): array
    {
        $page = $this->db()->one('SELECT current_revision_id FROM pages WHERE id = :id', ['id' => $pageId]);
        if (!is_array($page)) {
            return ['ok' => false, 'error' => 'Page not found.'];
        }
        if ((int) $page['current_revision_id'] === $revisionId) {
            return ['ok' => false, 'error' => 'The current revision cannot be deleted.'];
        }
        $this->db()->delete('page_revisions', 'id = :id AND page_id = :p', ['id' => $revisionId, 'p' => $pageId]);
        $this->app->logger()->info('Revision deleted', ['page_id' => $pageId, 'revision_id' => $revisionId]);
        return ['ok' => true];
    }

    /**
     * Returns the effective content of a page (current revision merged with page meta).
     *
     * @return array<string, mixed>|null
     */
    public function effectiveContent(int $pageId, ?int $revisionId = null): ?array
    {
        $page = $this->db()->one('SELECT * FROM pages WHERE id = :id', ['id' => $pageId]);
        if (!is_array($page)) {
            return null;
        }
        $revisionId ??= (int) $page['current_revision_id'];
        $revision = $revisionId > 0 ? $this->revision($revisionId) : null;
        return [
            'page' => $page,
            'revision' => $revision,
            'settings' => $this->decodeJson($revision['settings'] ?? $page['settings']),
            'body_html' => $revision['body_html'] ?? '',
            'body_json' => $this->decodeJson($revision['body_json'] ?? null),
            'custom_css' => $revision['custom_css'] ?? $page['custom_css'],
            'custom_js' => $revision['custom_js'] ?? $page['custom_js'],
            'head_html' => $revision['head_html'] ?? $page['head_html'],
        ];
    }

    /**
     * Menu tree for a location (or global), built from menu_path values.
     *
     * @return array<int, array<string, mixed>>
     */
    public function menuTree(?int $locationId): array
    {
        $pages = $this->menuPages($locationId);
        $tree = [];
        foreach ($pages as $page) {
            $segments = array_values(array_filter(array_map('trim', explode('/', (string) $page['menu_path']))));
            $node = &$tree;
            foreach ($segments as $segment) {
                if (!isset($node[$segment])) {
                    $node[$segment] = ['_label' => $segment, '_children' => []];
                }
                $node = &$node[$segment]['_children'];
            }
            $node[] = $page;
            unset($node);
        }
        return $this->sortTree($tree);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function menuPages(?int $locationId): array
    {
        $sql = 'SELECT id, title, slug, location_id, menu_path, page_type, menu_order FROM pages
                WHERE is_published = 1 AND show_in_menu = 1';
        $params = [];
        if ($locationId !== null) {
            $sql .= ' AND (location_id = :lid OR location_id IS NULL)';
            $params['lid'] = $locationId;
        }
        $sql .= ' ORDER BY menu_order ASC, title ASC';
        return $this->db()->all($sql, $params);
    }

    /**
     * Finds a published page by slug within a location scope.
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug, ?int $locationId): ?array
    {
        $sql = 'SELECT * FROM pages WHERE slug = :slug AND is_published = 1';
        $params = ['slug' => $slug];
        if ($locationId !== null) {
            $sql .= ' AND (location_id = :lid OR location_id IS NULL)';
            $params['lid'] = $locationId;
        }
        return $this->db()->one($sql . ' ORDER BY location_id IS NULL DESC LIMIT 1', $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRevision(int $pageId, int $number, array $data, ?int $userId, string $summary): int
    {
        return $this->db()->insert('page_revisions', [
            'page_id' => $pageId,
            'revision_number' => $number,
            'title' => mb_substr((string) ($data['title'] ?? ''), 0, 255),
            'body_html' => $data['body_html'] ?? null,
            'body_json' => isset($data['body_json']) ? json_encode($data['body_json']) : null,
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
            'custom_css' => $data['custom_css'] ?? null,
            'custom_js' => $data['custom_js'] ?? null,
            'head_html' => $data['head_html'] ?? null,
            'change_summary' => mb_substr($summary, 0, 255),
            'created_by' => $userId,
        ]);
    }

    private function uniqueSlug(string $slug, ?int $locationId): string
    {
        $base = $slug !== '' ? $slug : 'page';
        $base = strtolower((string) preg_replace('/[^a-z0-9\-]+/i', '-', trim($base)));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'page';
        }
        $candidate = $base;
        $i = 2;
        while ($this->slugExists($candidate, $locationId)) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $locationId): bool
    {
        if ($locationId === null) {
            return $this->db()->one(
                'SELECT id FROM pages WHERE slug = :s AND location_id IS NULL',
                ['s' => $slug]
            ) !== null;
        }
        return $this->db()->one(
            'SELECT id FROM pages WHERE slug = :s AND location_id = :l',
            ['s' => $slug, 'l' => $locationId]
        ) !== null;
    }

    /**
     * @return mixed
     */
    private function decodeJson(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return $decoded === null && $json !== 'null' ? null : $decoded;
    }

    /**
     * @param array<string, mixed> $tree
     * @return array<int, array<string, mixed>>
     */
    private function sortTree(array $tree): array
    {
        $items = array_values($tree);
        usort($items, static function (array $a, array $b): int {
            $aPage = isset($a['id']);
            $bPage = isset($b['id']);
            if ($aPage !== $bPage) {
                return $aPage ? -1 : 1;
            }
            if ($aPage && $bPage) {
                return ((int) ($a['menu_order'] ?? 0)) <=> ((int) ($b['menu_order'] ?? 0))
                    ?: strcmp((string) $a['title'], (string) $b['title']);
            }
            return strcmp((string) ($a['_label'] ?? ''), (string) ($b['_label'] ?? ''));
        });
        foreach ($items as &$item) {
            if (isset($item['_children'])) {
                $item['_children'] = $this->sortTree($item['_children']);
            }
        }
        return $items;
    }
}
