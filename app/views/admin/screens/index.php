<?php
/** Screens. @var array<int, array> $screens @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Screens';
ob_start();
?>
<div class="card">
    <h2>Register a screen</h2>
    <form method="post" action="/admin/screens" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Name <input name="name" required maxlength="150" placeholder="Reception TV"></label>
        <label>Location <input type="number" name="location_id" min="1" required></label>
        <label>Orientation
            <select name="orientation"><option value="landscape">landscape</option><option value="portrait">portrait</option></select>
        </label>
        <label>Inactivity timeout (sec) <input type="number" name="inactivity_timeout_sec" min="0" value="30"></label>
        <label class="checkbox"><input type="checkbox" name="timeout_enabled" value="1" checked> Enable inactivity timeout</label>
        <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="btn btn-primary" type="submit">Register</button>
    </form>
</div>
<div class="card">
    <h2>Screens</h2>
    <table class="table">
        <thead><tr><th>Name</th><th>Location</th><th>Orientation</th><th>Timeout</th><th>Last seen</th><th>Token URL</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($screens as $s): ?>
            <tr>
                <td><?= e((string) $s['name']) ?></td>
                <td><?= e((string) ($s['location_name'] ?? '—')) ?></td>
                <td><?= e((string) $s['orientation']) ?></td>
                <td><?= (int) $s['timeout_enabled'] === 1 ? e((string) $s['inactivity_timeout_sec']) . 's' : 'off' ?></td>
                <td><?= e((string) ($s['last_seen_at'] ?? 'never')) ?></td>
                <td><code>/?screen=<?= e(substr((string) $s['token'], 0, 12)) ?>…</code></td>
                <td class="row-actions">
                    <form method="post" action="/admin/screens/<?= e((string) $s['id']) ?>/rotate-token" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>"><button class="btn btn-ghost btn-sm">rotate token</button>
                    </form>
                    <form method="post" action="/admin/screens/<?= e((string) $s['id']) ?>/delete" class="inline-form">
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
