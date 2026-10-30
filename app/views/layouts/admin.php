<?php
/**
 * Admin layout: sidebar navigation, topbar with brand + user menu,
 * flash messages, branding CSS variables.
 *
 * @var array<string, mixed> $content Rendered page content (string, escaped below)
 * @var array<string, mixed> $settings
 * @var array<string, mixed>|null $currentUser
 * @var array<string, string[]> $flash
 * @var string $csrfToken
 * @var \InfoLoop\Core\App $app
 */
use function InfoLoop\Core\{e, raw};
$branding = $settings['branding'] ?? ['brand_name' => 'InfoLoop'];
$nav = [
    ['/admin', 'Dashboard', 'grid'],
    ['/admin/pages', 'Pages', 'doc'],
    ['/admin/media', 'Media library', 'image'],
    ['/admin/playlists', 'Playlists', 'list'],
    ['/admin/screens', 'Screens', 'monitor'],
    ['/admin/locations', 'Locations', 'pin'],
    ['/admin/users', 'Users', 'users'],
    ['/admin/settings', 'Settings', 'gear'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($branding['brand_name']) ?> — Admin</title>
    <link rel="stylesheet" href="/assets/admin.css">
    <style><?= raw($app->service(\InfoLoop\Services\SettingsService::class)->cssVariables()) ?></style>
</head>
<body class="admin-body">
<aside class="sidebar">
    <div class="brand">
        <?php if (!empty($branding['logo_url'])): ?>
            <img src="<?= e($branding['logo_url']) ?>" alt="" class="brand-logo">
        <?php endif; ?>
        <span class="brand-name"><?= e($branding['brand_name']) ?></span>
    </div>
    <nav class="nav">
        <?php foreach ($nav as [$href, $label, $icon]): ?>
            <a href="<?= e($href) ?>" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', $href) && $href !== '/admin' || ($href === '/admin' && ($_SERVER['REQUEST_URI'] ?? '') === '/admin') ? 'active' : '' ?>">
                <span class="nav-icon"><?= e($icon) ?></span><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/" target="_blank" class="nav-link">View kiosk ↗</a>
    </div>
</aside>
<main class="main">
    <header class="topbar">
        <div class="topbar-title"><?= e($pageTitle ?? 'Admin') ?></div>
        <div class="topbar-user">
            <?php if ($currentUser): ?>
                <a href="/admin/account" class="user-chip">
                    <span class="user-avatar"><?= e(strtoupper(mb_substr((string) $currentUser['display_name'], 0, 1))) ?></span>
                    <span><?= e($currentUser['display_name']) ?> · <?= e($currentUser['role_label'] ?? '') ?></span>
                </a>
                <form method="post" action="/admin/logout" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button class="btn btn-ghost" type="submit">Sign out</button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= e($type) ?>"><?= e($message) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <section class="content">
        <?= raw((string) $content) ?>
    </section>
</main>
<script src="/assets/admin.js"></script>
</body>
</html>
