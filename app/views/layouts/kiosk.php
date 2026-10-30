<?php
/**
 * Kiosk layout: full-viewport shell for touch screens. Branding CSS
 * variables are injected; page content is rendered inside #stage.
 *
 * @var string $content
 * @var array<string, mixed> $branding
 * @var string $cssVariables
 */
use function InfoLoop\Core\{e, raw};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <title><?= e($branding['brand_name'] ?? 'InfoLoop') ?></title>
    <link rel="stylesheet" href="/assets/kiosk.css">
    <style><?= raw($cssVariables) ?></style>
    <?php if (!empty($headHtml)): ?><?= raw((string) $headHtml) ?><?php endif; ?>
</head>
<body class="kiosk-body">
    <?= raw((string) $content) ?>
    <script src="/assets/kiosk.js"></script>
</body>
</html>
