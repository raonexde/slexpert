<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$stats = [
    'new' => (int)db()->query("SELECT COUNT(*) FROM tour_requests WHERE status = 'new'")->fetchColumn(),
    'active' => (int)db()->query("SELECT COUNT(*) FROM tour_requests WHERE status IN ('new','contacted','quoted')")->fetchColumn(),
    'confirmed' => (int)db()->query("SELECT COUNT(*) FROM tour_requests WHERE status = 'confirmed'")->fetchColumn(),
    'bookings' => (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status <> 'cancelled'")->fetchColumn(),
    'agents' => (int)db()->query("SELECT COUNT(*) FROM b2b_agents WHERE active = 1")->fetchColumn(),
    'catalog' => (int)db()->query('SELECT (SELECT COUNT(*) FROM destinations WHERE active=1) + (SELECT COUNT(*) FROM catalog_items WHERE active=1)')->fetchColumn(),
];
$latest = db()->query('SELECT * FROM tour_requests ORDER BY created_at DESC LIMIT 6')->fetchAll();
$requestTypeLabels=['tour'=>'Rundreise','beach'=>'Strandurlaub','ayurveda'=>'Ayurveda','maldives'=>'Malediven'];
$adminPage = 'dashboard';
$adminTitle = 'Übersicht';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <div class="page-heading"><div><p><?= e(strtoupper((new DateTime())->format('l, d. F Y'))) ?></p><h1>Guten Tag, <?= e(explode(' ', admin_user()['name'])[0]) ?>.</h1><span>Hier sehen Sie, was heute wichtig ist.</span></div><a class="primary-button" href="<?= e(url('plan.php?mode=admin')) ?>">＋ Reise für Kunde erstellen</a></div>
    <section class="stat-grid">
        <article><i class="gold">◇</i><div><p>Neue Anfragen</p><strong><?= $stats['new'] ?></strong><small>noch nicht bearbeitet</small></div><a href="requests.php?status=new">→</a></article>
        <article><i class="green">✓</i><div><p>Aktive Planungen</p><strong><?= $stats['active'] ?></strong><small>in Bearbeitung</small></div><a href="requests.php">→</a></article>
        <article><i class="blue">▦</i><div><p>Buchungen</p><strong><?= $stats['bookings'] ?></strong><small>aktiv und abgeschlossen</small></div><a href="bookings.php">→</a></article>
        <article><i class="clay">◎</i><div><p>B2B-Agenturen</p><strong><?= $stats['agents'] ?></strong><small>aktive Partner</small></div><a href="agents.php">→</a></article>
    </section>
    <section class="admin-card">
        <div class="card-head"><div><h2>Neueste Reiseanfragen</h2><p>Direkt aus dem Kundenplaner und vom Admin-Team</p></div><a href="requests.php">Alle anzeigen →</a></div>
        <?php if (!$latest): ?><div class="empty"><span>◇</span><h3>Noch keine Anfragen</h3><p>Neue Reiseentwürfe erscheinen automatisch hier.</p></div>
        <?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kunde</th><th>Referenz</th><th>Reise</th><th>Wert</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($latest as $request): ?><tr><td><strong><?= e($request['customer_name']) ?></strong><small><?= e($request['customer_email']) ?></small></td><td><?= e($request['reference']) ?></td><td><?= e($requestTypeLabels[$request['request_type']]??'Rundreise') ?> · <?= (int)$request['travelers'] ?> Pers.<small><?= (int)$request['duration'] ?> Nächte<?= $request['hotel_name_de']?' · '.e($request['hotel_name_de']):'' ?></small></td><td><?= e(money($request['estimate'])) ?></td><td><span class="status <?= e($request['status']) ?>"><?= e($request['status']) ?></span></td><td><a href="request.php?id=<?= (int)$request['id'] ?>">Öffnen →</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
