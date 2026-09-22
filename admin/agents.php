<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('agents');
$query=trim((string)($_GET['q']??''));$active=($_GET['active']??'')==='0'?'0':(($_GET['active']??'')==='1'?'1':'');
$sql="SELECT a.*,u.id AS user_id,u.last_login_at,
      (SELECT COUNT(*) FROM tour_requests r WHERE r.b2b_agent_id=a.id) request_count,
      (SELECT COUNT(*) FROM bookings b WHERE b.b2b_agent_id=a.id) booking_count,
      (SELECT COALESCE(SUM(b.total_amount),0) FROM bookings b WHERE b.b2b_agent_id=a.id AND b.status<>'cancelled') booking_value
      FROM b2b_agents a LEFT JOIN portal_users u ON u.b2b_agent_id=a.id AND u.user_type='agent' WHERE 1=1";$params=[];
if($active!==''){$sql.=' AND a.active=?';$params[]=(int)$active;}
if($query!==''){$sql.=' AND (a.company_name LIKE ? OR a.agency_code LIKE ? OR a.contact_name LIKE ? OR a.email LIKE ?)';$like='%'.$query.'%';array_push($params,$like,$like,$like,$like);}
$sql.=' ORDER BY a.active DESC,a.company_name LIMIT 250';$stmt=db()->prepare($sql);$stmt->execute($params);$agents=$stmt->fetchAll();
$counts=db()->query('SELECT active,COUNT(*) total FROM b2b_agents GROUP BY active')->fetchAll(PDO::FETCH_KEY_PAIR);
$adminPage='agents';$adminTitle='B2B-Agenturen';require __DIR__.'/_header.php';
?>
<div class="admin-content"><div class="page-heading compact"><div><p>PARTNERVERTRIEB</p><h1>B2B-Agenturen</h1><span>Agenturen, Portalzugänge, Provisionen und Kundenbuchungen verwalten.</span></div><a class="primary-button" href="agent-edit.php">＋ Neue Agentur</a></div>
<nav class="filter-tabs"><a class="<?= $active===''?'active':'' ?>" href="agents.php">Alle <b><?= array_sum(array_map('intval',$counts)) ?></b></a><a class="<?= $active==='1'?'active':'' ?>" href="?active=1">Aktiv <b><?= (int)($counts[1]??0) ?></b></a><a class="<?= $active==='0'?'active':'' ?>" href="?active=0">Inaktiv <b><?= (int)($counts[0]??0) ?></b></a></nav>
<section class="admin-card"><form class="table-toolbar" method="get"><?php if($active!==''):?><input type="hidden" name="active" value="<?= e($active) ?>"><?php endif;?><input name="q" value="<?= e($query) ?>" placeholder="Firma, Code, Kontakt oder E-Mail …"><button>Suchen</button><span><?= count($agents) ?> Ergebnisse</span></form>
<?php if(!$agents):?><div class="empty"><span>◇</span><h3>Noch keine B2B-Agenturen</h3><p>Erstellen Sie die erste Agentur mit eigenem Portalzugang.</p></div><?php else:?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Agentur</th><th>Kontakt / Login</th><th>Provision</th><th>Anfragen</th><th>Buchungen</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($agents as $agent):?><tr><td><strong><?= e($agent['company_name']) ?></strong><small><?= e($agent['agency_code']) ?> · <?= e($agent['country']?:'—') ?></small></td><td><strong><?= e($agent['contact_name']) ?></strong><small><?= e($agent['email']) ?><?= $agent['last_login_at']?' · Login '.e(date('d.m.Y',strtotime($agent['last_login_at']))):' · noch kein Login' ?></small></td><td><strong><?= e(number_format((float)$agent['commission_percent'],2,',','.')) ?> %</strong></td><td><?= (int)$agent['request_count'] ?></td><td><strong><?= (int)$agent['booking_count'] ?></strong><small><?= e(money_precise($agent['booking_value'])) ?></small></td><td><span class="status <?= $agent['active']?'confirmed':'archived' ?>"><?= $agent['active']?'Aktiv':'Inaktiv' ?></span></td><td><a href="agent-edit.php?id=<?= (int)$agent['id'] ?>">Bearbeiten →</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section></div>
<?php require __DIR__.'/_footer.php';?>
