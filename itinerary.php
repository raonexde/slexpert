<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$reference = trim((string)($_GET['ref'] ?? ''));
$stmt = db()->prepare('SELECT r.*,b.booking_reference FROM tour_requests r LEFT JOIN bookings b ON b.request_id=r.id WHERE r.reference=?');
$stmt->execute([$reference]);
$request = $stmt->fetch();
$isAdmin = admin_can('requests');
$isCustomerSession = $reference !== '' && hash_equals((string)($_SESSION['submitted_reference'] ?? ''), $reference);
$isPortalAccess = $request ? portal_can_access_request($request) : false;
if (!$request || (!$isAdmin && !$isCustomerSession && !$isPortalAccess)) {
    http_response_code(403);
    exit('This itinerary is not available in this session.');
}

$_SESSION['lang'] = $request['language'] === 'en' ? 'en' : 'de';
$routeStmt = db()->prepare('SELECT rd.*,d.name_de,d.name_en,d.region_de,d.region_en,d.latitude,d.longitude FROM tour_request_destinations rd JOIN destinations d ON d.id=rd.destination_id WHERE rd.request_id=? ORDER BY rd.sort_order');
$routeStmt->execute([$request['id']]);
$route = $routeStmt->fetchAll();
$itemStmt = db()->prepare('SELECT ri.*,c.destination_id,c.type,c.name_de,c.name_en,c.meta_de,c.meta_en,c.star_rating,cc.name_de AS classification_de,cc.name_en AS classification_en FROM tour_request_items ri JOIN catalog_items c ON c.id=ri.item_id LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id LEFT JOIN tour_request_destinations rd ON rd.id=ri.request_destination_id WHERE ri.request_id=? ORDER BY COALESCE(rd.sort_order,9999),c.type,c.sort_order');
$itemStmt->execute([$request['id']]);
$items = $itemStmt->fetchAll();
$itemsByRouteStop = [];
$legacyItemsByDestination = [];
foreach ($items as $item) {
    if ((int)($item['request_destination_id'] ?? 0) > 0) {
        $itemsByRouteStop[(int)$item['request_destination_id']][] = $item;
    } else {
        $legacyItemsByDestination[(int)$item['destination_id']][] = $item;
    }
}
$settings = db()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('google_maps_api_key','google_maps_map_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
$typeLabels = [
    'accommodation'=>t('Unterkunft','Accommodation'),'sight'=>t('Sehenswürdigkeit','Sight'),
    'activity'=>t('Aktivität','Activity'),'restaurant'=>t('Restaurant','Restaurant'),
    'spice_garden'=>t('Gewürzgarten','Spice garden'),'shop'=>t('Shop','Shop'),'service'=>t('Zusatzleistung','Additional service'),
];
$styleLabels = [
    'culture'=>t('Kultur & Genuss','Culture & food'),'nature'=>t('Natur & Safari','Nature & safari'),
    'ayurveda'=>t('Ayurveda & Ruhe','Ayurveda & calm'),'family'=>t('Familienreise','Family journey'),
];
$driveMinutes = (int)$request['route_duration_minutes'];
$driveLabel = $driveMinutes > 0 ? intdiv($driveMinutes,60) . ' ' . t('Std.','hr') . ' ' . ($driveMinutes%60) . ' ' . t('Min.','min') : '—';
$vehicleName = lang()==='en' ? $request['vehicle_name_en'] : $request['vehicle_name_de'];
$vehicleServiceLabel = $request['vehicle_service_type']==='self_drive' ? t('Ohne Fahrer / Selbstfahrer','Without driver / self-drive') : t('Mit Fahrer','With driver');
$vehicleLabel = $vehicleName !== '' ? $vehicleName . ' · ' . $vehicleServiceLabel . ' · ' . t('bis ','up to ') . (int)$request['vehicle_capacity'] . ' ' . t('Reisende','travellers') : '—';
$vehiclePriceParts = [(int)$request['vehicle_days'] . ' ' . t('Fahrzeugtage','vehicle days') . ' × ' . money_precise($request['vehicle_daily_rate']) . ' / ' . t('Tag','day')];
if ($request['vehicle_service_type']!=='self_drive') $vehiclePriceParts[] = number_format((float)$request['route_distance_km'],1,',','.') . ' km × ' . money_precise($request['vehicle_km_rate']) . ' / km';
$vehiclePriceLabel = implode(' + ', $vehiclePriceParts) . ' = ' . money_precise($request['vehicle_cost']);
$guidePriceLabel = $request['guide_name'] !== '' ? (int)$request['guide_days'] . ' ' . t('Guide-Tage','guide days') . ' × ' . money_precise($request['guide_daily_rate']) . ' / ' . t('Tag','day') . ' = ' . money_precise($request['guide_cost']) : '';
$documentReference = $request['booking_reference'] ?: $request['reference'];
$mapData = [
    'apiKey'=>trim((string)($settings['google_maps_api_key'] ?? '')),
    'mapId'=>trim((string)($settings['google_maps_map_id'] ?? '')) ?: 'DEMO_MAP_ID',
    'language'=>lang(),
    'places'=>array_map(static fn(array $place): array => [
        'name'=>lang()==='en'?$place['name_en']:$place['name_de'],
        'latitude'=>$place['latitude']!==null?(float)$place['latitude']:null,
        'longitude'=>$place['longitude']!==null?(float)$place['longitude']:null,
    ],$route),
    'labels'=>[
        'missingKey'=>t('Google Maps ist noch nicht eingerichtet. Die Reiseroute ist unten als Liste verfügbar.','Google Maps is not configured yet. The journey route is listed below.'),
        'error'=>t('Die Google-Fahrroute konnte nicht geladen werden.','The Google driving route could not be loaded.'),
        'hours'=>t('Std.','hr'),'minutes'=>t('Min.','min'),
    ],
];
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(t('Reiseverlauf','Itinerary')) ?> <?= e($documentReference) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/itinerary.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/itinerary-mobile.css')) ?>">
    <style>.journey-facts{grid-template-columns:repeat(7,1fr)}.vehicle-price-breakdown{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:-1px 0 12px;padding:13px 15px;border:1px solid rgba(21,56,46,.17);background:#f7f5ef}.vehicle-price-breakdown small{font-size:7px;letter-spacing:.12em;color:#737a74}.vehicle-price-breakdown strong{font:400 12px Georgia,serif;text-align:right}.travel-team{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:0 0 18px}.travel-team article{display:flex;align-items:center;gap:13px;border:1px solid rgba(21,56,46,.17);padding:10px;background:#fff}.travel-team img{width:95px;height:68px;object-fit:cover}.travel-team .guide-img{width:68px;height:68px;border-radius:50%}.travel-team div{display:flex;flex-direction:column;gap:4px}.travel-team small{font-size:7px;letter-spacing:.12em;color:#9b7229}.travel-team strong{font:400 15px Georgia,serif}.travel-team span{font-size:8px;color:#737a74;line-height:1.4}@media(max-width:760px){.vehicle-price-breakdown{align-items:flex-start;flex-direction:column}.vehicle-price-breakdown strong{text-align:left}.travel-team{grid-template-columns:1fr}}</style>
    <style>@media(max-width:760px){.journey-facts{grid-template-columns:repeat(2,minmax(0,1fr))}.vehicle-price-breakdown small,.travel-team small{font-size:10px}.vehicle-price-breakdown strong{font-size:14px}.travel-team strong{font-size:18px}.travel-team span{font-size:12px}}</style>
</head>
<body>
<nav class="print-controls"><a href="<?= e($isAdmin ? url('admin/request.php?id=' . $request['id']) : ($isPortalAccess ? url('account/request.php?id='.(int)$request['id']) : url('success.php?ref=' . urlencode($reference) . '&lang=' . lang()))) ?>">← <?= e(t('Zurück','Back')) ?></a><button type="button" onclick="window.print()">⌁ <?= e(t('Route drucken / als PDF speichern','Print route / save as PDF')) ?></button></nav>
<main class="itinerary-sheet">
    <header class="itinerary-header"><div class="itinerary-brand"><span>SL</span><div><strong>SRI LANKA</strong><small>EXPERT · BY RAONEX GMBH</small></div></div><div class="itinerary-ref"><small><?= e($request['booking_reference']?t('BUCHUNGSREFERENZ','BOOKING REFERENCE'):t('REISEREFERENZ','JOURNEY REFERENCE')) ?></small><strong><?= e($documentReference) ?></strong></div></header>
    <section class="itinerary-title"><p>TAILOR-MADE SRI LANKA</p><h1><?= e(t('Ihre persönliche Reiseroute','Your personal journey route')) ?></h1><span><?= e($request['customer_name']) ?> · <?= e(date('d.m.Y',strtotime($request['created_at']))) ?></span></section>
    <section class="journey-facts">
        <div><small><?= e(t('Reisebeginn','Start date')) ?></small><strong><?= $request['start_date']?e(date('d.m.Y',strtotime($request['start_date']))):e(t('Flexibel','Flexible')) ?></strong></div>
        <div><small><?= e(t('Reisende','Travellers')) ?></small><strong><?= (int)$request['travelers'] ?></strong></div>
        <div><small><?= e(t('Gesamtnächte','Total nights')) ?></small><strong><?= (int)$request['duration'] ?> <?= e(t('Nächte','nights')) ?></strong></div>
        <div><small><?= e(t('Reisestil','Travel style')) ?></small><strong><?= e($styleLabels[$request['travel_style']] ?? ($request['travel_style'] ?: '—')) ?></strong></div>
        <div><small><?= e(t('Fahrzeug','Vehicle')) ?></small><strong><?= e($vehicleLabel) ?></strong></div>
        <div><small><?= e(t('Fahrstrecke','Driving distance')) ?></small><strong data-itinerary-distance><?= (float)$request['route_distance_km']>0?e(number_format((float)$request['route_distance_km'],0,',','.')).' km':'—' ?></strong></div>
        <div><small><?= e(t('Fahrzeit','Driving time')) ?></small><strong data-itinerary-duration><?= e($driveLabel) ?></strong></div>
    </section>
    <section class="vehicle-price-breakdown"><small><?= e(t('FAHRZEUGKOSTEN','VEHICLE COST')) ?></small><strong><?= e($vehiclePriceLabel) ?></strong></section>
    <?php if($request['guide_name']!==''):?><section class="vehicle-price-breakdown"><small><?= e(t('REISELEITUNG','TOUR GUIDE')) ?></small><strong><?= e($guidePriceLabel) ?></strong></section><?php endif;?>
    <?php if($request['vehicle_image_path']||$request['guide_name']!==''):?><section class="travel-team">
        <?php if($request['vehicle_image_path']):?><article><img src="<?= e(url($request['vehicle_image_path'])) ?>" alt=""><div><small><?= e(t('IHR FAHRZEUG','YOUR VEHICLE')) ?></small><strong><?= e($vehicleName) ?></strong><span><?= e($vehicleServiceLabel) ?> · <?= (int)$request['vehicle_capacity'] ?> <?= e(t('Reisende','travellers')) ?></span></div></article><?php endif;?>
        <?php if($request['guide_name']!==''):?><article><?php if($request['guide_photo_path']):?><img class="guide-img" src="<?= e(url($request['guide_photo_path'])) ?>" alt=""><?php endif;?><div><small><?= e(t('IHRE REISELEITUNG','YOUR TOUR GUIDE')) ?></small><strong><?= e($request['guide_name']) ?></strong><span><?= e($request['guide_languages']) ?></span></div></article><?php endif;?>
    </section><?php endif;?>
    <section class="itinerary-map-card"><div class="itinerary-map-heading"><div><small>GOOGLE MAPS</small><h2><?= e(t('Fahrroute durch Sri Lanka','Driving route through Sri Lanka')) ?></h2></div><span><?= count($route) ?> <?= e(t('Orte','stops')) ?> · <?= array_sum(array_column($route,'nights')) ?> <?= e(t('Nächte','nights')) ?></span></div><div class="itinerary-map" data-itinerary-map></div><div class="itinerary-map-status visible" data-itinerary-map-status><?= e(t('Karte wird geladen …','Loading map…')) ?></div></section>
    <section class="route-section"><p class="section-kicker"><?= e(t('REISEVERLAUF','JOURNEY ROUTE')) ?></p><h2><?= e(t('Ihre gewählten Aufenthalte','Your selected stays')) ?></h2><div class="route-timeline">
        <?php foreach($route as $index=>$place):
            $placeItems = $itemsByRouteStop[(int)$place['id']] ?? $legacyItemsByDestination[(int)$place['destination_id']] ?? [];
        ?>
        <article class="route-stop">
            <span><?= $index+1 ?></span>
            <div class="route-stop-main">
                <small><?= e(lang()==='en'?$place['region_en']:$place['region_de']) ?></small>
                <h3><?= e(lang()==='en'?$place['name_en']:$place['name_de']) ?></h3>
                <?php if($placeItems): ?><ul><?php foreach($placeItems as $item): ?><li>
                    <b><?= e($typeLabels[$item['type']]??$item['type']) ?>:</b> <?= e(lang()==='en'?$item['name_en']:$item['name_de']) ?>
                    <?php $classification=lang()==='en'?$item['classification_en']:$item['classification_de']; if($classification): ?> <em>· <?= e($classification) ?><?= (int)$item['star_rating']>0?' · '.str_repeat('★',(int)$item['star_rating']):'' ?></em><?php endif; ?>
                    <?php $meta=lang()==='en'?$item['meta_en']:$item['meta_de']; if($meta): ?> <em>· <?= e($meta) ?></em><?php endif; ?>
                    <?php if($item['type']==='accommodation'): $roomTypeName=lang()==='en'?$item['room_type_name_en']:$item['room_type_name_de']; ?> <em>· <?= (int)$item['room_count'] ?> <?= e(t('Zimmer','room(s)')) ?><?= $roomTypeName?' · '.e($roomTypeName):'' ?> · <?= e(money($item['unit_price'])) ?> / <?= e(t('Zimmer / Nacht','room / night')) ?></em>
                    <?php elseif($item['price_basis']==='on_request'): ?> <em>· <?= e(t('Preis auf Anfrage','Price on request')) ?></em>
                    <?php elseif($item['price_basis']==='free'): ?> <em>· <?= e(t('Kostenfrei','Free')) ?></em>
                    <?php elseif((float)$item['unit_price']>0): ?> <em>· <?= e(money($item['unit_price'])) ?> / <?= e(stay_price_basis_label($item['price_basis'])) ?></em>
                    <?php else: ?> <em>· <?= e(t('Inklusive','Included')) ?></em><?php endif; ?>
                    <?php if((float)$item['line_total']>0): ?> <em>· <?= e(t('Gesamt','Total')) ?> <?= e(money_precise($item['line_total'])) ?></em><?php endif; ?>
                    <?php $mealPlan=lang()==='en'?$item['meal_plan_name_en']:$item['meal_plan_name_de']; if($item['type']==='accommodation'&&$mealPlan): ?> <em>· <?= e(t('Verpflegung','Meal plan')) ?>: <?= e($mealPlan) ?><?php if((float)$item['meal_plan_supplement']>0): ?> (+<?= e(money($item['meal_plan_supplement'])) ?> / <?= e(t('Pers. / Nacht','person / night')) ?>)<?php endif; ?></em><?php endif; ?>
                </li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <strong><?= (int)$place['nights'] ?> <?= e(t('Nächte','nights')) ?></strong>
        </article>
        <?php endforeach; ?>
    </div></section>
    <section class="itinerary-bottom"><div><small><?= e(t('AKTUELLE SCHÄTZUNG','CURRENT ESTIMATE')) ?></small><strong><?= e(money_precise($request['estimate'])) ?></strong><p><?= e(t('Die endgültige Bestätigung erfolgt nach persönlicher Prüfung von Verfügbarkeit und Leistungen.','Final confirmation follows after a personal review of availability and services.')) ?></p></div><div><small><?= e(t('WÜNSCHE & HINWEISE','WISHES & NOTES')) ?></small><p><?= nl2br(e($request['notes']?:t('Keine besonderen Hinweise.','No special notes.'))) ?></p></div></section>
    <footer class="itinerary-footer"><span><?= e(site_setting('brand_name', 'Sri Lanka Expert')) ?></span><span><?= e(t('Sri Lanka. Persönlich geplant.','Sri Lanka. Personally planned.')) ?></span></footer>
</main>
<script>window.ITINERARY_MAP_DATA=<?= json_encode($mapData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('js/itinerary-map.js')) ?>"></script>
</body>
</html>
