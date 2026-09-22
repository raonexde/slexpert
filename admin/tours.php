<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('tours');

$category = trim((string)($_GET['category'] ?? ''));
$allowedCategories = ['winter','summer','sport','culture','discovery','backpacker','ayurveda','family','luxury'];
if (!in_array($category, $allowedCategories, true)) $category = '';

$sql = "SELECT t.*,
        COUNT(DISTINCT s.id) AS stop_count,
        COUNT(DISTINCT p.id) AS price_count
    FROM tour_templates t
    LEFT JOIN tour_template_stops s ON s.tour_template_id=t.id
    LEFT JOIN tour_template_prices p ON p.tour_template_id=t.id AND p.active=1";
$params = [];
if ($category !== '') {
    $sql .= ' WHERE t.category=?';
    $params[] = $category;
}
$sql .= ' GROUP BY t.id ORDER BY t.active DESC,t.sort_order,t.title_de';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll();

$counts = db()->query('SELECT category,COUNT(*) total FROM tour_templates GROUP BY category')->fetchAll(PDO::FETCH_KEY_PAIR);
$totalTours = (int)array_sum(array_map('intval', $counts));
$categoryLabels = [
    'winter'=>'Winterurlaub','summer'=>'Sommerurlaub','sport'=>'Sport & Abenteuer','culture'=>'Kultur',
    'discovery'=>'Kennenlernen','backpacker'=>'Backpacker','ayurveda'=>'Ayurveda plus','family'=>'Familie','luxury'=>'Luxus',
];

$adminPage = 'tours';
$adminTitle = 'Fertige Reisen';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact">
        <div><p>REISEANGEBOT</p><h1>Fertige & anpassbare Reisen</h1><span>Vorlagen veröffentlichen, duplizieren und mit Hotels sowie Erlebnissen ausstatten.</span></div>
        <a class="primary-button" href="tour-edit.php">＋ Neue Reisevorlage</a>
    </div>
    <div class="filter-tabs">
        <a class="<?= $category === '' ? 'active' : '' ?>" href="tours.php">Alle <b><?= $totalTours ?></b></a>
        <?php foreach ($categoryLabels as $key=>$label): if (empty($counts[$key])) continue; ?>
            <a class="<?= $category === $key ? 'active' : '' ?>" href="tours.php?category=<?= e($key) ?>"><?= e($label) ?> <b><?= (int)$counts[$key] ?></b></a>
        <?php endforeach; ?>
    </div>
    <section class="admin-card">
        <div class="card-head"><div><h2>Reisevorlagen</h2><p><?= count($tours) ?> Einträge in dieser Ansicht</p></div></div>
        <?php if (!$tours): ?>
            <div class="empty"><span>◈</span><h3>Noch keine Reisen</h3><p>Erstellen Sie die erste anpassbare Reisevorlage.</p></div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table tour-admin-table"><thead><tr><th>Reise</th><th>Route</th><th>Richtpreis</th><th>Sichtbarkeit</th><th>Aktionen</th></tr></thead><tbody>
            <?php foreach ($tours as $tour): ?>
                <tr>
                    <td><strong><?= e($tour['title_de']) ?></strong><small><?= e($tour['title_en']) ?> · <?= e($categoryLabels[$tour['category']] ?? $tour['category']) ?></small></td>
                    <td><strong><?= (int)$tour['duration_nights'] ?> Nächte · <?= (int)$tour['stop_count'] ?> Stopps</strong><small><?= e($tour['season_de']) ?></small></td>
                    <td><strong>ab <?= e(money($tour['price_from'])) ?></strong><small><?= (int)$tour['price_count'] ?> Preisstaffeln</small></td>
                    <td><span class="status <?= (int)$tour['active'] === 1 ? 'confirmed' : 'archived' ?>"><?= (int)$tour['active'] === 1 ? 'Veröffentlicht' : 'Entwurf' ?></span><?= (int)$tour['featured'] === 1 ? '<small>Auf Startseite</small>' : '' ?></td>
                    <td><div class="tour-row-actions"><a href="tour-edit.php?id=<?= (int)$tour['id'] ?>">Bearbeiten</a><a href="<?= e(url('tour.php?slug=' . rawurlencode($tour['slug']))) ?>" target="_blank">Vorschau ↗</a><a href="tour-edit.php?duplicate=<?= (int)$tour['id'] ?>">Duplizieren</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
