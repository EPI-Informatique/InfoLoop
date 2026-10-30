<?php
/**
 * Admin dashboard: headline KPIs, chart (daily series), top pages,
 * per-location table, screens status.
 *
 * @var array<string, int> $headline
 * @var array<int, array<string, mixed>> $topPages
 * @var array<int, array<string, mixed>> $byLocation
 * @var array<int, array<string, mixed>> $series
 * @var array<int, array<string, mixed>> $screens
 * @var int $days
 */
use function InfoLoop\Core\e;
$pageTitle = 'Dashboard';
ob_start();
?>
<div class="kpi-grid">
    <?php foreach (['views' => 'Views', 'interactions' => 'Interactions', 'pages' => 'Pages', 'screens' => 'Screens', 'online_screens' => 'Screens online', 'media' => 'Media files'] as $key => $label): ?>
        <div class="kpi"><div class="kpi-value"><?= e((string) ($headline[$key] ?? 0)) ?></div><div class="kpi-label"><?= e($label) ?> (<?= e((string) $days) ?>d)</div></div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Engagement — last <?= e((string) $days) ?> days</h2>
        <canvas id="views-chart" height="120"></canvas>
    </div>
    <div class="card">
        <h2>Top pages</h2>
        <table class="table">
            <thead><tr><th>Page</th><th>Type</th><th>Views</th><th>Interactions</th></tr></thead>
            <tbody>
            <?php foreach ($topPages as $p): ?>
                <tr>
                    <td><a href="/admin/pages/<?= e((string) $p['id']) ?>/edit"><?= e((string) $p['title']) ?></a></td>
                    <td><?= e((string) $p['page_type']) ?></td>
                    <td><?= e((string) $p['views']) ?></td>
                    <td><?= e((string) $p['interactions']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2>By location</h2>
        <table class="table">
            <thead><tr><th>Location</th><th>Views</th><th>Pages</th><th>Screens</th></tr></thead>
            <tbody>
            <?php foreach ($byLocation as $l): ?>
                <tr><td><?= e((string) ($l['name'] ?? '—')) ?></td><td><?= e((string) $l['views']) ?></td><td><?= e((string) $l['distinct_pages']) ?></td><td><?= e((string) $l['active_screens']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h2>Screens (7 days)</h2>
        <table class="table">
            <thead><tr><th>Screen</th><th>Location</th><th>Views</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($screens as $s): ?>
                <tr>
                    <td><?= e((string) $s['name']) ?></td>
                    <td><?= e((string) ($s['location_name'] ?? '—')) ?></td>
                    <td><?= e((string) $s['views_7d']) ?></td>
                    <td><?= (int) $s['is_active'] === 1 ? '<span class="badge badge-ok">active</span>' : '<span class="badge badge-off">off</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    window.IL_CHART = <?= json_encode($series) ?>;
</script>
<?php
$content = ob_get_clean();
echo $this->render('layouts/admin', ['content' => $content, 'pageTitle' => $pageTitle]);
