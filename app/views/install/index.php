<?php
/** Installer view. @var string $csrfToken @var array $settings */
use function InfoLoop\Core\e;
$brand = $settings['branding']['brand_name'] ?? 'InfoLoop';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install <?= e($brand) ?></title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="install-body">
<div class="card install-card">
    <h1><?= e($brand) ?> — Installation</h1>
    <p class="muted">Create the database schema and the initial super-admin account.
    The password can be reset later from the CLI: <code>php bin/infoloop user:reset-password &lt;username&gt;</code></p>
    <?php foreach (($flash ?? []) as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form method="post" action="/install">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Admin username <input name="admin_username" required minlength="3" value="admin"></label>
        <label>Admin email <input type="email" name="admin_email" required value="admin@example.com"></label>
        <label>Admin password (min. 12 chars) <input type="password" name="admin_password" required minlength="12"></label>
        <label>Confirm password <input type="password" name="admin_password_confirm" required minlength="12"></label>
        <button class="btn btn-primary" type="submit">Install InfoLoop</button>
    </form>
</div>
</body>
</html>
