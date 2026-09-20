<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!request_is_post()) redirect('stay.php?type=beach');
verify_csrf();

$requestType = in_array($_POST['request_type'] ?? '', ['beach','ayurveda','maldives'], true) ? (string)$_POST['request_type'] : 'beach';
$language = ($_POST['language'] ?? '') === 'en' ? 'en' : 'de';
$returnUrl = 'stay.php?type=' . $requestType . '&lang=' . $language;
$name = trim((string)($_POST['customer_name'] ?? ''));
$email = strtolower(trim((string)($_POST['customer_email'] ?? '')));
$phone = substr(trim((string)($_POST['customer_phone'] ?? '')), 0, 80);
$notes = substr(trim((string)($_POST['notes'] ?? '')), 0, 4000);
$hotelId = max(0, (int)($_POST['hotel_id'] ?? 0));
$adults = max(1, min(50, (int)($_POST['adults'] ?? 2)));
$children = max(0, min(20, (int)($_POST['children'] ?? 0)));
$rooms = max(1, min(20, (int)($_POST['rooms'] ?? 1)));
$nights = max(1, min(90, (int)($_POST['nights'] ?? ($requestType === 'ayurveda' ? 7 : 1))));
$startDate = trim((string)($_POST['start_date'] ?? '')) ?: null;
$mealPlanCode = substr(trim((string)($_POST['meal_plan_code'] ?? '')), 0, 40);
$submittedServices = json_decode((string)($_POST['services_json'] ?? '[]'), true);
$isAdminMode = ($_POST['mode'] ?? '') === 'admin' && admin_user();
$portalOwner = $isAdminMode ? null : portal_user();
$customerUserId = $portalOwner && $portalOwner['user_type'] === 'customer' ? (int)$portalOwner['id'] : null;
$b2bAgentId = $portalOwner && $portalOwner['user_type'] === 'agent' ? (int)$portalOwner['b2b_agent_id'] : null;

$errors = [];
if ($name === '' || strlen($name) > 150) $errors[] = t('Bitte geben Sie Ihren Namen ein.','Please enter your name.');
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[] = t('Bitte geben Sie eine gültige E-Mail-Adresse ein.','Please enter a valid email address.');
if ($hotelId < 1) $errors[] = t('Bitte wählen Sie ein Hotel.','Please choose a hotel.');
if ($adults + $children > 50) $errors[] = t('Maximal 50 Reisende sind möglich.','A maximum of 50 travellers is supported.');
if ($startDate !== null) {
    $date = DateTimeImmutable::createFromFormat('Y-m-d',$startDate);
    if (!$date || $date->format('Y-m-d') !== $startDate) $errors[] = t('Das Anreisedatum ist ungültig.','The arrival date is invalid.');
}
if ($errors) {
    flash('error',implode(' ',$errors));
    redirect($returnUrl);
}

$availabilityColumn = match ($requestType) { 'ayurveda'=>'ayurveda_available', 'maldives'=>'maldives_available', default=>'beach_available' };
$countryCode = $requestType === 'maldives' ? 'MV' : 'LK';
$hotelStmt = db()->prepare(
    "SELECT c.*,d.name_de AS destination_name_de,d.name_en AS destination_name_en
     FROM catalog_items c JOIN destinations d ON d.id=c.destination_id
     WHERE c.id=? AND c.type='accommodation' AND c.active=1 AND d.active=1 AND d.country_code=? AND c.$availabilityColumn=1"
);
$hotelStmt->execute([$hotelId,$countryCode]);
$hotel = $hotelStmt->fetch();
if (!$hotel) {
    flash('error',t('Das ausgewählte Hotel ist nicht verfügbar.','The selected hotel is not available.'));
    redirect($returnUrl);
}

if ($requestType === 'ayurveda') {
    $allowedNights = array_values(array_filter(array_map('intval',explode(',',(string)$hotel['ayurveda_night_options'])),static fn(int $night):bool=>$night>0&&$night<=90));
    if (!$allowedNights) $allowedNights = [7,14,21,28];
    if (!in_array($nights,$allowedNights,true)) {
        flash('error',t('Bitte wählen Sie eine gültige Ayurveda-Aufenthaltsdauer.','Please choose a valid Ayurveda stay length.'));
        redirect($returnUrl);
    }
    $mealPlanCode = 'all_inclusive';
}

$mealStmt = db()->prepare('SELECT * FROM accommodation_meal_plans WHERE catalog_item_id=? AND code=? AND active=1 LIMIT 1');
$mealStmt->execute([$hotelId,$mealPlanCode]);
$mealPlan = $mealStmt->fetch() ?: null;
if ($requestType === 'ayurveda' && !$mealPlan) {
    flash('error',t('Für dieses Ayurveda-Hotel ist noch kein aktiver All-inclusive-Preis eingerichtet.','This Ayurveda hotel does not yet have an active all-inclusive rate.'));
    redirect($returnUrl);
}

$serviceIds = [];
foreach (is_array($submittedServices)?array_slice($submittedServices,0,50):[] as $serviceId) {
    $serviceId = (int)$serviceId;
    if ($serviceId > 0 && !in_array($serviceId,$serviceIds,true)) $serviceIds[] = $serviceId;
}
$services = [];
if ($serviceIds) {
    $placeholders = implode(',',array_fill(0,count($serviceIds),'?'));
    $serviceStmt = db()->prepare(
        "SELECT * FROM catalog_items
         WHERE id IN ($placeholders) AND destination_id=? AND type IN ('sight','activity','restaurant','spice_garden','shop','service')
           AND active=1 AND $availabilityColumn=1"
    );
    $serviceStmt->execute(array_merge($serviceIds,[(int)$hotel['destination_id']]));
    $validById = [];
    foreach ($serviceStmt->fetchAll() as $service) $validById[(int)$service['id']] = $service;
    foreach ($serviceIds as $serviceId) if (isset($validById[$serviceId])) $services[] = $validById[$serviceId];
}

