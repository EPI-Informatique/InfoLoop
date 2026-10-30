<?php
/** Revision history. @var array $page @var array<int, array> $revisions @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Revisions — ' . $page['title'];
ob_start();
?>
<div class="card">
    <div class="card-head"><h2>Revisions of “<?= e($page['title']) ?>”</h2>
        <a class="btn btn-ghost" href="/admin/pages/<?= e((string) $page['id']) ?>/edit">← Back to editor</a></div>
    <table class="table">
        <thead><tr><th>#</th><th>Summary</th><th>Author</th><th>Date</th><th>Current</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($revisions as $r): ?>
            <tr>
                <td><?= e((string) $r['revision_number']) ?></td>
                <td><?= e((string) $r['change_summary']) ?></td>
                <td><?= e((string) ($r['author'] ?? '—')) ?></td>
                <td><?= e((string) $r['created_at']) ?></td>
                <td><?= (int) $r['id'] === (int) $page['current_revision_id'] ? '<span class="badge badge-ok">current</span>' : '' ?></td>
                <td class="row-actions">
                    <a class="btn btn-ghost" href="/admin/pages/<?= e((string) $page['id']) ?>/revisions/<?= e((string) $r['id']) ?>">view</a>
                    <?php if ((int) $r['id'] !== (int) $page['current_revision_id']): ?>
                        <form method="post" action="/admin/pages/<?= e((string) $page['id']) ?>/revisions/<?= e((string) $r['id']) ?>/restore" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>"><button class="btn btn-ghost" type="submit">restore</button>
                        </form>
                        <button class="btn btn-danger btn-sm" data-delete-revision="<?= e((string) $r['id']) ?>" data-page="<?= e((string) $page['id']) ?>">delete</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
