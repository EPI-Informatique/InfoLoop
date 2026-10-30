<?php
/** Account page. @var array $user @var bool $mfaEnabled @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'My account';
ob_start();
?>
<div class="card">
    <h2><?= e($user['display_name']) ?></h2>
    <p class="muted">@<?= e($user['username']) ?> · <?= e($user['email']) ?> · role: <?= e($user['role_label'] ?? '') ?></p>
    <h3>Two-factor authentication (MFA)</h3>
    <p><?= $mfaEnabled ? '<span class="badge badge-ok">enabled</span>' : '<span class="badge badge-off">disabled</span>' ?></p>
    <?php if ($mfaEnabled): ?>
        <form method="post" action="/admin/mfa/disable">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button class="btn btn-danger" type="submit">Disable MFA</button>
        </form>
    <?php else: ?>
        <a class="btn btn-primary" href="/admin/mfa/setup">Set up MFA</a>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
