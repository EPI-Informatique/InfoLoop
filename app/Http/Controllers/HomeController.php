<?php

/**
 * Public (kiosk) home controller.
 *
 * Serves the touch-screen shell. The shell authenticates with a screen
 * token (?screen=TOKEN or stored in localStorage) and pulls its config
 * from /api/screen/config.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\PageService;
use InfoLoop\Services\ScreenService;
use InfoLoop\Services\SettingsService;

final class HomeController extends Controller
{
    /**
     * Kiosk shell page.
     */
    public function index(Request $request, array $params): Response
    {
        $settings = $this->app->service(SettingsService::class);
        $branding = $settings->branding();
        return $this->view('kiosk/shell', [
            'branding' => $branding,
            'cssVariables' => $settings->cssVariables(),
            'screenToken' => preg_match('/^[a-f0-9]{64}$/', (string) $request->query('screen', '')) ? $request->query('screen') : '',
        ], 200);
    }

    /**
     * Renders a single published page directly (mobile-friendly view,
     * also used when JS is disabled on a kiosk).
     */
    public function page(Request $request, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $locationSlug = (string) $request->query('location', '');
        $locationId = null;
        if ($locationSlug !== '') {
            $location = $this->app->service(\InfoLoop\Models\Location::class)->findBySlug($locationSlug);
            $locationId = is_array($location) ? (int) $location['id'] : null;
        }
        $pages = $this->app->service(PageService::class);
        try {
            $page = $pages->findPublishedBySlug($slug, $locationId);
        } catch (\PDOException) {
            return $this->abort(404, 'Page not found.');
        }
        if (!is_array($page)) {
            return $this->abort(404, 'Page not found.');
        }
        $content = $pages->effectiveContent((int) $page['id']);
        $settings = $this->app->service(SettingsService::class);
        return $this->view('kiosk/page', [
            'content' => $content,
            'branding' => $settings->branding(),
            'cssVariables' => $settings->cssVariables(),
            'menu' => $pages->menuTree($locationId),
            'locationId' => $locationId,
        ]);
    }
}
