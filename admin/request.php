<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('requests', request_is_post());
$id = (int)($_GET['id'] ?? 0);

if (request_is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'status');
    if ($action === 'convert_booking') {
        require_admin_permission('bookings', true);
        $sourceStmt = db()->prepare('SELECT * FROM tour_requests WHERE id=?');
        $sourceStmt->execute([$id]);
        $sourceRequest = $sourceStmt->fetch();
        if (!$sourceRequest) { http_response_code(404); exit('Request not found'); }
        $existingStmt = db()->prepare('SELECT id FROM bookings WHERE request_id=?');
        $existingStmt->execute([$id]);
        $existingBookingId = (int)($existingStmt->fetchColumn() ?: 0);
        if ($existingBookingId > 0) redirect('admin/booking.php?id=' . $existingBookingId);
        $commissionPercent = 0.0;
        if ((int)$sourceRequest['b2b_agent_id'] > 0) {
            $commissionStmt = db()->prepare('SELECT commission_percent FROM b2b_agents WHERE id=?');
            $commissionStmt->execute([(int)$sourceRequest['b2b_agent_id']]);
            $commissionPercent = (float)($commissionStmt->fetchColumn() ?: 0);
        }
        $startDate = $sourceRequest['start_date'] ?: null;
        $endDate = $startDate ? (new DateTimeImmutable($startDate))->modify('+' . (int)$sourceRequest['duration'] . ' days')->format('Y-m-d') : null;
        $pdo = db();
        try {
            $pdo->beginTransaction();
            do {
                $bookingReference = 'BK-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $check = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE booking_reference=?');
                $check->execute([$bookingReference]);
            } while ((int)$check->fetchColumn() > 0);
            $total = (float)$sourceRequest['estimate'];
            $pdo->prepare(
                "INSERT INTO bookings (booking_reference,request_id,customer_user_id,b2b_agent_id,status,payment_status,total_amount,deposit_amount,paid_amount,currency,travel_start_date,travel_end_date,payment_due_date,agent_commission_percent,agent_commission_amount,customer_notes,internal_notes,created_by_admin_id)
                 VALUES (?,?,?,?,'confirmed','unpaid',?,0,0,'EUR',?,?,NULL,?,?, '', '', ?)"
            )->execute([$bookingReference,$id,$sourceRequest['customer_user_id']?:null,$sourceRequest['b2b_agent_id']?:null,$total,$startDate,$endDate,$commissionPercent,round($total*$commissionPercent/100,2),(int)admin_user()['id']]);
            $bookingId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE tour_requests SET status='confirmed' WHERE id=?")->execute([$id]);
            $pdo->commit();
            flash('success', 'Die Anfrage wurde als Buchung übernommen.');
            redirect('admin/booking.php?id=' . $bookingId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Buchung konnte nicht erstellt werden: ' . $exception->getMessage());
        }
    } else {
        $newStatus = (string)($_POST['status'] ?? '');
        if (in_array($newStatus, ['new','contacted','quoted','confirmed','cancelled','archived'], true)) {
            db()->prepare('UPDATE tour_requests SET status=? WHERE id=?')->execute([$newStatus, $id]);
            flash('success', 'Status wurde aktualisiert.');
        }
    }
    redirect('admin/request.php?id=' . $id);
}

$stmt = db()->prepare('SELECT r.*, a.name AS admin_name, tt.title_de AS source_tour_title_de, tt.title_en AS source_tour_title_en, b.id AS booking_id, b.booking_reference, pu.name AS portal_customer_name, ag.company_name AS agent_company_name, ag.agency_code FROM tour_requests r LEFT JOIN admin_users a ON a.id=r.created_by_admin_id LEFT JOIN tour_templates tt ON tt.id=r.source_tour_template_id LEFT JOIN bookings b ON b.request_id=r.id LEFT JOIN portal_users pu ON pu.id=r.customer_user_id LEFT JOIN b2b_agents ag ON ag.id=r.b2b_agent_id WHERE r.id=?');
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) { http_response_code(404); exit('Request not found'); }
$isHotelStay = in_array($request['request_type']??'tour',['beach','ayurveda','maldives'],true);
$requestTypeLabel = match($request['request_type']??'tour'){'beach'=>'Strandurlaub','ayurveda'=>'Ayurveda-Aufenthalt','maldives'=>'Malediven-Aufenthalt',default=>'Rundreise'};
$routeStmt = db()->prepare('SELECT rd.*, d.name_de, d.name_en, d.code, d.region_de, d.region_en FROM tour_request_destinations rd JOIN destinations d ON d.id=rd.destination_id WHERE rd.request_id=? ORDER BY rd.sort_order');
$routeStmt->execute([$id]); $route = $routeStmt->fetchAll();
$itemStmt = db()->prepare('SELECT ri.*, c.type, c.name_de, c.name_en, d.name_de AS destination_name, rd.sort_order AS route_stop_order FROM tour_request_items ri JOIN catalog_items c ON c.id=ri.item_id JOIN destinations d ON d.id=c.destination_id LEFT JOIN tour_request_destinations rd ON rd.id=ri.request_destination_id WHERE ri.request_id=? ORDER BY COALESCE(rd.sort_order,9999),d.sort_order,c.type,c.sort_order');
$itemStmt->execute([$id]); $items = $itemStmt->fetchAll();
$statusLabels = ['new'=>'Neu','contacted'=>'Kontaktiert','quoted'=>'Angebot gesendet','confirmed'=>'Bestätigt','cancelled'=>'Storniert','archived'=>'Archiviert'];

