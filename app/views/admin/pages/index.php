<?php
/** Pages list. @var array<int, array<string, mixed>> $pages */
use function InfoLoop\Core\e;
$pageTitle = 'Pages';
ob_start();
?>
<div class="card">
    <div class="card-head">
        <h2>Pages</h2>
        <a class="btn btn-primary" href="/admin/pages/create">+ New page</a>
    </div>
    <table class="table">
        <thead><tr><th>Title</th><th>Menu path</th><th>Type</th><th>Location</th><th>Revisions</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <tr>
                <td><a href="/admin/pages/<?= e((string) $p['id']) ?>/edit"><?= e((string) $p['title']) ?></a></td>
                <td class="muted"><?= e((string) $p['menu_path']) ?></td>
                <td><span class="badge"><?= e((string) $p['page_type']) ?></span></td>
                <td><?= e((string) ($p['location_name'] ?? 'All locations')) ?></td>
                <td><a href="/admin/pages/<?= e((string) $p['id']) ?>/revisions"><?= e((string) $p['revision_count']) ?></a></td>
                <td><?= (int) $p['is_published'] === 1 ? '<span class="badge badge-ok">published</span>' : '<span class="badge badge-off">draft</span>' ?></td>
                <td><a class="btn btn-ghost" href="/page/<?= e((string) $p['slug']) ?>" target="_blank">view ↗</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