$priceMarkupPercent = price_markup_percent();
$basePricing = calculate_stay_pricing($hotel,$mealPlan,$services,$adults,$children,$rooms,$nights);
$pricedHotel = $hotel;
$pricedHotel['price_per_person'] = price_with_markup((float)$hotel['price_per_person'], $priceMarkupPercent);
$pricedMealPlan = $mealPlan;
if ($pricedMealPlan) $pricedMealPlan['supplement_per_person_night'] = price_with_markup((float)$pricedMealPlan['supplement_per_person_night'], $priceMarkupPercent);
$pricedServices = array_map(static function(array $service) use ($priceMarkupPercent): array {
    $service['price_per_person'] = price_with_markup((float)$service['price_per_person'], $priceMarkupPercent);
    return $service;
}, $services);
$pricing = calculate_stay_pricing($pricedHotel,$pricedMealPlan,$pricedServices,$adults,$children,$rooms,$nights);
if (!$pricing['valid_occupancy']) {
    flash('error',t('Für diese Gästezahl benötigen Sie mehr Zimmer.','More rooms are required for this number of guests.'));
    redirect($returnUrl);
}
$priceMarkupAmount = round($pricing['total'] - $basePricing['total'], 2);
$estimate = $pricing['total'];

$pdo = db();
try {
    $pdo->beginTransaction();
    do {
        $reference = match($requestType){'ayurveda'=>'AY-','maldives'=>'MV-',default=>'BV-'} . date('ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $check = $pdo->prepare('SELECT COUNT(*) FROM tour_requests WHERE reference=?');
        $check->execute([$reference]);
    } while ((int)$check->fetchColumn() > 0);

    $requestInsert = $pdo->prepare(
        'INSERT INTO tour_requests
         (reference,customer_user_id,b2b_agent_id,customer_name,customer_email,customer_phone,language,request_type,travelers,adults,children,rooms,hotel_name_de,hotel_name_en,room_cost,extra_bed_cost,meal_cost,services_cost,price_markup_percent,price_markup_amount,vehicle_id,vehicle_name_de,vehicle_name_en,vehicle_capacity,vehicle_service_type,vehicle_days,vehicle_daily_rate,vehicle_km_rate,vehicle_cost,start_date,duration,travel_style,notes,estimate,route_distance_km,route_duration_minutes,status,admin_created,created_by_admin_id)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,\'\',\'\',0,\'with_driver\',0,0,0,0,?,?,?,?,?,0,0,?,?,?)'
    );
    $requestInsert->execute([
        $reference,$customerUserId,$b2bAgentId,$name,$email,$phone,$language,$requestType,$adults+$children,$adults,$children,$rooms,
        $hotel['name_de'],$hotel['name_en'],$pricing['room_cost'],$pricing['extra_bed_cost'],$pricing['meal_cost'],$pricing['services_cost'],$priceMarkupPercent,$priceMarkupAmount,
        $startDate,$nights,$requestType,$notes,$estimate,$isAdminMode?'contacted':'new',$isAdminMode?1:0,$isAdminMode?(int)admin_user()['id']:null,
    ]);
    $requestId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tour_request_destinations (request_id,destination_id,nights,sort_order) VALUES (?,?,?,0)')->execute([$requestId,(int)$hotel['destination_id'],$nights]);
    $requestDestinationId = (int)$pdo->lastInsertId();

    $itemInsert = $pdo->prepare(
        'INSERT INTO tour_request_items
         (request_id,request_destination_id,item_id,quantity,room_count,unit_price,price_basis,line_total,adult_quantity,child_quantity,extra_bed_adults,extra_bed_children,extra_bed_percent,child_percent,meal_plan_code,meal_plan_name_de,meal_plan_name_en,meal_plan_supplement)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $itemInsert->execute([
        $requestId,$requestDestinationId,$hotelId,$adults+$children,$rooms,$pricedHotel['price_per_person'],'per_room_night',
        $pricing['room_cost']+$pricing['extra_bed_cost']+$pricing['meal_cost'],$adults,$children,
        $pricing['extra_bed_adults'],$pricing['extra_bed_children'],$hotel['extra_bed_percent'],$hotel['child_percent'],
        $pricedMealPlan['code']??'',$pricedMealPlan['name_de']??'',$pricedMealPlan['name_en']??'',$pricedMealPlan['supplement_per_person_night']??0,
    ]);
    foreach ($pricing['services'] as $service) {
        $itemInsert->execute([
            $requestId,$requestDestinationId,(int)$service['id'],$adults+$children,0,$service['price_per_person'],$service['price_basis'],
            $service['line_total'],$adults,$children,0,0,0,$hotel['child_percent'],'','','',0,
        ]);
    }
    $pdo->commit();
    $_SESSION['submitted_reference'] = $reference;
    if ($isAdminMode) {
        flash('success',t('Der Hotelaufenthalt wurde gespeichert.','The hotel stay was saved.'));
        redirect('admin/request.php?id='.$requestId);
    }
    redirect('success.php?ref='.urlencode($reference).'&lang='.$language);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error',t('Die Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.','The enquiry could not be saved. Please try again.'));
    redirect($returnUrl);
}
