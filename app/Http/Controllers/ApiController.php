<?php

/**
 * Public JSON API consumed by the kiosk shell.
 *
 * - GET /api/screen/config?token=...   full kiosk payload (auth by token)
 * - GET /api/page/{slug}?token=...      single page payload + menu
 * - GET /api/media-file/{id}?token=...  serve a media file to a screen
 * - POST /api/stats/event               engagement beacon (view/interaction)
 * - GET /api/health                     liveness probe
 *
 * All responses are JSON; errors use {"error": "..."} with proper codes.
 * Database outages surface as 503 JSON instead of HTML error pages.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\PageService;
use InfoLoop\Services\ScreenService;
use InfoLoop\Services\StatsService;

final class ApiController extends Controller
{
    public function health(Request $request, array $params): Response
    {
        return $this->json(['status' => 'ok', 'time' => date('c')]);
    }

    /**
     * Full kiosk configuration for a registered screen.
     */
    public function screenConfig(Request $request, array $params): Response
    {
        try {
            $screen = $this->screenFromRequest($request);
            if (!is_array($screen)) {
                return $this->json(['error' => 'Unknown or disabled screen.'], 401);
            }
            $screens = $this->app->service(ScreenService::class);
            return $this->json($screens->kioskConfig($screen));
        } catch (\PDOException $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * Single page payload for interactive navigation.
     */
    public function page(Request $request, array $params): Response
    {
        try {
            $screen = $this->screenFromRequest($request);
            if (!is_array($screen)) {
                return $this->json(['error' => 'Unknown or disabled screen.'], 401);
            }
            $slug = (string) ($params['slug'] ?? '');
            $pages = $this->app->service(PageService::class);
            $page = $pages->findPublishedBySlug($slug, (int) $screen['location_id']);
            if (!is_array($page)) {
                return $this->json(['error' => 'Page not found.'], 404);
            }
            $content = $pages->effectiveContent((int) $page['id']);
            $screens = $this->app->service(ScreenService::class);
            return $this->json([
                'page' => $content !== null ? $screens->publicPagePayload($content) : null,
                'menu' => $pages->menuTree((int) $screen['location_id']),
                'timeouts' => [
                    'enabled' => (bool) ((int) $screen['timeout_enabled'] === 1),
                    'inactivity_sec' => (int) $screen['inactivity_timeout_sec'],
                ],
            ]);
        } catch (\PDOException $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * Serves a media file by ID to an authenticated screen (pdf/video pages).
     */
    public function mediaFile(Request $request, array $params): Response
    {
        try {
            $screen = $this->screenFromRequest($request);
            if (!is_array($screen)) {
                return $this->json(['error' => 'Unknown screen.'], 401);
            }
            $mediaId = (int) ($params['id'] ?? 0);
            $media = $this->app->service(\InfoLoop\Models\MediaFile::class)->find($mediaId);
            if (!is_array($media)) {
                return $this->json(['error' => 'Not found.'], 404);
            }
            $service = $this->app->service(\InfoLoop\Services\MediaService::class);
            $absolute = $service->absolutePath($media);
            if (!is_file($absolute)) {
                return $this->json(['error' => 'Not found.'], 404);
            }
            return Response::file($absolute, (string) $media['original_name'], (string) $media['mime_type'])
                ->withHeader('Cache-Control', 'public, max-age=86400, immutable');
        } catch (\PDOException $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * Engagement beacon. Accepts JSON {token, page_id, event}.
     */
    public function statsEvent(Request $request, array $params): Response
    {
        try {
            $body = $request->json();
            $screen = $this->screenFromRequest($request, (string) ($body['token'] ?? ''));
            if (!is_array($screen)) {
                return $this->json(['error' => 'Unknown screen.'], 401);
            }
            $pageId = (int) ($body['page_id'] ?? 0);
            if ($pageId <= 0) {
                return $this->json(['error' => 'page_id required.'], 422);
            }
            $event = (string) ($body['event'] ?? 'view');
            $this->app->service(StatsService::class)->record(
                $pageId,
                (int) $screen['id'],
                (int) $screen['location_id'],
                (string) ($body['token'] ?? ''),
                $event
            );
            return $this->json(['ok' => true]);
        } catch (\PDOException $e) {
            return $this->unavailable($e);
        }
    }

    /**
     * Resolves the screen from the token (query param or JSON body).
     *
     * @return array<string, mixed>|null
     */
    private function screenFromRequest(Request $request, string $bodyToken = ''): ?array
    {
        $token = (string) $request->query('token', $bodyToken);
        return $this->app->service(ScreenService::class)->byToken($token);
    }

    private function unavailable(\PDOException $e): Response
    {
        $this->app->logger()->error('Database unavailable in API', ['error' => $e->getMessage()]);
        return $this->json(['error' => 'Service temporarily unavailable.'], 503);
    }
}
