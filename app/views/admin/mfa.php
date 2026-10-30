<?php
/** MFA challenge. @var string $csrfToken @var array $flash */
use function InfoLoop\Core\e;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Two-factor authentication</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="login-body"><div class="card login-card">
    <h1>Two-factor authentication</h1>
    <p class="muted">Enter the 6-digit code from your authenticator app (or a recovery code).</p>
    <?php foreach ($flash as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form method="post" action="/admin/mfa">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Code <input name="code" inputmode="numeric" pattern="[0-9A-Za-z]{6,16}" required autocomplete="one-time-code" autofocus></label>
        <button class="btn btn-primary btn-block" type="submit">Verify</button>
    </form>
</div></body></html>