$adminPage = 'requests';
$adminTitle = 'Anfrage ' . $request['reference'];
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <a class="back-admin" href="requests.php">← Alle Reiseanfragen</a>
    <div class="page-heading request-heading"><div><p><?= e($requestTypeLabel) ?> · <?= e($request['reference']) ?> · <?= e(date('d.m.Y H:i', strtotime($request['created_at']))) ?></p><h1><?= e($request['customer_name']) ?></h1><span><?= $request['admin_created'] ? 'Vom Admin erstellt' . ($request['admin_name'] ? ' · ' . e($request['admin_name']) : '') : 'Über die Website eingegangen' ?><?= $request['agent_company_name'] ? ' · B2B ' . e($request['agent_company_name']) : '' ?></span></div><div class="request-actions"><a class="secondary-button" target="_blank" href="<?= e(url(($isHotelStay?'stay-proposal.php':'itinerary.php').'?ref=' . urlencode($request['reference']))) ?>">⌁ <?= $isHotelStay?'Aufenthalt':'Route' ?> & Druckansicht</a><?php if($request['booking_id']):?><a class="secondary-button" href="booking.php?id=<?= (int)$request['booking_id'] ?>">Buchung <?= e($request['booking_reference']) ?> →</a><?php else:?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="convert_booking"><button class="secondary-button" type="submit">✓ Als Buchung übernehmen</button></form><?php endif;?><form method="post" class="status-form"><?= csrf_field() ?><input type="hidden" name="action" value="status"><select name="status"><?php foreach ($statusLabels as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($value,$request['status']) ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="primary-button" type="submit">Status speichern</button></form></div></div>
    <div class="detail-grid">
        <section class="admin-card detail-main">
            <div class="card-head"><div><h2><?= $isHotelStay?'Hotelaufenthalt':'Reiseverlauf' ?></h2><p><?= $isHotelStay?e($request['hotel_name_de']).' · ':count($route).' Orte · ' ?><?= array_sum(array_column($route,'nights')) ?> Nächte</p></div><a href="<?= e(url($isHotelStay?'stay.php?type='.$request['request_type'].'&mode=admin':'plan.php?mode=admin')) ?>">Neue Variante planen →</a></div>
            <div class="itinerary">
                <?php foreach ($route as $index=>$place): ?><article><span><?= $index+1 ?></span><div><p><?= e($place['region_de']) ?></p><h3><?= e($place['name_de']) ?></h3><small><?= e($place['name_en']) ?></small></div><strong><?= (int)$place['nights'] ?> Nächte</strong></article><?php endforeach; ?>
            </div>
            <div class="card-head sub"><div><h2>Gewählte Leistungen</h2><p>Unterkünfte, Erlebnisse und Stopps</p></div></div>
            <?php if (!$items): ?><div class="empty small">Keine zusätzlichen Leistungen gewählt.</div><?php else: ?><div class="service-list"><?php foreach ($items as $item): ?><article><span><?= e(substr(strtoupper($item['type']),0,2)) ?></span><div><strong><?= e($item['name_de']) ?></strong><small><?php if($item['route_stop_order']!==null): ?>Stopp <?= (int)$item['route_stop_order']+1 ?> · <?php endif; ?><?= e($item['destination_name']) ?> · <?= e($item['type']) ?><?php if($item['type']==='accommodation'):?> · <?= (int)$item['room_count'] ?> Zimmer<?= $item['room_type_name_de']?' · '.e($item['room_type_name_de']):'' ?><?php if($item['meal_plan_name_de']):?> · Verpflegung: <?= e($item['meal_plan_name_de']) ?><?php if((float)$item['meal_plan_supplement']>0):?> (+<?= e(money($item['meal_plan_supplement'])) ?> / Pers. / Nacht)<?php endif;?><?php endif;?><?php endif;?></small></div><b><?= e(money_precise($item['line_total']>0?$item['line_total']:$item['unit_price'])) ?><?= $item['line_total']>0?' gesamt':' / '.($item['type']==='accommodation'?'Zimmer / Nacht':stay_price_basis_label($item['price_basis'])) ?></b></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <aside class="detail-side">
            <section class="admin-card info-card"><h2>Kundendaten</h2><dl><dt>Name</dt><dd><?= e($request['customer_name']) ?></dd><dt>E-Mail</dt><dd><a href="mailto:<?= e($request['customer_email']) ?>"><?= e($request['customer_email']) ?></a></dd><dt>Telefon</dt><dd><?= e($request['customer_phone'] ?: '—') ?></dd><dt>Sprache</dt><dd><?= strtoupper(e($request['language'])) ?></dd><dt>Kundenkonto</dt><dd><?= $request['customer_user_id']?'Verknüpft':'Gastanfrage' ?></dd><?php if($request['agent_company_name']):?><dt>B2B-Agentur</dt><dd><?= e($request['agent_company_name']) ?> · <?= e($request['agency_code']) ?></dd><?php endif;?></dl></section>
            <section class="admin-card info-card"><h2>Reisedaten</h2><dl><dt>Typ</dt><dd><?= e($requestTypeLabel) ?></dd><?php if(!$isHotelStay && $request['source_tour_title_de']):?><dt>Vorlage</dt><dd><?= e($request['source_tour_title_de']) ?></dd><?php endif;?><dt>Beginn</dt><dd><?= $request['start_date'] ? e(date('d.m.Y',strtotime($request['start_date']))) : 'Flexibel' ?></dd><dt>Gesamtnächte</dt><dd><?= (int)$request['duration'] ?> Nächte</dd><?php if($isHotelStay):?><dt>Hotel</dt><dd><?= e($request['hotel_name_de']) ?></dd><dt>Erwachsene</dt><dd><?= (int)$request['adults'] ?></dd><dt>Kinder bis 12</dt><dd><?= (int)$request['children'] ?></dd><dt>Zimmer</dt><dd><?= (int)$request['rooms'] ?></dd><dt>Zimmerkosten</dt><dd><?= e(money_precise($request['room_cost'])) ?></dd><dt>Zusatzbetten</dt><dd><?= e(money_precise($request['extra_bed_cost'])) ?></dd><dt>Verpflegung</dt><dd><?= e(money_precise($request['meal_cost'])) ?></dd><dt>Zusatzleistungen</dt><dd><?= e(money_precise($request['services_cost'])) ?></dd><?php else:?><dt>Reisende</dt><dd><?= (int)$request['travelers'] ?> Personen</dd><dt>Fahrzeug</dt><dd><?= e($request['vehicle_name_de'] ?: '—') ?><?= (int)$request['vehicle_capacity'] > 0 ? ' · bis ' . (int)$request['vehicle_capacity'] . ' Reisende' : '' ?></dd><dt>Fahreroption</dt><dd><?= $request['vehicle_service_type']==='self_drive' ? 'Ohne Fahrer / Selbstfahrer' : 'Mit Fahrer' ?></dd><dt>Fahrzeugtage</dt><dd><?= (int)$request['vehicle_days'] ?></dd><dt>Tagespreis</dt><dd><?= e(money_precise($request['vehicle_daily_rate'])) ?></dd><dt>Kilometerpreis</dt><dd><?= $request['vehicle_service_type']==='self_drive' ? '—' : e(money_precise($request['vehicle_km_rate'])) . ' / km' ?></dd><dt>Fahrzeugkosten</dt><dd><?= e(money_precise($request['vehicle_cost'])) ?></dd><dt>Reiseleitung</dt><dd><?= e($request['guide_name']?:'Nicht gewählt') ?><?= $request['guide_name']?' · '.e($request['guide_languages']):'' ?></dd><?php if($request['guide_name']):?><dt>Guide-Honorar</dt><dd><?= (int)$request['guide_days'] ?> Tage × <?= e(money_precise($request['guide_daily_rate'])) ?> = <?= e(money_precise($request['guide_cost'])) ?></dd><?php endif;?><dt>Stil</dt><dd><?= e($request['travel_style'] ?: '—') ?></dd><dt>Fahrstrecke</dt><dd><?= (float)$request['route_distance_km'] > 0 ? e(number_format((float)$request['route_distance_km'], 0, ',', '.')) . ' km' : '—' ?></dd><dt>Fahrzeit</dt><dd><?php $driveMinutes=(int)$request['route_duration_minutes']; echo $driveMinutes > 0 ? e((string)intdiv($driveMinutes,60)) . ' Std. ' . e((string)($driveMinutes%60)) . ' Min.' : '—'; ?></dd><?php endif;?><?php if((float)$request['price_markup_percent']>0):?><dt>Interne Preisanpassung</dt><dd><?= e(number_format((float)$request['price_markup_percent'],2,',','.')) ?> % · <?= e(money_precise($request['price_markup_amount'])) ?> enthalten</dd><?php endif;?><dt>Schätzung</dt><dd class="big-price"><?= e(money($request['estimate'])) ?></dd></dl></section>
            <section class="admin-card info-card"><h2>Wünsche & Hinweise</h2><p><?= nl2br(e($request['notes'] ?: 'Keine besonderen Hinweise.')) ?></p></section>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
