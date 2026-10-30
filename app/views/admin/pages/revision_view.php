<?php
/** Read-only revision view. @var array $page @var array $revision */
use function InfoLoop\Core\{e, raw};
$pageTitle = 'Revision #' . $revision['revision_number'];
ob_start();
?>
<div class="card">
    <div class="card-head"><h2><?= e($page['title']) ?> — revision #<?= e((string) $revision['revision_number']) ?></h2>
        <a class="btn btn-ghost" href="/admin/pages/<?= e((string) $page['id']) ?>/revisions">← History</a></div>
    <p class="muted"><?= e((string) $revision['change_summary']) ?> · <?= e((string) $revision['created_at']) ?></p>
    <article class="page-body preview"><?= raw((string) $revision['body_html']) ?></article>
</div>
<?php if (!empty($revision['custom_css'])): ?><style><?= raw((string) $revision['custom_css']) ?></style><?php endif; ?>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
