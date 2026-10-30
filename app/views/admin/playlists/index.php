<?php
/** Playlists list. @var array<int, array> $playlists @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Playlists';
ob_start();
?>
<div class="card">
    <div class="card-head"><h2>Playlists (auto-rotation for non-interactive screens)</h2>
        <form method="post" action="/admin/playlists" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input name="name" required placeholder="Lobby loop">
            <button class="btn btn-primary" type="submit">+ New playlist</button>
        </form>
    </div>
    <table class="table">
        <thead><tr><th>Name</th><th>Description</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($playlists as $p): ?>
            <tr>
                <td><a href="/admin/playlists/<?= e((string) $p['id']) ?>/edit"><?= e((string) $p['name']) ?></a></td>
                <td class="muted"><?= e((string) ($p['description'] ?? '')) ?></td>
                <td>
                    <form method="post" action="/admin/playlists/<?= e((string) $p['id']) ?>/delete" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>"><button class="btn btn-danger btn-sm">delete</button>
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
