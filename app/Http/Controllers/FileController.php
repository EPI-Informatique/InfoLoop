<?php

/**
 * Serves uploaded files when nginx is not configured to serve public/files
 * directly (fallback mode). In production nginx should serve /files/
 * statically; this controller enforces MIME allow-listing and prevents
 * path traversal.
 */

declare(strict_types=1);

namespace InfoLoop\Http\Controllers;

use InfoLoop\Core\Controller;
use InfoLoop\Core\Request;
use InfoLoop\Core\Response;
use InfoLoop\Services\MediaService;

final class FileController extends Controller
{
    public function serve(Request $request, array $params): Response
    {
        $path = (string) ($params['path'] ?? '');
        if (!preg_match('#^([0-9a-f]{2})/([0-9a-f]{2})/([0-9a-f]{60})(\.[a-z0-9]{1,10})$#', $path, $m)) {
            return new Response(404, 'Not found', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $sha = $m[3];
        $media = $this->app->service(\InfoLoop\Models\MediaFile::class)->findBy('sha256', $sha);
        if (!is_array($media)) {
            return new Response(404, 'Not found', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $service = $this->app->service(MediaService::class);
        $absolute = $service->absolutePath($media);
        if (!is_file($absolute)) {
            return new Response(404, 'Not found', ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        return Response::file($absolute, (string) $media['original_name'], (string) $media['mime_type'])
            ->withHeader('Cache-Control', 'public, max-age=86400, immutable');
    }
}
