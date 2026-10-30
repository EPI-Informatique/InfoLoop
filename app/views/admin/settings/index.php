<?php
/**
 * Settings: branding, defaults, LDAP.
 * @var array $branding @var array $defaults @var array $ldap @var string $csrfToken
 */
use function InfoLoop\Core\e;
$pageTitle = 'Settings';
ob_start();
?>
<div class="grid-2">
<div class="card">
    <h2>Branding</h2>
    <form method="post" action="/admin/settings/branding" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Brand name <input name="brand_name" required value="<?= e($branding['brand_name'] ?? '') ?>"></label>
        <label>Slogan <input name="slogan" value="<?= e($branding['slogan'] ?? '') ?>"></label>
        <label>Logo (png/svg/webp) <input type="file" name="logo" accept="image/*"></label>
        <?php if (!empty($branding['logo_url'])): ?>
            <p><img src="<?= e($branding['logo_url']) ?>" alt="logo" style="max-height:48px"> <label class="checkbox"><input type="checkbox" name="remove_logo" value="1"> remove</label></p>
        <?php endif; ?>
        <label>Primary color <input name="primary_color" value="<?= e($branding['primary_color'] ?? '#2563eb') ?>" pattern="^#[0-9a-fA-F]{6}$"></label>
        <label>Secondary color <input name="secondary_color" value="<?= e($branding['secondary_color'] ?? '#0f172a') ?>" pattern="^#[0-9a-fA-F]{6}$"></label>
        <label>Accent color <input name="accent_color" value="<?= e($branding['accent_color'] ?? '#f59e0b') ?>" pattern="^#[0-9a-fA-F]{6}$"></label>
        <label>Background <input name="background_color" value="<?= e($branding['background_color'] ?? '#ffffff') ?>" pattern="^#[0-9a-fA-F]{6}$"></label>
        <label>Text color <input name="text_color" value="<?= e($branding['text_color'] ?? '#0f172a') ?>" pattern="^#[0-9a-fA-F]{6}$"></label>
        <label>Font family <input name="font_family" value="<?= e($branding['font_family'] ?? '') ?>"></label>
        <button class="btn btn-primary" type="submit">Save branding</button>
    </form>
</div>
<div class="card">
    <h2>Defaults</h2>
    <form method="post" action="/admin/settings/defaults" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="checkbox"><input type="checkbox" name="timeout_enabled" value="1" <?= ($defaults['timeout_enabled'] ?? true) ? 'checked' : '' ?>> Enable inactivity timeout by default</label>
        <label>Inactivity timeout (sec) <input type="number" name="inactivity_timeout_sec" min="0" value="<?= e((string) ($defaults['inactivity_timeout_sec'] ?? 30)) ?>"></label>
        <label>Default playlist duration (sec) <input type="number" name="default_playlist_duration_sec" min="1" value="<?= e((string) ($defaults['default_playlist_duration_sec'] ?? 15)) ?>"></label>
        <button class="btn btn-primary" type="submit">Save defaults</button>
    </form>
</div>
</div>
<div class="card">
    <h2>LDAP / Active Directory</h2>
    <form method="post" action="/admin/settings/ldap" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label class="checkbox"><input type="checkbox" name="enabled" value="1" <?= ($ldap['enabled'] ?? false) ? 'checked' : '' ?>> Enable LDAP login</label>
        <label>Host <input name="host" value="<?= e($ldap['host'] ?? '') ?>"></label>
        <label>Port <input type="number" name="port" value="<?= e((string) ($ldap['port'] ?? 389)) ?>"></label>
        <label>Base DN <input name="base_dn" value="<?= e($ldap['base_dn'] ?? '') ?>" placeholder="dc=example,dc=com"></label>
        <label>Bind DN <input name="bind_dn" value="<?= e($ldap['bind_dn'] ?? '') ?>"></label>
        <label>Bind password <input type="password" name="bind_password" value=""></label>
        <label>User filter <input name="user_filter" value="<?= e($ldap['user_filter'] ?? '(uid={username})') ?>"></label>
        <label>Group filter <input name="group_filter" value="<?= e($ldap['group_filter'] ?? '(member={dn})') ?>"></label>
        <label>Allowed group (optional) <input name="allowed_group" value="<?= e($ldap['allowed_group'] ?? '') ?>"></label>
        <label>Attr username <input name="attr_username" value="<?= e($ldap['attr_username'] ?? 'uid') ?>"></label>
        <label>Attr email <input name="attr_email" value="<?= e($ldap['attr_email'] ?? 'mail') ?>"></label>
        <label>Attr display name <input name="attr_display_name" value="<?= e($ldap['attr_display_name'] ?? 'displayName') ?>"></label>
        <label>Default role for synced users <input name="default_role" value="<?= e($ldap['default_role'] ?? 'viewer') ?>"></label>
        <button class="btn btn-primary" type="submit">Save LDAP settings</button>
    </form>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
