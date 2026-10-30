<?php
/** Playlist editor. @var array $playlist @var array<int, array> $items @var array<int, array> $pages @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Playlist — ' . $playlist['name'];
ob_start();
?>
<div class="card">
    <div class="card-head"><h2>“<?= e($playlist['name']) ?>”</h2>
        <form method="post" action="/admin/playlists/<?= e((string) $playlist['id']) ?>/items" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <select name="page_id">
                <?php foreach ($pages as $p): ?><option value="<?= e((string) $p['id']) ?>"><?= e((string) $p['title']) ?></option><?php endforeach; ?>
            </select>
            <input type="number" name="duration_sec" value="15" min="1" max="86400" title="Duration (sec)">
            <button class="btn btn-primary" type="submit">Add page</button>
        </form>
    </div>
    <table class="table" id="playlist-items">
        <thead><tr><th>#</th><th>Page</th><th>Duration (s)</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr data-item="<?= e((string) $i['id']) ?>">
                <td><?= e((string) $i['position']) ?></td>
                <td><?= e((string) $i['title']) ?> <span class="muted">(<?= e((string) $i['page_type']) ?>)</span></td>
                <td><input type="number" class="dur" value="<?= e((string) $i['duration_sec']) ?>" min="1" max="86400"></td>
                <td><button class="btn btn-danger btn-sm remove-item" type="button">remove</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><a class="btn btn-ghost" href="/admin/playlists">← Back</a></p>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
