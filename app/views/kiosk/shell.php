<?php
/**
 * Kiosk shell: boots the SPA-like controller (kiosk.js) which loads the
 * screen config from /api/screen/config and renders pages, playlist
 * rotation and inactivity timeout.
 *
 * @var array<string, mixed> $branding
 * @var string $screenToken
 */
use function InfoLoop\Core\e;
?>
<div id="kiosk-root"
     data-screen-token="<?= e($screenToken) ?>"
     data-brand="<?= e($branding['brand_name'] ?? 'InfoLoop') ?>">
    <div class="kiosk-boot">
        <div class="kiosk-logo"><?= e($branding['brand_name'] ?? 'InfoLoop') ?></div>
        <div class="kiosk-loading">Loading…</div>
    </div>
</div>
<noscript>
    <div class="kiosk-noscript">
        <h1><?= e($branding['brand_name'] ?? 'InfoLoop') ?></h1>
        <p>This display requires JavaScript. If you are configuring a screen, open
        <code>/?screen=YOUR_SCREEN_TOKEN</code> on the display device.</p>
    </div>
</noscript>
