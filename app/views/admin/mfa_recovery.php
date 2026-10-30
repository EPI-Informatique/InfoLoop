<?php
/** Recovery codes shown once. @var string[] $codes */
use function InfoLoop\Core\e;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recovery codes</title><link rel="stylesheet" href="/assets/admin.css"></head>
<body class="login-body"><div class="card login-card">
    <h1>Save your recovery codes</h1>
    <p class="muted">Each code can be used once if you lose your authenticator. Store them somewhere safe — they will not be shown again.</p>
    <ul class="recovery-codes">
        <?php foreach ($codes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?>
    </ul>
    <a class="btn btn-primary btn-block" href="/admin/account">Done</a>
</div></body></html>
