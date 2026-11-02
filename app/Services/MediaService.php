<?php

/**
 * Media library service.
 *
 * Stores uploads in public/files using a sharded layout
 * (files/ab/cd/abcdef...ext) so no directory ever holds too many files.
 * Validates MIME by content (finfo), enforces an allow-list, de-duplicates
 * by sha256 and tracks usage (media_usage) for the "where is this file
 * used?" tooltips in the media manager.
 */

declare(strict_types=1);

namespace InfoLoop\Services;

use InfoLoop\Core\App;
use InfoLoop\Core\Database;
use InfoLoop\Core\Request;

final class MediaService
{
    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif'];
    private const VIDEO_MIMES = ['video/mp4', 'video/webm'];
    private const DOC_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.text',
        'text/plain',
    ];

    private ?Database $db = null;

    public function __construct(private readonly App $app)
    {
    }

    private function db(): Database
    {
        return $this->db ??= new Database($this->app->db());
    }

    /**
     * @return string[] Allowed MIME types (images, videos, documents).
     */
    public function allowedMimes(): array
    {
        $fromSettings = $this->app->service(SettingsService::class)->get('defaults');
        if (is_array($fromSettings) && isset($fromSettings['allowed_mime']) && is_array($fromSettings['allowed_mime'])) {
            return $fromSettings['allowed_mime'];
        }
        return [...self::IMAGE_MIMES, ...self::VIDEO_MIMES, ...self::DOC_MIMES];
    }

    /**
     * Stores an uploaded file (from a $_FILES entry or a raw payload).
     *
     * @param array{tmp_name?: string, name: string, error?: int, size?: int}|null $file Normalised $_FILES entry.
     * @param string|null $rawContent Raw bytes (used for paste-uploads).
     * @return array{ok: bool, media?: array<string, mixed>, error?: string}
     */
    public function store(?array $file, ?string $rawContent = null, ?int $userId = null): array
    {
        $maxBytes = (int) ($this->app->config('upload.max_mb') ?? $this->app->config('app.upload.max_mb', 200)) * 1024 * 1024;
        if ($file !== null) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return ['ok' => false, 'error' => 'Upload failed (code ' . (int) ($file['error'] ?? -1) . ').'];
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !file_exists($tmp)) {
                return ['ok' => false, 'error' => 'Missing uploaded file.'];
            }
            $size = (int) ($file['size'] ?? filesize($tmp));
            if ($size > $maxBytes) {
                return ['ok' => false, 'error' => 'File exceeds the maximum size of ' . ($this->app->config('upload.max_mb') ?? $this->app->config('app.upload.max_mb', 200)) . ' MB.'];
            }
            $content = (string) file_get_contents($tmp);
            $originalName = (string) ($file['name'] ?? 'upload.bin');
        } else {
            $content = (string) $rawContent;
            $originalName = 'paste-' . date('Ymd-His') . '.bin';
            if (strlen($content) > $maxBytes) {
                return ['ok' => false, 'error' => 'Payload too large.'];
            }
        }
        if ($content === '') {
            return ['ok' => false, 'error' => 'Empty file.'];
        }

        $mime = $this->detectMime($content, $originalName);
        if (!in_array($mime, $this->allowedMimes(), true)) {
            $this->app->logger()->warning('Rejected upload with disallowed MIME', ['mime' => $mime, 'name' => $originalName]);
            return ['ok' => false, 'error' => 'File type not allowed: ' . $mime];
        }
        if ($this->isSvg($mime) && $this->svgContainsScript($content)) {
            return ['ok' => false, 'error' => 'SVG files containing scripts are not allowed.'];
        }

        $sha = hash('sha256', $content);
        $existing = $this->db()->one('SELECT * FROM media_files WHERE sha256 = :s', ['s' => $sha]);
        if (is_array($existing)) {
            return ['ok' => true, 'media' => $existing, 'duplicate' => true];
        }

        $ext = $this->safeExtension($originalName, $mime);
        $storedName = $sha . $ext;
        $relative = $this->shardPath($sha, $ext);
        $absolute = $this->baseDir() . '/' . $relative;
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0750, true);
        }
        if (file_put_contents($absolute, $content, LOCK_EX) === false) {
            return ['ok' => false, 'error' => 'Could not write file to storage.'];
        }
        chmod($absolute, 0640);

        [$width, $height] = $this->imageDimensions($absolute, $mime);
        $id = $this->db()->insert('media_files', [
            'stored_name' => $storedName,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime_type' => $mime,
            'size_bytes' => strlen($content),
            'width' => $width,
            'height' => $height,
            'sha256' => $sha,
            'uploaded_by' => $userId,
        ]);
        $this->app->logger()->info('Media file stored', ['media_id' => $id, 'mime' => $mime, 'bytes' => strlen($content)]);
        return ['ok' => true, 'media' => $this->db()->one('SELECT * FROM media_files WHERE id = :id', ['id' => $id])];
    }

    /**
     * Records that a media file is referenced by a page/revision.
     */
    public function recordUsage(int $mediaId, ?int $pageId, ?int $revisionId, string $context = 'page'): void
    {
        $this->db()->insert('media_usage', [
            'media_id' => $mediaId,
            'page_id' => $pageId,
            'revision_id' => $revisionId,
            'context' => $context,
        ]);
        $this->db()->run(
            'UPDATE media_files SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id = :id',
            ['id' => $mediaId]
        );
    }

    /**
     * Finds every place a media file is used (current pages + revisions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function usages(int $mediaId): array
    {
        return $this->db()->all(
            'SELECT mu.*, p.title AS page_title, p.slug AS page_slug, p.location_id, r.revision_number
             FROM media_usage mu
             LEFT JOIN pages p ON p.id = mu.page_id
             LEFT JOIN page_revisions r ON r.id = mu.revision_id
             WHERE mu.media_id = :id
             ORDER BY mu.created_at DESC',
            ['id' => $mediaId]
        );
    }

    /**
     * Searches the library by file name or by page name (files linked from
     * pages whose title matches the query).
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $sort = 'uploaded_at', string $dir = 'DESC', int $limit = 100): array
    {
        $query = trim($query);
        $allowedSorts = [
            'uploaded_at' => 'uploaded_at',
            'last_used_at' => 'last_used_at',
            'size_bytes' => 'size_bytes',
            'original_name' => 'original_name',
        ];
        $column = $allowedSorts[$sort] ?? 'uploaded_at';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        if ($query === '') {
            return $this->db()->all("SELECT * FROM media_files ORDER BY {$column} {$direction} LIMIT " . $limit);
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';
        return $this->db()->all(
            "SELECT DISTINCT m.* FROM media_files m
             LEFT JOIN media_usage mu ON mu.media_id = m.id
             LEFT JOIN pages p ON p.id = mu.page_id
             WHERE m.original_name LIKE :q ESCAPE '\\' OR m.stored_name LIKE :q2 ESCAPE '\\'
                OR p.title LIKE :q3 ESCAPE '\\' OR p.slug LIKE :q4 ESCAPE '\\'
             ORDER BY {$column} {$direction} LIMIT {$limit}",
            ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
        );
    }

    /**
     * Deletes a media file (DB row + disk file) when it is unused or forced.
     */
    public function delete(int $mediaId, bool $force = false): array
    {
        $media = $this->db()->one('SELECT * FROM media_files WHERE id = :id', ['id' => $mediaId]);
        if (!is_array($media)) {
            return ['ok' => false, 'error' => 'File not found.'];
        }
        $usages = $this->usages($mediaId);
        if (!$force && $usages !== []) {
            return ['ok' => false, 'error' => 'File is still referenced by ' . count($usages) . ' page(s)/revision(s).'];
        }
        $absolute = $this->baseDir() . '/' . $this->shardPath((string) $media['sha256'], $this->extOf((string) $media['stored_name']));
        $this->db()->delete('media_files', 'id = :id', ['id' => $mediaId]); // cascades media_usage
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $this->app->logger()->info('Media file deleted', ['media_id' => $mediaId, 'forced' => $force]);
        return ['ok' => true];
    }

    public function publicUrl(array $media): string
    {
        $base = (string) ($this->app->config('files.base_url') ?? $this->app->config('app.files.base_url', '/files'));
        return $base . '/' . $this->shardPath((string) $media['sha256'], $this->extOf((string) $media['stored_name']));
    }

    public function absolutePath(array $media): string
    {
        return $this->baseDir() . '/' . $this->shardPath((string) $media['sha256'], $this->extOf((string) $media['stored_name']));
    }

    /**
     * Extracts media IDs referenced by an HTML/JSON payload (src/href
     * pointing to /files/...).
     *
     * @return int[]
     */
    public function extractMediaIds(string $payload): array
    {
        preg_match_all('#/files/([0-9a-f]{2})/([0-9a-f]{2})/([0-9a-f]{60})#', $payload, $matches);
        $ids = [];
        try {
            foreach ($matches[3] as $sha) {
                $row = $this->db()->one('SELECT id FROM media_files WHERE sha256 = :s', ['s' => $sha]);
                if (is_array($row)) {
                    $ids[] = (int) $row['id'];
                }
            }
        } catch (\PDOException) {
            return []; // DB unavailable (tests / pre-install)
        }
        return array_values(array_unique($ids));
    }

    private function baseDir(): string
    {
        $dir = (string) ($this->app->config('files.base_dir') ?? $this->app->config('app.files.base_dir', INFOLOOP_PUBLIC . '/files'));
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    private function shardPath(string $sha, string $ext): string
    {
        return substr($sha, 0, 2) . '/' . substr($sha, 2, 2) . '/' . $sha . $ext;
    }

    private function extOf(string $storedName): string
    {
        $pos = strrpos($storedName, '.');
        return $pos === false ? '' : substr($storedName, $pos);
    }

    private function detectMime(string $content, string $name): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->buffer($content);
        if ($mime === 'image/svg+xml' || ($mime === 'text/plain' && str_ends_with(strtolower($name), '.svg'))) {
            return 'image/svg+xml';
        }
        return $mime;
    }

    private function isSvg(string $mime): bool
    {
        return $mime === 'image/svg+xml';
    }

    private function svgContainsScript(string $content): bool
    {
        return (bool) preg_match('/<\s*script|<\s*\w+[^>]*\son\w+\s*=|javascript:/i', $content);
    }

    private function safeExtension(string $name, string $mime): string
    {
        $map = [
            'image/png' => '.png', 'image/jpeg' => '.jpg', 'image/svg+xml' => '.svg',
            'image/webp' => '.webp', 'image/gif' => '.gif', 'video/mp4' => '.mp4',
            'video/webm' => '.webm', 'application/pdf' => '.pdf', 'text/plain' => '.txt',
        ];
        if (isset($map[$mime])) {
            return $map[$mime];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.' . $ext : '.bin';
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function imageDimensions(string $path, string $mime): array
    {
        if (!in_array($mime, self::IMAGE_MIMES, true) || $mime === 'image/svg+xml') {
            return [null, null];
        }
        $size = @getimagesize($path);
        if (!is_array($size)) {
            return [null, null];
        }
        return [(int) $size[0], (int) $size[1]];
    }
}
