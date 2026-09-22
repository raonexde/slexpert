<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('requests');

$status = in_array($_GET['status'] ?? '', ['new','contacted','quoted','confirmed','cancelled','archived'], true) ? $_GET['status'] : '';
$query = trim((string)($_GET['q'] ?? ''));
$sql = 'SELECT r.*, a.company_name AS agent_company_name, b.id AS booking_id, b.booking_reference, (SELECT COUNT(*) FROM tour_request_destinations rd WHERE rd.request_id=r.id) AS place_count FROM tour_requests r LEFT JOIN b2b_agents a ON a.id=r.b2b_agent_id LEFT JOIN bookings b ON b.request_id=r.id WHERE 1=1';
$params = [];
if ($status !== '') { $sql .= ' AND r.status = ?'; $params[] = $status; }
if ($query !== '') { $sql .= ' AND (r.customer_name LIKE ? OR r.customer_email LIKE ? OR r.reference LIKE ?)'; $search = '%' . $query . '%'; array_push($params, $search, $search, $search); }
$sql .= ' ORDER BY r.created_at DESC LIMIT 250';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();
$counts = db()->query('SELECT status, COUNT(*) AS total FROM tour_requests GROUP BY status')->fetchAll();
$countMap = array_column($counts, 'total', 'status');
$requestTypeLabels=['tour'=>'Rundreise','beach'=>'Strandurlaub','ayurveda'=>'Ayurveda-Aufenthalt','maldives'=>'Malediven-Aufenthalt'];

$adminPage = 'requests';
$adminTitle = 'Reiseanfragen';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact"><div><p>KUNDENANFRAGEN</p><h1>Reiseanfragen</h1><span>Rundreisen, Strand-, Ayurveda- und Malediven-Aufenthalte.</span></div><div class="heading-actions"><a class="secondary-button" href="<?= e(url('stay.php?type=maldives&mode=admin')) ?>">＋ Malediven</a><a class="secondary-button" href="<?= e(url('stay.php?type=beach&mode=admin')) ?>">＋ Hotelaufenthalt</a><a class="primary-button" href="<?= e(url('plan.php?mode=admin')) ?>">＋ Neue Kundenreise</a></div></div>
    <nav class="filter-tabs">
        <a class="<?= $status === '' ? 'active' : '' ?>" href="requests.php">Alle <b><?= array_sum($countMap) ?></b></a>
        <?php foreach (['new'=>'Neu','contacted'=>'Kontaktiert','quoted'=>'Angebot','confirmed'=>'Bestätigt','archived'=>'Archiviert'] as $value=>$label): ?><a class="<?= $status === $value ? 'active' : '' ?>" href="?status=<?= e($value) ?>"><?= e($label) ?> <b><?= (int)($countMap[$value] ?? 0) ?></b></a><?php endforeach; ?>
    </nav>
    <section class="admin-card">
        <form class="table-toolbar" method="get"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?><input name="q" value="<?= e($query) ?>" placeholder="Name, E-Mail oder Referenz suchen …"><button type="submit">Suchen</button><span><?= count($requests) ?> Ergebnisse</span></form>
        <?php if (!$requests): ?><div class="empty"><span>◇</span><h3>Keine Anfragen gefunden</h3><p>Ändern Sie den Filter oder erstellen Sie eine neue Kundenreise.</p></div>
        <?php else: ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Kunde</th><th>Referenz / Datum</th><th>Route</th><th>Schätzung</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($requests as $request): ?><tr>
            <td><strong><?= e($request['customer_name']) ?></strong><small><?= e($request['customer_email']) ?><?= $request['customer_phone'] ? ' · ' . e($request['customer_phone']) : '' ?><?= $request['agent_company_name']?' · B2B '.e($request['agent_company_name']):'' ?></small></td>
            <td><strong><?= e($request['booking_reference']?:$request['reference']) ?></strong><small><?= e(date('d.m.Y H:i', strtotime($request['created_at']))) ?><?= $request['booking_reference']?' · Buchung':($request['admin_created'] ? ' · Admin' : ' · Website') ?></small></td>
            <td><?= e($requestTypeLabels[$request['request_type']]??'Rundreise') ?> · <?= (int)$request['travelers'] ?> Pers.<small><?= $request['request_type']==='tour'?(int)$request['place_count'].' Orte · ':e($request['hotel_name_de']).' · ' ?><?= (int)$request['duration'] ?> Nächte<?= $request['start_date'] ? ' · ab ' . e(date('d.m.Y', strtotime($request['start_date']))) : '' ?></small></td>
            <td><strong><?= e(money($request['estimate'])) ?></strong></td>
            <td><span class="status <?= e($request['status']) ?>"><?= e($request['status']) ?></span></td>
            <td><a href="request.php?id=<?= (int)$request['id'] ?>">Details →</a></td>
        </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
