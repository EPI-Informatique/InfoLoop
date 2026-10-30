<?php
/** Admin login. @var string $csrfToken @var string $redirect @var array $branding @var array $flash */
use function InfoLoop\Core\{e, raw};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — <?= e($branding['brand_name'] ?? 'InfoLoop') ?></title>
    <link rel="stylesheet" href="/assets/admin.css">
    <style><?= raw($app->service(\InfoLoop\Services\SettingsService::class)->cssVariables()) ?></style>
</head>
<body class="login-body">
<div class="card login-card">
    <h1><?= e($branding['brand_name'] ?? 'InfoLoop') ?></h1>
    <?php if (!empty($branding['slogan'])): ?><p class="muted"><?= e($branding['slogan']) ?></p><?php endif; ?>
    <?php foreach ($flash as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form method="post" action="/admin/login">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <label>Username <input name="username" required autocomplete="username"></label>
        <label>Password <input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
</div>
</body>
</html>
