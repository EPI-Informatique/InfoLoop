<?php

/**
 * Screen (kiosk) management: register screens, assign playlists/default
 * pages, set inactivity timeout, rotate tokens.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Models\Screen;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\ScreenService;

final class ScreenController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        $auth = $this->app->service(AuthService::class);
        $screens = ($user !== null && ($auth->isSuperAdmin($user) || ($user['role_name'] ?? '') === 'admin'))
            ? $this->app->service(Screen::class)->allWithLocations()
            : $this->app->service(Screen::class)->forLocation((int) ($user['location_id'] ?? 0));
        return $this->view('admin/screens/index', ['screens' => $screens]);
    }

    public function store(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())
            ->required('name')->string('name', 1, 150)
            ->required('location_id')->int('location_id', 1)
            ->in('orientation', ['landscape', 'portrait'])
            ->int('inactivity_timeout_sec', 30, 0, 86400);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/screens');
        }
        $input = $v->sanitized();
        $token = bin2hex(random_bytes(32));
        $this->app->service(Screen::class)->create([
            'location_id' => (int) $input['location_id'],
            'name' => $input['name'],
            'token' => $token,
            'resolution' => $request->string('resolution') ?: null,
            'orientation' => $input['orientation'],
            'playlist_id' => $request->int('playlist_id', 0) ?: null,
            'default_page_id' => $request->int('default_page_id', 0) ?: null,
            'inactivity_timeout_sec' => (int) $input['inactivity_timeout_sec'],
            'timeout_enabled' => $request->bool('timeout_enabled', true) ? 1 : 0,
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ]);
        $this->flash('success', 'Screen registered. Open /?screen=' . $token . ' on the display.');
        return $this->redirect('/admin/screens');
    }

    public function update(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $v = Validator::make($request->allInput())
            ->required('name')->string('name', 1, 150)
            ->in('orientation', ['landscape', 'portrait'])
            ->int('inactivity_timeout_sec', 30, 0, 86400);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/screens');
        }
        $input = $v->sanitized();
        $this->app->service(Screen::class)->updateById($id, [
            'name' => $input['name'],
            'resolution' => $request->string('resolution') ?: null,
            'orientation' => $input['orientation'],
            'playlist_id' => $request->int('playlist_id', 0) ?: null,
            'default_page_id' => $request->int('default_page_id', 0) ?: null,
            'inactivity_timeout_sec' => (int) $input['inactivity_timeout_sec'],
            'timeout_enabled' => $request->bool('timeout_enabled', true) ? 1 : 0,
            'is_active' => $request->bool('is_active', true) ? 1 : 0,
        ]);
        $this->flash('success', 'Screen updated.');
        return $this->redirect('/admin/screens');
    }

    public function rotateToken(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $token = $this->app->service(ScreenService::class)->regenerateToken($id);
        $this->flash('success', 'New screen URL: /?screen=' . $token);
        return $this->redirect('/admin/screens');
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $this->app->service(Screen::class)->deleteById((int) ($params['id'] ?? 0));
        $this->flash('success', 'Screen deleted.');
        return $this->redirect('/admin/screens');
    }

    /**
     * Data for the screen form (locations, playlists, pages).
     */
    public function formData(Request $request, array $params): Response
    {
        $user = $this->app->service(AuthService::class)->currentUser();
        return $this->json([
            'locations' => $this->app->service(\InfoLoop\Models\Location::class)->active(),
            'playlists' => $this->app->service(\InfoLoop\Models\Playlist::class)->all('name', 'ASC'),
            'pages' => $this->app->service(\InfoLoop\Models\Page::class)->published(),
        ]);
    }
}
