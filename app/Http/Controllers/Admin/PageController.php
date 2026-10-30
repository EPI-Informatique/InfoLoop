<?php

/**
 * Admin page CRUD + revision management controller.
 *
 * Covers: list/create/edit pages, save revisions, view/restore/delete
 * revisions, inline media paste-upload endpoint used by the editor.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\MediaService;
use InfoLoop\Services\PageService;

final class PageController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        $locationId = $this->scopedLocationId($user);
        $pages = $this->app->service(\InfoLoop\Models\Page::class)->listWithMeta($locationId);
        return $this->view('admin/pages/index', ['pages' => $pages]);
    }

    public function createForm(Request $request, array $params): Response
    {
        return $this->view('admin/pages/form', [
            'page' => null,
            'locations' => $this->assignableLocations(),
            'types' => PageService::TYPES,
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('title')->string('title', 1, 255)
            ->string('slug', 0, 255)
            ->required('page_type')->in('page_type', PageService::TYPES)
            ->string('menu_path', 0, 512)
            ->int('location_id', 0, 0, PHP_INT_MAX)
            ->int('menu_order', 0, 0, 100000);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/pages/create');
        }
        $input = $v->sanitized();
        $locationId = (int) ($input['location_id'] ?? 0);
        if (!$this->canManageLocation($locationId !== 0 ? $locationId : null)) {
            return $this->abort(403, 'You cannot manage this location.');
        }
        $result = $this->app->service(PageService::class)->create([
            'title' => $input['title'],
            'slug' => $input['slug'] ?? '',
            'page_type' => $input['page_type'],
            'menu_path' => $input['menu_path'] ?? '',
            'location_id' => $locationId !== 0 ? $locationId : null,
            'is_published' => $request->bool('is_published', true),
            'show_in_menu' => $request->bool('show_in_menu', true),
            'menu_order' => (int) ($input['menu_order'] ?? 0),
            'settings' => $this->extractSettings($request),
            'body_html' => $request->string('body_html'),
            'body_json' => $this->extractBodyJson($request),
            'custom_css' => $request->string('custom_css'),
            'custom_js' => $request->string('custom_js'),
            'head_html' => $request->string('head_html'),
        ], $this->currentUserId());
        if (!$result['ok']) {
            $this->flash('error', $result['error'] ?? 'Could not create the page.');
            return $this->redirect('/admin/pages/create');
        }
        $this->flash('success', 'Page created.');
        return $this->redirect('/admin/pages/' . $result['page_id'] . '/edit');
    }

    public function editForm(Request $request, array $params): Response
    {
        $pageId = (int) ($params['id'] ?? 0);
        $pages = $this->app->service(PageService::class);
        $content = $pages->effectiveContent($pageId);
        if (!is_array($content)) {
            return $this->abort(404, 'Page not found.');
        }
        if (!$this->canManageLocation($content['page']['location_id'] !== null ? (int) $content['page']['location_id'] : null)) {
            return $this->abort(403, 'You cannot edit pages of this location.');
        }
        return $this->view('admin/pages/form', [
            'page' => $content['page'],
            'content' => $content,
            'revisions' => $pages->revisions($pageId),
            'locations' => $this->assignableLocations(),
            'types' => PageService::TYPES,
        ]);
    }

    public function update(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $pageId = (int) ($params['id'] ?? 0);
        $v = Validator::make($request->allInput())
            ->required('title')->string('title', 1, 255)
            ->required('page_type')->in('page_type', PageService::TYPES)
            ->string('menu_path', 0, 512);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/pages/' . $pageId . '/edit');
        }
        $input = $v->sanitized();
        $result = $this->app->service(PageService::class)->saveRevision($pageId, [
            'title' => $input['title'],
            'page_type' => $input['page_type'],
            'menu_path' => $input['menu_path'] ?? '',
            'is_published' => $request->bool('is_published', true),
            'show_in_menu' => $request->bool('show_in_menu', true),
            'menu_order' => $request->int('menu_order', 0, 0, 100000),
            'settings' => $this->extractSettings($request),
            'body_html' => $request->string('body_html'),
            'body_json' => $this->extractBodyJson($request),
            'custom_css' => $request->string('custom_css'),
            'custom_js' => $request->string('custom_js'),
            'head_html' => $request->string('head_html'),
        ], $this->currentUserId(), $request->string('change_summary', 'Updated'));
        if (!$result['ok']) {
            $this->flash('error', $result['error'] ?? 'Could not save.');
            return $this->redirect('/admin/pages/' . $pageId . '/edit');
        }
        $this->flash('success', 'New revision saved.');
        return $this->redirect('/admin/pages/' . $pageId . '/edit');
    }

    /**
     * Revision history page.
     */
    public function revisions(Request $request, array $params): Response
    {
        $pageId = (int) ($params['id'] ?? 0);
        $pages = $this->app->service(PageService::class);
        $content = $pages->effectiveContent($pageId);
        if (!is_array($content)) {
            return $this->abort(404, 'Page not found.');
        }
        return $this->view('admin/pages/revisions', [
            'page' => $content['page'],
            'revisions' => $pages->revisions($pageId),
        ]);
    }

    public function showRevision(Request $request, array $params): Response
    {
        $pageId = (int) ($params['id'] ?? 0);
        $revisionId = (int) ($params['revision'] ?? 0);
        $pages = $this->app->service(PageService::class);
        $revision = $pages->revision($revisionId);
        if (!is_array($revision) || (int) $revision['page_id'] !== $pageId) {
            return $this->abort(404, 'Revision not found.');
        }
        $page = $this->app->service(\InfoLoop\Models\Page::class)->find($pageId);
        return $this->view('admin/pages/revision_view', ['page' => $page, 'revision' => $revision]);
    }

    public function restoreRevision(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $pageId = (int) ($params['id'] ?? 0);
        $revisionId = (int) ($params['revision'] ?? 0);
        $result = $this->app->service(PageService::class)->restore($pageId, $revisionId, $this->currentUserId());
        $this->flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Revision restored.' : ($result['error'] ?? 'Restore failed.'));
        return $this->redirect('/admin/pages/' . $pageId . '/revisions');
    }

    public function deleteRevision(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $pageId = (int) ($params['id'] ?? 0);
        $revisionId = (int) ($params['revision'] ?? 0);
        $result = $this->app->service(PageService::class)->deleteRevision($pageId, $revisionId);
        return $this->json($result);
    }

    /**
     * Paste/direct upload endpoint used by the page editor (Ctrl+V images).
     */
    public function uploadInline(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->json(['error' => 'Invalid CSRF token.'], 403);
        }
        $file = $request->file('file');
        $raw = null;
        if ($file === null && $request->body() !== '') {
            $raw = $request->body();
        }
        $result = $this->app->service(MediaService::class)->store($file, $raw, $this->currentUserId());
        if (!$result['ok']) {
            return $this->json(['error' => $result['error'] ?? 'Upload failed.'], 422);
        }
        $media = $result['media'];
        $url = $this->app->service(MediaService::class)->publicUrl($media);
        return $this->json(['ok' => true, 'id' => (int) $media['id'], 'url' => $url, 'name' => $media['original_name']]);
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $pageId = (int) ($params['id'] ?? 0);
        $this->app->service(\InfoLoop\Models\Page::class)->deleteById($pageId); // cascades revisions
        $this->app->logger()->info('Page deleted', ['page_id' => $pageId]);
        $this->flash('success', 'Page deleted.');
        return $this->redirect('/admin/pages');
    }

    /**
     * Type-specific settings fields from the request.
     *
     * @return array<string, mixed>
     */
    private function extractSettings(Request $request): array
    {
        $pageType = $request->string('page_type', 'article');
        return match ($pageType) {
            'gallery' => [
                'items' => $request->arrayOfStrings('gallery_items'),
                'mode' => $request->string('gallery_mode', 'auto'),
                'interval_sec' => $request->int('gallery_interval', 5, 1, 3600),
            ],
            'pdf' => [
                'file_id' => $request->int('pdf_file_id', 0) ?: null,
                'start_page' => $request->int('pdf_start_page', 1, 1, 10000),
            ],
            'iframe' => ['url' => $request->string('iframe_url')],
            'video' => [
                'file_id' => $request->int('video_file_id', 0) ?: null,
                'loop' => $request->bool('video_loop', true),
                'autoplay' => $request->bool('video_autoplay', true),
                'muted' => $request->bool('video_muted', true),
            ],
            'youtube' => [
                'video_id' => $request->string('youtube_id'),
                'autoplay' => $request->bool('youtube_autoplay', false),
                'loop' => $request->bool('youtube_loop', false),
            ],
            'clock' => [
                'timezone' => $request->string('clock_timezone', 'Europe/Paris'),
                'format' => $request->string('clock_format', 'full'),
                'show_seconds' => $request->bool('clock_seconds', true),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractBodyJson(Request $request): ?array
    {
        $raw = $request->string('body_json');
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assignableLocations(): array
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        $auth = $this->app->service(AuthService::class);
        if ($user !== null && ($auth->isSuperAdmin($user) || ($user['role_name'] ?? '') === 'admin')) {
            return $this->app->service(\InfoLoop\Models\Location::class)->active();
        }
        if ($user !== null && (int) ($user['location_id'] ?? 0) > 0) {
            $location = $this->app->service(\InfoLoop\Models\Location::class)->find((int) $user['location_id']);
            return $location !== null ? [$location] : [];
        }
        return [];
    }

    private function scopedLocationId(?array $user): ?int
    {
        if ($user === null) {
            return null;
        }
        $auth = $this->app->service(AuthService::class);
        return $auth->isSuperAdmin($user) || ($user['role_name'] ?? '') === 'admin'
            ? null
            : ((int) ($user['location_id'] ?? 0) ?: null);
    }

    private function canManageLocation(?int $locationId): bool
    {
        return $this->app->service(AuthService::class)->canManageLocation(
            $this->app->service(AuthService::class)->currentUser(),
            $locationId
        );
    }

    private function currentUserId(): ?int
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        return $user !== null ? (int) $user['id'] : null;
    }
}
