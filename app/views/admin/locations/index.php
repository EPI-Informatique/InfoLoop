<?php
/** Locations. @var array<int, array> $locations @var string $csrfToken */
use function InfoLoop\Core\e;
$pageTitle = 'Locations';
ob_start();
?>
<div class="grid-2">
<div class="card">
    <h2>New location</h2>
    <form method="post" action="/admin/locations" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Name <input name="name" required maxlength="150"></label>
        <label>Timezone <input name="timezone" value="Europe/Paris" required></label>
        <label>Description <input name="description"></label>
        <label class="checkbox"><input type="checkbox" name="is_active" value="1" checked> Active</label>
        <button class="btn btn-primary" type="submit">Create</button>
    </form>
</div>
<div class="card">
    <h2>Locations</h2>
    <table class="table">
        <thead><tr><th>Name</th><th>Slug</th><th>Timezone</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($locations as $l): ?>
            <tr>
                <td><?= e((string) $l['name']) ?></td>
                <td class="muted"><?= e((string) $l['slug']) ?></td>
                <td><?= e((string) $l['timezone']) ?></td>
                <td>
                    <form method="post" action="/admin/locations/<?= e((string) $l['id']) ?>/delete" class="inline-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
