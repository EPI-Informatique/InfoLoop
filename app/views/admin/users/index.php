<?php
/** Users & roles. @var array<int, array> $users @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Users & roles';
ob_start();
?>
<div class="card">
    <div class="card-head"><h2>Admin users</h2>
        <form method="post" action="/admin/users/ldap-sync" class="inline-form">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button class="btn" type="submit">Sync LDAP now</button>
        </form>
    </div>
    <form method="post" action="/admin/users" class="form-grid card-inner">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Username <input name="username" required minlength="3"></label>
        <label>Email <input type="email" name="email" required></label>
        <label>Display name <input name="display_name"></label>
        <label>Role <input type="number" name="role_id" min="1" value="2" required></label>
        <label>Location ID (for location-scoped roles) <input type="number" name="location_id" min="0" value="0"></label>
        <label>Password (min. 12) <input type="password" name="password" required minlength="12"></label>
        <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="btn btn-primary" type="submit">Create user</button>
    </form>
    <table class="table">
        <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Location</th><th>Source</th><th>MFA</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e((string) $u['username']) ?><div class="muted"><?= e((string) $u['display_name']) ?></div></td>
                <td><?= e((string) $u['email']) ?></td>
                <td><span class="badge"><?= e((string) ($u['role_label'] ?? $u['role_name'])) ?></span></td>
                <td><?= e((string) ($u['location_name'] ?? '—')) ?></td>
                <td><?= e((string) $u['source']) ?></td>
                <td><?= (int) $u['mfa_enabled'] === 1 ? '✓' : '—' ?></td>
                <td><?= (int) $u['is_active'] === 1 ? '<span class="badge badge-ok">active</span>' : '<span class="badge badge-off">off</span>' ?></td>
                <td>
                    <form method="post" action="/admin/users/<?= e((string) $u['id']) ?>/delete" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>"><button class="btn btn-danger btn-sm">delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
