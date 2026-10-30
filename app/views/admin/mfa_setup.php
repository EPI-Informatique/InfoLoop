<?php
/** MFA enrolment. @var string $csrfToken @var string $secret @var string $uri */
use function InfoLoop\Core\e;
$qr = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($uri);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set up MFA</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="login-body"><div class="card login-card">
    <h1>Set up two-factor authentication</h1>
    <p class="muted">Scan this QR code with your authenticator app, then enter the 6-digit code.</p>
    <p class="qr-wrap"><img src="<?= e($qr) ?>" alt="QR code" width="200" height="200"></p>
    <p class="muted">Manual code: <code><?= e(chunk_split($secret, 4, ' ')) ?></code></p>
    <?php foreach (($flash ?? []) as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($m) ?></div>
    <?php endforeach; endforeach; ?>
    <form method="post" action="/admin/mfa/setup">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Verification code <input name="code" inputmode="numeric" pattern="[0-9]{6}" required autocomplete="one-time-code"></label>
        <button class="btn btn-primary btn-block" type="submit">Enable MFA</button>
    </form>
</div></body></html>
