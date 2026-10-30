<?php
/**
 * Media library. Search bar, sortable columns, usage tooltips (loaded via
 * /admin/media/{id}/usages), upload form, delete.
 *
 * @var array<int, array<string, mixed>> $files
 * @var string $q @var string $sort @var string $dir @var string $csrfToken
 */
use function InfoLoop\Core\e;
$pageTitle = 'Media library';
$sortLink = static fn (string $col): string => '/admin/media?q=' . rawurlencode($q) . '&sort=' . $col . '&dir=' . ($sort === $col && $dir === 'DESC' ? 'ASC' : 'DESC');
$bytes = static fn (int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024, 1) . ' KB' : $b . ' B');
ob_start();
?>
<div class="card">
    <div class="card-head">
        <h2>Media library</h2>
        <form method="post" action="/admin/media/upload" enctype="multipart/form-data" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="file" name="files[]" multiple required>
            <button class="btn btn-primary" type="submit">Upload</button>
        </form>
    </div>
    <form method="get" class="search-bar">
        <input name="q" value="<?= e($q) ?>" placeholder="Search by file name or page name (e.g. HR)…">
        <button class="btn" type="submit">Search</button>
    </form>
    <table class="table">
        <thead><tr>
            <th><a href="<?= e($sortLink('original_name')) ?>">Name <?= $sort === 'original_name' ? e($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
            <th>Type</th>
            <th><a href="<?= e($sortLink('size_bytes')) ?>">Size <?= $sort === 'size_bytes' ? e($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
            <th><a href="<?= e($sortLink('uploaded_at')) ?>">Uploaded <?= $sort === 'uploaded_at' ? e($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
            <th><a href="<?= e($sortLink('last_used_at')) ?>">Last used <?= $sort === 'last_used_at' ? e($dir === 'DESC' ? '▼' : '▲') : '' ?></a></th>
            <th>Used in</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($files as $f): ?>
            <tr>
                <td><a href="/files/<?= e(substr((string) $f['sha256'], 0, 2) . '/' . substr((string) $f['sha256'], 2, 2) . '/' . (string) $f['stored_name']) ?>" target="_blank"><?= e((string) $f['original_name']) ?></a></td>
                <td class="muted"><?= e((string) $f['mime_type']) ?></td>
                <td><?= e($bytes((int) $f['size_bytes'])) ?></td>
                <td><?= e((string) $f['uploaded_at']) ?></td>
                <td><?= e((string) ($f['last_used_at'] ?? 'never')) ?></td>
                <td><button class="btn btn-ghost btn-sm usage-btn" data-media-id="<?= e((string) $f['id']) ?>">🔎 <?= e((string) $f['usage_count']) ?></button>
                    <div class="usage-tooltip" id="usage-<?= e((string) $f['id']) ?>" hidden></div></td>
                <td>
                    <form method="post" action="/admin/media/<?= e((string) $f['id']) ?>/delete" class="inline-form" onsubmit="return confirm('Delete this file?<?= (int) $f['usage_count'] > 0 ? ' It is still referenced.' : '' ?>');">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
