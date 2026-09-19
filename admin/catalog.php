<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$destinations = db()->query(
    "SELECT d.*,
      SUM(CASE WHEN c.type='accommodation' AND c.active=1 THEN 1 ELSE 0 END) AS accommodations,
      SUM(CASE WHEN c.type='sight' AND c.active=1 THEN 1 ELSE 0 END) AS sights,
      SUM(CASE WHEN c.type='activity' AND c.active=1 THEN 1 ELSE 0 END) AS activities,
      SUM(CASE WHEN c.type='shop' AND c.active=1 THEN 1 ELSE 0 END) AS shops,
      SUM(CASE WHEN c.type='service' AND c.active=1 THEN 1 ELSE 0 END) AS services
     FROM destinations d LEFT JOIN catalog_items c ON c.destination_id=d.id
     GROUP BY d.id ORDER BY d.sort_order,d.name_de"
)->fetchAll();
$items = db()->query('SELECT c.*,d.name_de AS destination_name,d.country_code FROM catalog_items c JOIN destinations d ON d.id=c.destination_id ORDER BY d.country_code,d.sort_order,c.type,c.sort_order')->fetchAll();
$type = in_array($_GET['type'] ?? '', ['accommodation','sight','activity','shop','service'], true) ? $_GET['type'] : '';
$basisLabels=['per_person'=>'pro Person','per_person_night'=>'pro Person / Nacht','per_booking'=>'pro Buchung'];

$adminPage = 'catalog';
$adminTitle = 'Reisebausteine';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact"><div><p>DEUTSCH & ENGLISCH</p><h1>Reisebausteine</h1><span>Reiseziele, Unterkünfte, Sehenswürdigkeiten, Aktivitäten, Shops und Zusatzleistungen verwalten.</span></div><div class="heading-actions"><a class="secondary-button" href="destination-edit.php">＋ Neuer Ort / Reiseziel</a><a class="primary-button" href="item-edit.php">＋ Neuer Baustein</a></div></div>
    <h2 class="section-title">Reiseziele</h2>
    <div class="catalog-grid">
        <?php foreach ($destinations as $destination): ?><article class="catalog-card">
            <div class="catalog-code accent-<?= e($destination['accent']) ?>"<?= $destination['image_path'] ? ' style="background-image:linear-gradient(rgba(10,40,32,.2),rgba(10,40,32,.6)),url(' . e(url($destination['image_path'])) . ')"' : '' ?>><strong><?= e($destination['code']) ?></strong><small><?= $destination['active'] ? '● Aktiv' : '○ Inaktiv' ?></small></div>
            <div><p><?= e($destination['country_code']==='MV'?'MALEDIVEN · '.$destination['region_de']:'SRI LANKA · '.$destination['region_de']) ?></p><h3><?= e($destination['name_de']) ?></h3><small><?= e($destination['name_en']) ?></small><ul><li><?= (int)$destination['accommodations'] ?> Unterkünfte</li><li><?= (int)$destination['sights'] ?> Sehenswertes</li><li><?= (int)$destination['activities'] ?> Aktivitäten</li><li><?= (int)$destination['shops'] ?> Shops</li><li><?= (int)$destination['services'] ?> Zusatzleistungen</li></ul><a href="destination-edit.php?id=<?= (int)$destination['id'] ?>">Bearbeiten →</a></div>
        </article><?php endforeach; ?>
    </div>
    <div class="section-row"><h2 class="section-title">Alle Angebote</h2><nav class="mini-tabs"><a class="<?= $type===''?'active':'' ?>" href="catalog.php">Alle</a><a class="<?= $type==='accommodation'?'active':'' ?>" href="?type=accommodation">Unterkünfte</a><a class="<?= $type==='sight'?'active':'' ?>" href="?type=sight">Sehenswertes</a><a class="<?= $type==='activity'?'active':'' ?>" href="?type=activity">Aktivitäten</a><a class="<?= $type==='shop'?'active':'' ?>" href="?type=shop">Shops</a><a class="<?= $type==='service'?'active':'' ?>" href="?type=service">Zusatzleistungen</a></nav></div>
    <section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Reiseziel</th><th>Typ</th><th>Preis</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($items as $item): if ($type && $item['type'] !== $type) continue; ?><tr><td><strong><?= e($item['name_de']) ?></strong><small><?= e($item['name_en']) ?><?= $item['beach_available']?' · Strand':'' ?><?= $item['ayurveda_available']?' · Ayurveda':'' ?><?= $item['maldives_available']?' · Malediven':'' ?></small></td><td><?= e($item['country_code']==='MV'?'Malediven · ':'Sri Lanka · ') ?><?= e($item['destination_name']) ?></td><td><?= e($item['type']) ?></td><td><?= e(money($item['price_per_person'])) ?><small><?= $item['type']==='accommodation'?'pro Doppelzimmer / Nacht':e($basisLabels[$item['price_basis']]??'pro Person') ?></small></td><td><span class="status <?= $item['active'] ? 'confirmed' : 'archived' ?>"><?= $item['active'] ? 'Aktiv' : 'Inaktiv' ?></span></td><td><a href="item-edit.php?id=<?= (int)$item['id'] ?>">Bearbeiten →</a></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
