<?php
/**
 * No-JS / mobile fallback rendering of a single published page.
 *
 * @var array<string, mixed>|null $content  Effective page content
 * @var array<string, mixed> $branding
 * @var string $cssVariables
 * @var array<int, array<string, mixed>> $menu
 */
use function InfoLoop\Core\{e, raw};
$page = $content['page'] ?? [];
$settings = $content['settings'] ?? [];
?>
<header class="kiosk-header">
    <div class="kiosk-brand"><?= e($branding['brand_name'] ?? 'InfoLoop') ?></div>
    <nav class="kiosk-menu">
        <?php foreach ($menu as $item): if (isset($item['id'])): ?>
            <a href="/page/<?= e($item['slug']) ?><?= isset($locationId) && $locationId ? '?location=' . e((string) $locationId) : '' ?>" class="<?= ($page['slug'] ?? '') === $item['slug'] ? 'active' : '' ?>"><?= e($item['title']) ?></a>
        <?php endif; endforeach; ?>
    </nav>
</header>
<main class="kiosk-stage">
    <h1><?= e($page['title'] ?? '') ?></h1>
    <?php
    $type = $page['page_type'] ?? 'article';
    if ($type === 'gallery' && is_array($settings['items'] ?? null)):
        foreach ($settings['items'] as $url): ?>
            <img src="<?= e($url) ?>" alt="" class="gallery-img">
        <?php endforeach;
    elseif ($type === 'pdf' && !empty($settings['file_id'])): ?>
        <iframe src="/files-fallback/" class="pdf-frame" title="PDF"></iframe>
    <?php elseif ($type === 'video' && !empty($settings['file_id'])): ?>
        <video controls loop muted autoplay playsinline class="video-el"></video>
    <?php elseif ($type === 'youtube' && !empty($settings['video_id'])): ?>
        <iframe class="video-el" src="https://www.youtube-nocookie.com/embed/<?= e($settings['video_id']) ?>" allowfullscreen></iframe>
    <?php elseif ($type === 'iframe' && !empty($settings['url'])): ?>
        <iframe src="<?= e($settings['url']) ?>" class="iframe-el"></iframe>
    <?php elseif ($type === 'clock'): ?>
        <div class="clock" data-timezone="<?= e($settings['timezone'] ?? 'UTC') ?>"></div>
    <?php endif; ?>
    <article class="page-body"><?= raw((string) ($content['body_html'] ?? '')) ?></article>
</main>
<?php if (!empty($content['custom_css'])): ?><style><?= raw((string) $content['custom_css']) ?></style><?php endif; ?>
<?php if (!empty($content['custom_js'])): ?><script><?= raw((string) $content['custom_js']) ?></script><?php endif; ?>
