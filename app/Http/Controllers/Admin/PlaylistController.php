<?php

/**
 * Playlist management: ordered page rotation with per-item durations,
 * assigned to non-interactive screens.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Database;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Core\Validator;
use InfoLoop\Models\Playlist;
use InfoLoop\Services\AuthService;

final class PlaylistController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        return $this->view('admin/playlists/index', [
            'playlists' => $this->app->service(Playlist::class)->all('name', 'ASC'),
        ]);
    }

    public function store(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $v = Validator::make($request->allInput())->required('name')->string('name', 1, 150);
        if (!$v->valid()) {
            $this->flash('error', $v->firstError());
            return $this->redirect('/admin/playlists');
        }
        $input = $v->sanitized();
        $user = $this->app->service(AuthService::class)->currentUser();
        $this->app->service(Playlist::class)->create([
            'name' => $input['name'],
            'description' => $request->string('description'),
            'created_by' => $user !== null ? (int) $user['id'] : null,
        ]);
        $this->flash('success', 'Playlist created.');
        return $this->redirect('/admin/playlists');
    }

    /**
     * Editor view: reorder items, set durations.
     */
    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $playlist = $this->app->service(Playlist::class)->find($id);
        if (!is_array($playlist)) {
            return $this->abort(404, 'Playlist not found.');
        }
        return $this->view('admin/playlists/edit', [
            'playlist' => $playlist,
            'items' => $this->app->service(Playlist::class)->withItems($id),
            'pages' => $this->app->service(\InfoLoop\Models\Page::class)->published(),
        ]);
    }

    public function addItem(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $id = (int) ($params['id'] ?? 0);
        $pageId = $request->int('page_id', 0, 1);
        $duration = $request->int('duration_sec', 15, 1, 86400);
        if ($pageId <= 0) {
            return $this->json(['error' => 'page_id required.'], 422);
        }
        $db = new Database($this->app->db());
        $position = (int) ($db->scalar('SELECT COALESCE(MAX(position),0)+1 FROM playlist_items WHERE playlist_id = :p', ['p' => $id]));
        $db->insert('playlist_items', [
            'playlist_id' => $id,
            'page_id' => $pageId,
            'position' => $position,
            'duration_sec' => $duration,
        ]);
        return $this->redirect('/admin/playlists/' . $id . '/edit');
    }

    public function updateItem(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $itemId = (int) ($params['item'] ?? 0);
        $db = new Database($this->app->db());
        $db->update('playlist_items', [
            'duration_sec' => $request->int('duration_sec', 15, 1, 86400),
            'position' => $request->int('position', 1, 1, 10000),
        ], 'id = :id', ['id' => $itemId]);
        return $this->json(['ok' => true]);
    }

    public function removeItem(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $itemId = (int) ($params['item'] ?? 0);
        $db = new Database($this->app->db());
        $db->delete('playlist_items', 'id = :id', ['id' => $itemId]);
        return $this->json(['ok' => true]);
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $this->app->service(Playlist::class)->deleteById((int) ($params['id'] ?? 0));
        $this->flash('success', 'Playlist deleted.');
        return $this->redirect('/admin/playlists');
    }
}
