<?php

/**
 * Media library controller.
 *
 * List/search/sort files, upload (single or bulk), inspect where a file
 * is used (current pages + revisions tooltips), delete with usage guard.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers\Admin;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Csrf;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\AuthService;
use InfoLoop\Services\MediaService;

final class MediaController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $media = $this->app->service(MediaService::class);
        $query = $request->string('q');
        $sort = $request->string('sort', 'uploaded_at');
        $dir = $request->string('dir', 'DESC');
        $files = $media->search($query, $sort, $dir, 200);
        return $this->view('admin/media/index', [
            'files' => $files,
            'q' => $query,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * JSON list for the editor's insert-media picker.
     */
    public function listJson(Request $request, array $params): Response
    {
        $media = $this->app->service(MediaService::class);
        $files = $media->search($request->string('q'), $request->string('sort', 'uploaded_at'), $request->string('dir', 'DESC'), 100);
        return $this->json(['files' => array_map(static fn (array $f): array => [
            'id' => (int) $f['id'],
            'name' => $f['original_name'],
            'mime' => $f['mime_type'],
            'size' => (int) $f['size_bytes'],
            'url' => $media->publicUrl($f),
            'uploaded_at' => $f['uploaded_at'],
        ], $files)]);
    }

    public function upload(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->json(['error' => 'Invalid CSRF token.'], 403);
        }
        $user = $this->app->service(AuthService::class)->currentUser();
        $media = $this->app->service(MediaService::class);
        $results = [];
        $files = $request->files();
        $uploads = $files['files'] ?? null;
        if (is_array($uploads) && isset($uploads['tmp_name']) && is_array($uploads['tmp_name'])) {
            foreach (array_keys($uploads['tmp_name']) as $i) {
                $entry = [
                    'tmp_name' => $uploads['tmp_name'][$i] ?? '',
                    'name' => $uploads['name'][$i] ?? 'file',
                    'error' => $uploads['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $uploads['size'][$i] ?? 0,
                ];
                $results[] = $media->store($entry, null, $user !== null ? (int) $user['id'] : null);
            }
        } else {
            foreach ($files as $entry) {
                if (is_array($entry)) {
                    $results[] = $media->store($entry, null, $user !== null ? (int) $user['id'] : null);
                }
            }
        }
        $ok = array_values(array_filter($results, static fn (array $r): bool => $r['ok']));
        if ($ok === []) {
            return $this->json(['error' => $results[0]['error'] ?? 'Upload failed.'], 422);
        }
        if ($request->wantsJson()) {
            return $this->json(['ok' => true, 'files' => array_map(static fn (array $r): array => [
                'id' => (int) $r['media']['id'],
                'url' => $media->publicUrl($r['media']),
                'name' => $r['media']['original_name'],
            ], $ok)]);
        }
        $this->flash('success', count($ok) . ' file(s) uploaded.');
        return $this->redirect('/admin/media');
    }

    /**
     * Usage details for the tooltip: pages and revisions referencing a file.
     */
    public function usages(Request $request, array $params): Response
    {
        $mediaId = (int) ($params['id'] ?? 0);
        $usages = $this->app->service(MediaService::class)->usages($mediaId);
        return $this->json(['usages' => $usages]);
    }

    public function delete(Request $request, array $params): Response
    {
        if (!$this->app->service(Csrf::class)->verifyRequest($request)) {
            return $this->abort(403, 'Invalid CSRF token.');
        }
        $mediaId = (int) ($params['id'] ?? 0);
        $result = $this->app->service(MediaService::class)->delete($mediaId, $request->bool('force', false));
        if (!$result['ok']) {
            $this->flash('error', $result['error'] ?? 'Delete failed.');
            return $this->redirect('/admin/media');
        }
        $this->flash('success', 'File deleted.');
        return $this->redirect('/admin/media');
    }
}
