<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$reference = trim((string)($_GET['ref'] ?? ''));
$stmt = db()->prepare("SELECT r.*,b.booking_reference FROM tour_requests r LEFT JOIN bookings b ON b.request_id=r.id WHERE r.reference=? AND r.request_type IN ('beach','ayurveda','maldives')");
$stmt->execute([$reference]);
$request = $stmt->fetch();
$isAdmin = (bool)admin_user();
$isCustomerSession = $reference !== '' && hash_equals((string)($_SESSION['submitted_reference'] ?? ''),$reference);
$isPortalAccess = $request ? portal_can_access_request($request) : false;
if (!$request || (!$isAdmin && !$isCustomerSession && !$isPortalAccess)) {
    http_response_code(403);
    exit('This proposal is not available in this session.');
}
$_SESSION['lang'] = $request['language'] === 'en' ? 'en' : 'de';
$destinationStmt = db()->prepare('SELECT rd.*,d.name_de,d.name_en,d.region_de,d.region_en FROM tour_request_destinations rd JOIN destinations d ON d.id=rd.destination_id WHERE rd.request_id=? ORDER BY rd.sort_order LIMIT 1');
$destinationStmt->execute([$request['id']]);
$destination = $destinationStmt->fetch() ?: [];
$itemStmt = db()->prepare('SELECT ri.*,c.type,c.name_de,c.name_en,c.meta_de,c.meta_en FROM tour_request_items ri JOIN catalog_items c ON c.id=ri.item_id WHERE ri.request_id=? ORDER BY CASE WHEN c.type=\'accommodation\' THEN 0 ELSE 1 END,c.type,c.sort_order,c.id');
$itemStmt->execute([$request['id']]);
$items = $itemStmt->fetchAll();
$hotel = null;
$services = [];
foreach ($items as $item) {
    if ($item['type'] === 'accommodation' && !$hotel) $hotel = $item;
    else $services[] = $item;
}
$typeLabels = [
    'sight'=>t('Ausflug','Excursion'),'activity'=>t('Aktivität','Activity'),
    'shop'=>t('Lokaler Shop','Local shop'),'service'=>t('Zusatzleistung','Additional service'),
];
$stayLabel = match($request['request_type']){'ayurveda'=>t('Ayurveda-Aufenthalt','Ayurveda retreat'),'maldives'=>t('Malediven-Resorturlaub','Maldives resort holiday'),default=>t('Strandurlaub','Beach vacation')};
$arrival = $request['start_date'] ? new DateTimeImmutable($request['start_date']) : null;
$departure = $arrival ? $arrival->modify('+' . (int)$request['duration'] . ' days') : null;
$mealPlanName = $hotel ? (lang()==='en'?$hotel['meal_plan_name_en']:$hotel['meal_plan_name_de']) : '';
$documentReference = $request['booking_reference'] ?: $request['reference'];
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title><?= e($stayLabel) ?> <?= e($documentReference) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/itinerary.css')) ?>">
    <style>
        .stay-facts{grid-template-columns:repeat(6,1fr)}.stay-hotel-print{border:1px solid var(--line);padding:24px;margin:0 0 28px;display:grid;grid-template-columns:1fr auto;gap:20px;break-inside:avoid}.stay-hotel-print>div>small,.price-lines small{color:#9b7229;font-size:7px;letter-spacing:.12em}.stay-hotel-print h2{font:400 25px Georgia,serif;margin:5px 0}.stay-hotel-print p{margin:5px 0;color:var(--muted);font-size:9px;line-height:1.55}.stay-hotel-print>strong{font:400 23px Georgia,serif}.stay-hotel-meta{display:flex;gap:18px;margin-top:13px}.stay-hotel-meta span{font-size:8px}.service-print-list{display:grid;grid-template-columns:1fr 1fr;gap:10px}.service-print{border:1px solid var(--line);padding:14px;break-inside:avoid}.service-print small{color:#9b7229;font-size:7px;text-transform:uppercase;letter-spacing:.1em}.service-print h3{font:400 15px Georgia,serif;margin:5px 0}.service-print p{font-size:8px;color:var(--muted);margin:0 0 8px}.service-print strong{font-size:9px}.price-lines{margin:22px 0;border-top:1px solid var(--line)}.price-lines>div{display:flex;justify-content:space-between;padding:10px 3px;border-bottom:1px solid var(--line);font-size:9px}.price-lines strong{font-weight:700}.stay-notes{margin-top:22px}@media(max-width:760px){.service-print-list{grid-template-columns:1fr}.stay-hotel-print{grid-template-columns:1fr}.stay-facts{grid-template-columns:repeat(2,1fr)}}@media print{.stay-hotel-print{padding:17px;margin-bottom:18px}.service-print{padding:10px}.route-section{padding-top:18px}.price-lines{margin:15px 0}.stay-notes{margin-top:15px}}
    </style>
</head>
<body>
<nav class="print-controls"><a href="<?= e($isAdmin?url('admin/request.php?id='.$request['id']):($isPortalAccess?url('account/request.php?id='.(int)$request['id']):url('success.php?ref='.urlencode($reference).'&lang='.lang()))) ?>">← <?= e(t('Zurück','Back')) ?></a><button type="button" onclick="window.print()">⌁ <?= e(t('Aufenthalt drucken / als PDF speichern','Print stay / save as PDF')) ?></button></nav>
<main class="itinerary-sheet">
    <header class="itinerary-header"><div class="itinerary-brand"><span><?= $request['request_type']==='maldives'?'MV':'SL' ?></span><div><strong><?= $request['request_type']==='maldives'?'MALDIVES':'SRI LANKA' ?></strong><small>EXPERT · BY RAONEX GMBH</small></div></div><div class="itinerary-ref"><small><?= e($request['booking_reference']?t('BUCHUNGSREFERENZ','BOOKING REFERENCE'):t('ANFRAGEREFERENZ','ENQUIRY REFERENCE')) ?></small><strong><?= e($documentReference) ?></strong></div></header>
    <section class="itinerary-title"><p><?= e(strtoupper($stayLabel)) ?></p><h1><?= e(t('Ihr persönlicher Hotelaufenthalt','Your personal hotel stay')) ?></h1><span><?= e($request['customer_name']) ?> · <?= e(date('d.m.Y',strtotime($request['created_at']))) ?></span></section>
    <section class="journey-facts stay-facts">
        <div><small><?= e(t('Anreise','Arrival')) ?></small><strong><?= $arrival?e($arrival->format('d.m.Y')):e(t('Flexibel','Flexible')) ?></strong></div>
        <div><small><?= e(t('Abreise','Departure')) ?></small><strong><?= $departure?e($departure->format('d.m.Y')):e(t('Flexibel','Flexible')) ?></strong></div>
        <div><small><?= e(t('Aufenthalt','Stay')) ?></small><strong><?= (int)$request['duration'] ?> <?= e(t('Nächte','nights')) ?></strong></div>
        <div><small><?= e(t('Erwachsene','Adults')) ?></small><strong><?= (int)$request['adults'] ?></strong></div>
        <div><small><?= e(t('Kinder bis 12','Children up to 12')) ?></small><strong><?= (int)$request['children'] ?></strong></div>
        <div><small><?= e(t('Doppelzimmer','Double rooms')) ?></small><strong><?= (int)$request['rooms'] ?></strong></div>
    </section>
    <section class="stay-hotel-print"><div><small><?= e(t('GEWÄHLTES HOTEL','SELECTED HOTEL')) ?> · <?= e(lang()==='en'?($destination['name_en']??''):($destination['name_de']??'')) ?></small><h2><?= e(lang()==='en'?$request['hotel_name_en']:$request['hotel_name_de']) ?></h2><?php if($hotel):?><p><?= e(lang()==='en'?$hotel['meta_en']:$hotel['meta_de']) ?></p><div class="stay-hotel-meta"><span><?= (int)$request['rooms'] ?> <?= e(t('Doppelzimmer','double rooms')) ?></span><span><?= e(money_precise($hotel['unit_price'])) ?> / <?= e(t('Zimmer / Nacht','room / night')) ?></span><?php if($mealPlanName):?><span><?= e(t('Verpflegung','Meal plan')) ?>: <?= e($mealPlanName) ?></span><?php endif;?></div><?php endif;?></div><strong><?= e(money_precise((float)$request['room_cost']+(float)$request['extra_bed_cost']+(float)$request['meal_cost'])) ?></strong></section>
    <section class="route-section"><p class="section-kicker"><?= e(t('ZUSATZLEISTUNGEN','ADDITIONAL SERVICES')) ?></p><h2><?= e(t('Ihre gewählten Leistungen','Your selected services')) ?></h2>
        <?php if(!$services):?><p><?= e(t('Keine zusätzlichen Leistungen ausgewählt.','No additional services selected.')) ?></p><?php else:?><div class="service-print-list"><?php foreach($services as $service):?><article class="service-print"><small><?= e($typeLabels[$service['type']]??$service['type']) ?></small><h3><?= e(lang()==='en'?$service['name_en']:$service['name_de']) ?></h3><p><?= e(lang()==='en'?$service['meta_en']:$service['meta_de']) ?></p><strong><?= e(money_precise($service['unit_price'])) ?> · <?= e(stay_price_basis_label($service['price_basis'])) ?> · <?= e(t('Gesamt','Total')) ?> <?= e(money_precise($service['line_total'])) ?></strong></article><?php endforeach;?></div><?php endif;?>
    </section>
    <section class="price-lines">
        <div><span><?= e(t('Zimmerpreis','Room rate')) ?></span><strong><?= e(money_precise($request['room_cost'])) ?></strong></div>
        <div><span><?= e(t('Zusatzbetten','Extra beds')) ?><?php if($hotel&&((int)$hotel['extra_bed_adults']+(int)$hotel['extra_bed_children'])>0):?> (<?= (int)$hotel['extra_bed_adults'] ?> <?= e(t('Erw.','adult')) ?> · <?= (int)$hotel['extra_bed_children'] ?> <?= e(t('Kind','child')) ?>)<?php endif;?></span><strong><?= e(money_precise($request['extra_bed_cost'])) ?></strong></div>
        <div><span><?= e(t('Verpflegung','Meal plan')) ?><?= $mealPlanName?' · '.e($mealPlanName):'' ?></span><strong><?= e(money_precise($request['meal_cost'])) ?></strong></div>
        <div><span><?= e(t('Zusatzleistungen','Additional services')) ?></span><strong><?= e(money_precise($request['services_cost'])) ?></strong></div>
    </section>
    <section class="itinerary-bottom"><div><small><?= e(t('AKTUELLE SCHÄTZUNG','CURRENT ESTIMATE')) ?></small><strong><?= e(money_precise($request['estimate'])) ?></strong><p><?= e(t('Die endgültige Bestätigung erfolgt nach Prüfung der Hotelverfügbarkeit und Leistungen.','Final confirmation follows after checking hotel and service availability.')) ?></p></div><div><small><?= e(t('WÜNSCHE & HINWEISE','WISHES & NOTES')) ?></small><p><?= nl2br(e($request['notes']?:t('Keine besonderen Hinweise.','No special notes.'))) ?></p></div></section>
    <footer class="itinerary-footer"><span><?= e(site_setting('brand_name', 'Sri Lanka Expert')) ?></span><span><?= e($request['request_type']==='maldives'?t('Malediven. Persönlich geplant.','Maldives. Personally planned.'):t('Sri Lanka. Persönlich geplant.','Sri Lanka. Personally planned.')) ?></span></footer>
</main>
</body>
</html>
