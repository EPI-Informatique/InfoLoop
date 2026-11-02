<?php
/**
 * Page editor form. Handles all page types; type-specific settings are
 * shown/hidden by admin.js. Supports paste-upload of images directly in
 * the editor (Ctrl+V) via /admin/pages/upload-inline.
 *
 * @var array<string, mixed>|null $page
 * @var array<string, mixed>|null $content
 * @var array<int, array<string, mixed>> $revisions
 * @var array<int, array<string, mixed>> $locations
 * @var string[] $types
 * @var string $csrfToken
 */
use function InfoLoop\Core\e;
$pageTitle = $page !== null ? 'Edit page' : 'New page';
$settings = $content['settings'] ?? [];
ob_start();
?>
<div class="card">
    <div class="card-head">
        <h2><?= e($pageTitle) ?></h2>
        <?php if ($page !== null): ?>
            <div>
                <a class="btn btn-ghost" href="/admin/pages/<?= e((string) $page['id']) ?>/revisions">History (<?= count($revisions) ?>)</a>
                <a class="btn btn-ghost" href="/page/<?= e((string) $page['slug']) ?>" target="_blank">Preview ↗</a>
            </div>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= $page !== null ? '/admin/pages/' . (int) $page['id'] : '/admin/pages' ?>" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Title <input name="title" required maxlength="255" value="<?= e($page['title'] ?? '') ?>"></label>
        <label>Slug <input name="slug" maxlength="255" value="<?= e($page['slug'] ?? '') ?>" placeholder="auto-generated when empty"></label>
        <label>Page type
            <select name="page_type" id="page-type">
                <?php foreach ($types as $t): ?>
                    <option value="<?= e($t) ?>" <?= ($page['page_type'] ?? 'article') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Menu path (e.g. Company / HR / Policies) <input name="menu_path" maxlength="512" value="<?= e($page['menu_path'] ?? '') ?>"></label>
        <label>Location
            <select name="location_id">
                <option value="0">All locations (shared)</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= e((string) $loc['id']) ?>" <?= (int) ($page['location_id'] ?? 0) === (int) $loc['id'] ? 'selected' : '' ?>><?= e((string) $loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Menu order <input type="number" name="menu_order" min="0" value="<?= e((string) ($page['menu_order'] ?? 0)) ?>"></label>
        <label class="checkbox"><input type="checkbox" name="is_published" value="1" <?= ($page['is_published'] ?? 1) ? 'checked' : '' ?>> Published</label>
        <label class="checkbox"><input type="checkbox" name="show_in_menu" value="1" <?= ($page['show_in_menu'] ?? 1) ? 'checked' : '' ?>> Show in menu</label>

        <fieldset class="type-settings" data-type="gallery">
            <legend>Gallery</legend>
            <label>Image URLs (one per line, /files/... links from the media library)
                <textarea name="gallery_items_text" rows="4" placeholder="/files/ab/cd/...png"><?= e(implode("\n", is_array($settings['items'] ?? null) ? $settings['items'] : [])) ?></textarea>
            </label>
            <input type="hidden" name="gallery_items" id="gallery-items-hidden">
            <label>Switching mode
                <select name="gallery_mode">
                    <option value="auto" <?= ($settings['mode'] ?? 'auto') === 'auto' ? 'selected' : '' ?>>Automatic</option>
                    <option value="manual" <?= ($settings['mode'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual (tap)</option>
                </select>
            </label>
            <label>Interval (seconds) <input type="number" name="gallery_interval" min="1" max="3600" value="<?= e((string) ($settings['interval_sec'] ?? 5)) ?>"></label>
        </fieldset>

        <fieldset class="type-settings" data-type="pdf">
            <legend>PDF</legend>
            <label>PDF file ID <input type="number" name="pdf_file_id" min="1" value="<?= e((string) ($settings['file_id'] ?? '')) ?>"></label>
            <label>Start page <input type="number" name="pdf_start_page" min="1" value="<?= e((string) ($settings['start_page'] ?? 1)) ?>"></label>
        </fieldset>

        <fieldset class="type-settings" data-type="iframe">
            <legend>Web page (iframe)</legend>
            <label>URL <input name="iframe_url" value="<?= e($settings['url'] ?? '') ?>" placeholder="https://intranet.example.com"></label>
        </fieldset>

        <fieldset class="type-settings" data-type="video">
            <legend>MP4 video</legend>
            <label>Video file ID <input type="number" name="video_file_id" min="1" value="<?= e((string) ($settings['file_id'] ?? '')) ?>"></label>
            <label class="checkbox"><input type="checkbox" name="video_loop" value="1" <?= ($settings['loop'] ?? true) ? 'checked' : '' ?>> Loop</label>
            <label class="checkbox"><input type="checkbox" name="video_autoplay" value="1" <?= ($settings['autoplay'] ?? true) ? 'checked' : '' ?>> Autoplay</label>
            <label class="checkbox"><input type="checkbox" name="video_muted" value="1" <?= ($settings['muted'] ?? true) ? 'checked' : '' ?>> Muted</label>
        </fieldset>

        <fieldset class="type-settings" data-type="youtube">
            <legend>YouTube</legend>
            <label>YouTube video ID <input name="youtube_id" value="<?= e($settings['video_id'] ?? '') ?>" placeholder="dQw4w9WgXcQ"></label>
            <label class="checkbox"><input type="checkbox" name="youtube_autoplay" value="1" <?= ($settings['autoplay'] ?? false) ? 'checked' : '' ?>> Autoplay</label>
            <label class="checkbox"><input type="checkbox" name="youtube_loop" value="1" <?= ($settings['loop'] ?? false) ? 'checked' : '' ?>> Loop</label>
        </fieldset>

        <fieldset class="type-settings" data-type="clock">
            <legend>Clock</legend>
            <label>Timezone <input name="clock_timezone" value="<?= e($settings['timezone'] ?? 'Europe/Paris') ?>" placeholder="Europe/Paris"></label>
            <label class="checkbox"><input type="checkbox" name="clock_seconds" value="1" <?= ($settings['show_seconds'] ?? true) ? 'checked' : '' ?>> Show seconds</label>
        </fieldset>

        <label class="full">Content — WYSIWYG editor (images can be pasted with Ctrl+V)
            <textarea name="body_html" id="body-editor" rows="14"><?= e($content['body_html'] ?? '') ?></textarea>
        </label>
        <?php $tinymceVer = is_file(INFOLOOP_PUBLIC . '/assets/vendor/tinymce/VERSION') ? trim((string) file_get_contents(INFOLOOP_PUBLIC . '/assets/vendor/tinymce/VERSION')) : '8.10.1'; ?>
        <script src="/assets/vendor/tinymce/tinymce.min.js?v=<?= e($tinymceVer) ?>"></script>
        <label class="full">Structured content (JSON, for tables etc.)
            <textarea name="body_json" rows="4"><?= e(is_array($content['body_json'] ?? null) ? json_encode($content['body_json'], JSON_PRETTY_PRINT) : '') ?></textarea>
        </label>
        <label class="full">Custom CSS <textarea name="custom_css" rows="4"><?= e($content['custom_css'] ?? '') ?></textarea></label>
        <label class="full">Custom JS (body) <textarea name="custom_js" rows="4"><?= e($content['custom_js'] ?? '') ?></textarea></label>
        <label class="full">Custom HTML for &lt;head&gt; <textarea name="head_html" rows="3"><?= e($content['head_html'] ?? '') ?></textarea></label>
        <?php if ($page !== null): ?>
            <label class="full">Change summary <input name="change_summary" maxlength="255" placeholder="What changed?"></label>
        <?php endif; ?>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= $page !== null ? 'Save new revision' : 'Create page' ?></button>
            <a class="btn btn-ghost" href="/admin/pages">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
