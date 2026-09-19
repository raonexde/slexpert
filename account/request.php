<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_portal_user();
$id=(int)($_GET['id']??0);
$stmt=db()->prepare('SELECT r.*,b.id AS booking_id,b.booking_reference FROM tour_requests r LEFT JOIN bookings b ON b.request_id=r.id WHERE r.id=?');
$stmt->execute([$id]); $request=$stmt->fetch();
if(!$request||!portal_can_access_request($request)){http_response_code(403);exit('Access denied');}
$routeStmt=db()->prepare('SELECT rd.*,d.name_de,d.name_en,d.region_de,d.region_en FROM tour_request_destinations rd JOIN destinations d ON d.id=rd.destination_id WHERE rd.request_id=? ORDER BY rd.sort_order');
$routeStmt->execute([$id]);$route=$routeStmt->fetchAll();
$itemStmt=db()->prepare('SELECT ri.*,c.type,c.name_de,c.name_en FROM tour_request_items ri JOIN catalog_items c ON c.id=ri.item_id WHERE ri.request_id=? ORDER BY c.type,c.sort_order');
$itemStmt->execute([$id]);$items=$itemStmt->fetchAll();
$typeLabel=match($request['request_type']){'beach'=>t('Strandurlaub','Beach vacation'),'ayurveda'=>t('Ayurveda-Aufenthalt','Ayurveda retreat'),'maldives'=>t('Malediven-Aufenthalt','Maldives stay'),default=>t('Rundreise','Tour')};
$statusLabels=['new'=>t('Neu eingegangen','Received'),'contacted'=>t('In Bearbeitung','In progress'),'quoted'=>t('Angebot erstellt','Proposal prepared'),'confirmed'=>t('Bestätigt','Confirmed'),'cancelled'=>t('Storniert','Cancelled'),'archived'=>t('Archiviert','Archived')];
$isHotel=in_array($request['request_type'],['beach','ayurveda','maldives'],true);
$pageTitle=t('Reiseanfrage','Travel enquiry').' '.$request['reference'];$bodyClass='account-page';$extraStyles=['css/account.css'];
require dirname(__DIR__).'/includes/public-header.php';$accountPage='dashboard';require __DIR__.'/_nav.php';
?>
<main class="account-main"><a class="account-back" href="<?= e(url('account/index.php?lang='.lang())) ?>">← <?= e(t('Zur Übersicht','Back to overview')) ?></a>
<section class="account-heading compact"><div><p><?= e($typeLabel) ?> · <?= e($request['reference']) ?></p><h1><?= e($request['customer_name']) ?></h1><span><?= e($statusLabels[$request['status']]??$request['status']) ?></span></div><div class="account-actions"><?php if($request['booking_id']):?><a class="account-secondary" href="<?= e(url('account/booking.php?id='.(int)$request['booking_id'])) ?>"><?= e(t('Buchung öffnen','Open booking')) ?></a><?php endif;?><a class="account-primary inline" target="_blank" href="<?= e(url(($isHotel?'stay-proposal.php':'itinerary.php').'?ref='.urlencode($request['reference']))) ?>"><?= e(t('Dokument anzeigen & drucken','View & print document')) ?> ⌁</a></div></section>
<div class="account-detail-grid"><section class="account-card"><header><div><h2><?= e(t('Reiseverlauf','Journey')) ?></h2><p><?= count($route) ?> <?= e(t('Orte','stops')) ?> · <?= (int)$request['duration'] ?> <?= e(t('Nächte','nights')) ?></p></div></header><div class="account-route"><?php foreach($route as $i=>$place):?><article><span><?= $i+1 ?></span><div><small><?= e(lang()==='en'?$place['region_en']:$place['region_de']) ?></small><strong><?= e(lang()==='en'?$place['name_en']:$place['name_de']) ?></strong></div><b><?= (int)$place['nights'] ?> <?= e(t('Nächte','nights')) ?></b></article><?php endforeach;?></div>
<header class="sub"><div><h2><?= e(t('Gewählte Leistungen','Selected services')) ?></h2></div></header><?php if(!$items):?><div class="account-empty small"><?= e(t('Keine Zusatzleistungen gewählt.','No additional services selected.')) ?></div><?php else:?><div class="account-services"><?php foreach($items as $item):?><article><div><small><?= e($item['type']) ?></small><strong><?= e(lang()==='en'?$item['name_en']:$item['name_de']) ?></strong></div><b><?= e(money_precise($item['line_total']?:$item['unit_price'])) ?></b></article><?php endforeach;?></div><?php endif;?></section>
<aside class="account-side"><section class="account-card account-info"><h2><?= e(t('Anfragedaten','Enquiry details')) ?></h2><dl><dt><?= e(t('Status','Status')) ?></dt><dd><?= e($statusLabels[$request['status']]??$request['status']) ?></dd><dt><?= e(t('Reisebeginn','Start date')) ?></dt><dd><?= $request['start_date']?e(date('d.m.Y',strtotime($request['start_date']))):e(t('Flexibel','Flexible')) ?></dd><dt><?= e(t('Reisende','Travellers')) ?></dt><dd><?= (int)$request['travelers'] ?></dd><dt><?= e(t('Schätzung','Estimate')) ?></dt><dd class="big"><?= e(money_precise($request['estimate'])) ?></dd></dl></section><section class="account-card account-info"><h2><?= e(t('Hinweise','Notes')) ?></h2><p><?= nl2br(e($request['notes']?:t('Keine besonderen Hinweise.','No special notes.'))) ?></p></section></aside></div></main>
<?php require dirname(__DIR__).'/includes/public-footer.php';?>
