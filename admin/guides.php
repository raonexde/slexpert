<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$status = in_array($_GET['status']??'', ['pending','approved','suspended','rejected'], true) ? (string)$_GET['status'] : '';
$query = trim((string)($_GET['q']??''));
$sql = 'SELECT * FROM guides WHERE 1=1'; $params=[];
if($status!==''){$sql.=' AND status=?';$params[]=$status;}
if($query!==''){$sql.=' AND (guide_code LIKE ? OR display_name LIKE ? OR email LIKE ? OR languages LIKE ?)';$like='%'.$query.'%';array_push($params,$like,$like,$like,$like);}
$sql.=' ORDER BY FIELD(status,\'pending\',\'approved\',\'suspended\',\'rejected\'),sort_order,display_name LIMIT 300';
$stmt=db()->prepare($sql);$stmt->execute($params);$guides=$stmt->fetchAll();
$counts=db()->query('SELECT status,COUNT(*) total FROM guides GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$adminPage='guides';$adminTitle='Reiseleiter';require __DIR__.'/_header.php';
?>
<div class="admin-content"><div class="page-heading compact"><div><p>PARTNER</p><h1>Reiseleiter</h1><span>Registrierungen prüfen, Profile freigeben und Tageshonorare verwalten.</span></div><a class="primary-button" href="guide-edit.php">＋ Reiseleiter anlegen</a></div>
<div class="guide-status-summary"><a href="guides.php">Alle</a><a href="?status=pending">Neu <?= (int)($counts['pending']??0) ?></a><a href="?status=approved">Freigegeben <?= (int)($counts['approved']??0) ?></a><a href="?status=suspended">Pausiert <?= (int)($counts['suspended']??0) ?></a><a href="?status=rejected">Abgelehnt <?= (int)($counts['rejected']??0) ?></a></div>
<form class="filter-bar"><input name="q" value="<?= e($query) ?>" placeholder="Name, Referenz, E-Mail oder Sprache"><button class="secondary-button">Suchen</button></form>
<section class="admin-card"><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Foto</th><th>Reiseleiter</th><th>Sprachen</th><th>Qualifikation</th><th>Honorar</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($guides as $guide):?><tr>
<td><?php if($guide['photo_path']):?><img class="guide-photo" src="<?= e(url($guide['photo_path'])) ?>" alt=""><?php else:?><span class="guide-photo admin-table-placeholder">◎</span><?php endif;?></td>
<td><strong><?= e($guide['display_name']) ?></strong><small><?= e($guide['guide_code']) ?> · <?= e($guide['email']) ?><br><?= e($guide['phone']) ?></small></td><td><?= e($guide['languages']?:'—') ?></td><td><?= e($guide['license_type']?:'—') ?><small><?= e($guide['license_number']) ?><?= $guide['driver_guide']?' · Driver-Guide':'' ?></small></td><td><?= e(money_precise($guide['daily_rate'])) ?> / Tag</td><td><span class="status <?= $guide['status']==='approved'&&$guide['active']?'confirmed':($guide['status']==='pending'?'new':'archived') ?>"><?= e(ucfirst($guide['status'])) ?></span></td><td><a href="guide-edit.php?id=<?= (int)$guide['id'] ?>">Öffnen →</a></td>
</tr><?php endforeach;?><?php if(!$guides):?><tr><td colspan="7">Keine Einträge gefunden.</td></tr><?php endif;?></tbody></table></div></section></div>
<?php require __DIR__.'/_footer.php'; ?>
