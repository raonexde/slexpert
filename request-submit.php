<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!request_is_post()) {
    redirect('plan.php');
}
verify_csrf();

$name = trim((string)($_POST['customer_name'] ?? ''));
$email = strtolower(trim((string)($_POST['customer_email'] ?? '')));
$phone = trim((string)($_POST['customer_phone'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$language = ($_POST['language'] ?? 'de') === 'en' ? 'en' : 'de';
$travelers = max(1, min(50, (int)($_POST['travelers'] ?? 2)));
$requestedVehicleId = max(0, (int)($_POST['vehicle_id'] ?? 0));
$requestedVehicleService = ($_POST['vehicle_service_type'] ?? '') === 'self_drive' ? 'self_drive' : 'with_driver';
$requestedGuideId = max(0, (int)($_POST['guide_id'] ?? 0));
$duration = 12;
$startDate = trim((string)($_POST['start_date'] ?? '')) ?: null;
$style = substr(trim((string)($_POST['travel_style'] ?? '')), 0, 80);
$routeDistanceKm = max(0, min(5000, round((float)($_POST['route_distance_km'] ?? 0), 1)));
$routeDurationMinutes = max(0, min(20000, (int)($_POST['route_duration_minutes'] ?? 0)));
$destinations = json_decode((string)($_POST['destinations_json'] ?? '[]'), true);
$submittedItems = json_decode((string)($_POST['items_json'] ?? '[]'), true);
$adminModeRequested = ($_POST['mode'] ?? '') === 'admin';
if ($adminModeRequested) require_admin_permission('planner', true);
$isAdminMode = $adminModeRequested;
$sourceTourTemplateId = max(0, (int)($_POST['source_tour_template_id'] ?? 0));
$priceMarkupPercent = price_markup_percent();
$portalOwner = $isAdminMode ? null : portal_user();
$customerUserId = $portalOwner && $portalOwner['user_type'] === 'customer' ? (int)$portalOwner['id'] : null;
$b2bAgentId = $portalOwner && $portalOwner['user_type'] === 'agent' ? (int)$portalOwner['b2b_agent_id'] : null;
if ($sourceTourTemplateId > 0) {
    $sourceStmt = db()->prepare('SELECT id FROM tour_templates WHERE id=? AND (active=1 OR ?=1)');
    $sourceStmt->execute([$sourceTourTemplateId, $isAdminMode ? 1 : 0]);
    if (!$sourceStmt->fetchColumn()) $sourceTourTemplateId = 0;
}

$errors = [];
if ($name === '' || strlen($name) > 150) $errors[] = t('Bitte geben Sie Ihren Namen ein.', 'Please enter your name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'Please enter a valid email address.');
if (!is_array($destinations) || count($destinations) < 1) $errors[] = t('Bitte wählen Sie mindestens ein Reiseziel.', 'Please choose at least one destination.');

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('plan.php?lang=' . $language);
}

$destinationRows = [];
$destinationIds = [];
$stopUids = [];
foreach (array_slice($destinations, 0, 20) as $order => $destination) {
    $id = (int)($destination['id'] ?? 0);
    if ($id < 1) continue;
    $stopUid = trim((string)($destination['stop_uid'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $stopUid) || isset($stopUids[$stopUid])) {
        $stopUid = 'stop-' . ($order + 1);
        while (isset($stopUids[$stopUid])) $stopUid .= '-x';
    }
    $stopUids[$stopUid] = true;
    if (!in_array($id, $destinationIds, true)) $destinationIds[] = $id;
    $destinationRows[] = [
        'id' => $id,
        'stop_uid' => $stopUid,
        'nights' => max(0, min(14, (int)($destination['nights'] ?? 0))),
        'order' => $order,
    ];
}
if (!$destinationRows) {
    flash('error', t('Die ausgewählte Route ist ungültig.', 'The selected route is invalid.'));
    redirect('plan.php?lang=' . $language);
}

$placeholders = implode(',', array_fill(0, count($destinationIds), '?'));
$stmt = db()->prepare("SELECT id, price_from FROM destinations WHERE active = 1 AND id IN ($placeholders)");
$stmt->execute($destinationIds);
$validDestinations = [];
foreach ($stmt->fetchAll() as $row) $validDestinations[(int)$row['id']] = (float)$row['price_from'];
$destinationRows = array_values(array_filter($destinationRows, static fn(array $row): bool => isset($validDestinations[$row['id']])));
if (!$destinationRows) {
    flash('error', t('Die ausgewählte Route ist ungültig.', 'The selected route is invalid.'));
    redirect('plan.php?lang=' . $language);
}
$duration = max(0, min(280, array_sum(array_column($destinationRows, 'nights'))));
$destinationByStopUid = array_column($destinationRows, null, 'stop_uid');

$vehicle = null;
if ($requestedVehicleId > 0) {
    $vehicleStmt = db()->prepare('SELECT id,name_de,name_en,capacity,price_per_day_with_driver,self_drive_allowed,price_per_day_without_driver,driver_price_per_km,image_path FROM vehicles WHERE id=? AND active=1 AND capacity>=?');
    $vehicleStmt->execute([$requestedVehicleId, $travelers]);
    $vehicle = $vehicleStmt->fetch() ?: null;
}
if (!$vehicle) {
    $vehicleStmt = db()->prepare('SELECT id,name_de,name_en,capacity,price_per_day_with_driver,self_drive_allowed,price_per_day_without_driver,driver_price_per_km,image_path FROM vehicles WHERE active=1 AND capacity>=? ORDER BY capacity,sort_order,id LIMIT 1');
    $vehicleStmt->execute([$travelers]);
    $vehicle = $vehicleStmt->fetch() ?: null;
}
if (!$vehicle) {
    flash('error', t('Für diese Gruppengröße ist kein passendes Fahrzeug eingerichtet.', 'No suitable vehicle is configured for this group size.'));
    redirect('plan.php?lang=' . $language);
}
$vehicleServiceType = $requestedVehicleService === 'self_drive' && (int)$vehicle['capacity'] <= 3 && (int)$vehicle['self_drive_allowed'] === 1
    ? 'self_drive'
    : 'with_driver';
$vehicleDays = min(281, $duration + 1);
$baseVehicleDailyRate = $vehicleServiceType === 'self_drive'
    ? (float)$vehicle['price_per_day_without_driver']
    : (float)$vehicle['price_per_day_with_driver'];
$baseVehicleKmRate = $vehicleServiceType === 'with_driver' ? (float)$vehicle['driver_price_per_km'] : 0.0;
$baseVehicleCost = round(($baseVehicleDailyRate * $vehicleDays) + ($baseVehicleKmRate * $routeDistanceKm), 2);
$vehicleDailyRate = price_with_markup($baseVehicleDailyRate, $priceMarkupPercent);
$vehicleKmRate = price_with_markup($baseVehicleKmRate, $priceMarkupPercent);
$vehicleCost = round(($vehicleDailyRate * $vehicleDays) + ($vehicleKmRate * $routeDistanceKm), 2);

$guide = null;
if ($requestedGuideId > 0) {
    $guideStmt = db()->prepare("SELECT id,display_name,languages,daily_rate,photo_path FROM guides WHERE id=? AND status='approved' AND active=1");
    $guideStmt->execute([$requestedGuideId]);
    $guide = $guideStmt->fetch() ?: null;
}
$guideDays = $guide ? $vehicleDays : 0;
$baseGuideDailyRate = $guide ? (float)$guide['daily_rate'] : 0.0;
$baseGuideCost = round($baseGuideDailyRate * $guideDays, 2);
$guideDailyRate = price_with_markup($baseGuideDailyRate, $priceMarkupPercent);
$guideCost = round($guideDailyRate * $guideDays, 2);

$requestedMealPlans = [];
$requestedRoomCounts = [];
$requestedRoomTypeIds = [];
$requestedItems = [];
$cleanItemIds = [];
$submittedSelectionKeys = [];
foreach (is_array($submittedItems) ? array_slice($submittedItems, 0, 100) : [] as $submittedItem) {
    $itemId = is_array($submittedItem) ? (int)($submittedItem['id'] ?? 0) : (int)$submittedItem;
    if ($itemId < 1) continue;
    $stopUid = is_array($submittedItem) ? trim((string)($submittedItem['stop_uid'] ?? '')) : '';
    if ($stopUid !== '' && !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $stopUid)) continue;
    $selectionKey = $stopUid . ':' . $itemId;
    if (isset($submittedSelectionKeys[$selectionKey])) continue;
    $submittedSelectionKeys[$selectionKey] = true;
    $requestedItems[] = ['id' => $itemId, 'stop_uid' => $stopUid, 'selection_key' => $selectionKey];
    if (!in_array($itemId, $cleanItemIds, true)) $cleanItemIds[] = $itemId;
    if (is_array($submittedItem)) {
        $requestedMealPlans[$selectionKey] = substr(trim((string)($submittedItem['meal_plan_code'] ?? '')), 0, 40);
        $requestedRoomCounts[$selectionKey] = (int)($submittedItem['rooms'] ?? 0);
        $requestedRoomTypeIds[$selectionKey] = max(0,(int)($submittedItem['room_type_id'] ?? 0));
    }
}
$validItems = [];
if ($cleanItemIds) {
    $itemPlaceholders = implode(',', array_fill(0, count($cleanItemIds), '?'));
    $itemStmt = db()->prepare("SELECT id, destination_id, type, price_per_person, price_basis FROM catalog_items WHERE active = 1 AND id IN ($itemPlaceholders)");
    $itemStmt->execute($cleanItemIds);
    $catalogById = array_column($itemStmt->fetchAll(), null, 'id');
    $accommodationStops = [];
    $validSelectionKeys = [];
    foreach ($requestedItems as $requestedItem) {
        $row = $catalogById[$requestedItem['id']] ?? null;
        if (!$row) continue;
        $stopUid = $requestedItem['stop_uid'];
        if ($stopUid === '') {
            foreach ($destinationRows as $destinationRow) {
                if ((int)$destinationRow['id'] === (int)$row['destination_id']) {
                    $stopUid = $destinationRow['stop_uid'];
                    break;
                }
            }
        }
        if ($stopUid === '' || !isset($destinationByStopUid[$stopUid])) continue;
        if ((int)$destinationByStopUid[$stopUid]['id'] !== (int)$row['destination_id']) continue;
        $selectionKey = $stopUid . ':' . (int)$row['id'];
        if ($selectionKey !== $requestedItem['selection_key']) {
            if (isset($requestedMealPlans[$requestedItem['selection_key']])) {
                $requestedMealPlans[$selectionKey] = $requestedMealPlans[$requestedItem['selection_key']];
            }
            if (isset($requestedRoomCounts[$requestedItem['selection_key']])) {
                $requestedRoomCounts[$selectionKey] = $requestedRoomCounts[$requestedItem['selection_key']];
            }
            if (isset($requestedRoomTypeIds[$requestedItem['selection_key']])) {
                $requestedRoomTypeIds[$selectionKey] = $requestedRoomTypeIds[$requestedItem['selection_key']];
            }
        }
        if (isset($validSelectionKeys[$selectionKey])) continue;
        $validSelectionKeys[$selectionKey] = true;
        if ($row['type'] === 'accommodation') {
            if (isset($accommodationStops[$stopUid])) continue;
            $accommodationStops[$stopUid] = true;
        }
        $row['stop_uid'] = $stopUid;
        $row['selection_key'] = $selectionKey;
        $validItems[] = $row;
    }
}

$mealPlansByItem = [];
if ($cleanItemIds) {
    $mealPlanStmt = db()->prepare("SELECT * FROM accommodation_meal_plans WHERE active=1 AND catalog_item_id IN ($itemPlaceholders) ORDER BY catalog_item_id,sort_order,id");
    $mealPlanStmt->execute($cleanItemIds);
    foreach ($mealPlanStmt->fetchAll() as $mealPlan) $mealPlansByItem[(int)$mealPlan['catalog_item_id']][$mealPlan['code']] = $mealPlan;
}
foreach ($validItems as &$validItem) {
    $validItem['room_count'] = 0;
    $validItem['meal_plan_code'] = '';
    $validItem['meal_plan_name_de'] = '';
    $validItem['meal_plan_name_en'] = '';
    $validItem['meal_plan_supplement'] = 0.0;
    $validItem['room_type_id'] = null;
    $validItem['room_type_code'] = '';
    $validItem['room_type_name_de'] = '';
    $validItem['room_type_name_en'] = '';
    $validItem['line_total'] = 0.0;
    if ($validItem['type'] !== 'accommodation') continue;
    $selectionKey = $validItem['selection_key'];
    $requestedRoomTypeId = $requestedRoomTypeIds[$selectionKey] ?? 0;
    $roomTypeStmt = db()->prepare('SELECT * FROM hotel_room_types WHERE hotel_id=? AND active=1 AND (?=0 OR id=?) ORDER BY featured DESC,sort_order,id LIMIT 1');
    $roomTypeStmt->execute([(int)$validItem['id'],$requestedRoomTypeId,$requestedRoomTypeId]);
    $roomType = $roomTypeStmt->fetch() ?: null;
    if ($roomType) {
        $nights=(int)($destinationByStopUid[$validItem['stop_uid']]['nights']??0);
        $roomRate=(float)$roomType['base_rate_per_room_night'];
        if($startDate){
            $arrivalOffset=0;
            foreach($destinationRows as $routeStop){if($routeStop['stop_uid']===$validItem['stop_uid'])break;$arrivalOffset+=(int)$routeStop['nights'];}
            $roomDate=(new DateTimeImmutable($startDate))->modify('+'.$arrivalOffset.' days')->format('Y-m-d');
            $rateStmt=db()->prepare('SELECT price_per_room_night FROM hotel_room_rates WHERE room_type_id=? AND active=1 AND minimum_nights<=? AND (valid_from IS NULL OR valid_from<=?) AND (valid_to IS NULL OR valid_to>=?) ORDER BY valid_from DESC,sort_order,id LIMIT 1');
            $rateStmt->execute([(int)$roomType['id'],$nights,$roomDate,$roomDate]);
            $seasonal=$rateStmt->fetchColumn();if($seasonal!==false)$roomRate=(float)$seasonal;
        }
        $validItem['price_per_person']=$roomRate;
        $validItem['room_type_id']=(int)$roomType['id'];
        $validItem['room_type_code']=$roomType['code'];
        $validItem['room_type_name_de']=$roomType['name_de'];
        $validItem['room_type_name_en']=$roomType['name_en'];
        $standardGuests=max(1,(int)$roomType['standard_guests']);
    } else {
        $standardGuests=2;
    }
    $minimumRooms = max(1, (int)ceil($travelers / $standardGuests));
    $validItem['room_count'] = max($minimumRooms, min($travelers, $requestedRoomCounts[$selectionKey] ?? $minimumRooms));
    if (empty($mealPlansByItem[(int)$validItem['id']])) continue;
    $availablePlans = $mealPlansByItem[(int)$validItem['id']];
    $requestedCode = $requestedMealPlans[$selectionKey] ?? '';
    $selectedPlan = $availablePlans[$requestedCode] ?? reset($availablePlans);
    $validItem['meal_plan_code'] = $selectedPlan['code'];
    $validItem['meal_plan_name_de'] = $selectedPlan['name_de'];
    $validItem['meal_plan_name_en'] = $selectedPlan['name_en'];
    $validItem['meal_plan_supplement'] = (float)$selectedPlan['supplement_per_person_night'];
}
unset($validItem);

foreach ($validItems as &$validItem) {
    $validItem['base_price_per_person'] = (float)$validItem['price_per_person'];
    $validItem['base_meal_plan_supplement'] = (float)$validItem['meal_plan_supplement'];
    $validItem['price_per_person'] = price_with_markup($validItem['base_price_per_person'], $priceMarkupPercent);
    $validItem['meal_plan_supplement'] = price_with_markup($validItem['base_meal_plan_supplement'], $priceMarkupPercent);
}
unset($validItem);

$roomCost = 0.0;
$mealCost = 0.0;
$servicesCost = 0.0;
$baseRoomCost = 0.0;
$baseMealCost = 0.0;
$baseServicesCost = 0.0;
foreach ($validItems as &$row) {
    $nights = (int)($destinationByStopUid[$row['stop_uid']]['nights'] ?? 0);
    if ($row['type'] === 'accommodation') {
        $itemRoomCost = (float)$row['price_per_person'] * (int)$row['room_count'] * $nights;
        $itemMealCost = (float)$row['meal_plan_supplement'] * $travelers * $nights;
        $baseRoomCost += (float)$row['base_price_per_person'] * (int)$row['room_count'] * $nights;
        $baseMealCost += (float)$row['base_meal_plan_supplement'] * $travelers * $nights;
        $roomCost += $itemRoomCost;
        $mealCost += $itemMealCost;
        $row['line_total'] = round($itemRoomCost + $itemMealCost,2);
    } elseif ($row['price_basis'] === 'per_person_night') {
        $row['line_total'] = round((float)$row['price_per_person'] * $travelers * $nights,2);
        $servicesCost += $row['line_total'];
        $baseServicesCost += (float)$row['base_price_per_person'] * $travelers * $nights;
    } elseif ($row['price_basis'] === 'per_booking') {
        $row['line_total'] = round((float)$row['price_per_person'],2);
        $servicesCost += $row['line_total'];
        $baseServicesCost += (float)$row['base_price_per_person'];
    } elseif (in_array($row['price_basis'], ['free','on_request'], true)) {
        $row['line_total'] = 0.0;
    } else {
        $row['line_total'] = round((float)$row['price_per_person'] * $travelers,2);
        $servicesCost += $row['line_total'];
        $baseServicesCost += (float)$row['base_price_per_person'] * $travelers;
    }
}
unset($row);
$estimate = round($roomCost + $mealCost + $servicesCost + $vehicleCost + $guideCost, 2);
$baseEstimate = $baseRoomCost + $baseMealCost + $baseServicesCost + $baseVehicleCost + $baseGuideCost;
$priceMarkupAmount = round($estimate - $baseEstimate, 2);

$pdo = db();
try {
    $pdo->beginTransaction();
    do {
        $reference = 'SL-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $check = $pdo->prepare('SELECT COUNT(*) FROM tour_requests WHERE reference = ?');
        $check->execute([$reference]);
    } while ((int)$check->fetchColumn() > 0);

    $requestInsert = $pdo->prepare(
        "INSERT INTO tour_requests
        (reference,customer_user_id,b2b_agent_id,customer_name,customer_email,customer_phone,language,request_type,source_tour_template_id,travelers,adults,children,rooms,room_cost,extra_bed_cost,meal_cost,services_cost,price_markup_percent,price_markup_amount,vehicle_id,vehicle_name_de,vehicle_name_en,vehicle_capacity,vehicle_service_type,vehicle_days,vehicle_daily_rate,vehicle_km_rate,vehicle_cost,vehicle_image_path,guide_id,guide_name,guide_photo_path,guide_languages,guide_days,guide_daily_rate,guide_cost,start_date,duration,travel_style,notes,estimate,route_distance_km,route_duration_minutes,status,admin_created,created_by_admin_id)
        VALUES
        (:reference,:customer_user_id,:b2b_agent_id,:customer_name,:customer_email,:customer_phone,:language,'tour',:source_tour_template_id,:travelers,:adults,0,0,:room_cost,0,:meal_cost,:services_cost,:price_markup_percent,:price_markup_amount,:vehicle_id,:vehicle_name_de,:vehicle_name_en,:vehicle_capacity,:vehicle_service_type,:vehicle_days,:vehicle_daily_rate,:vehicle_km_rate,:vehicle_cost,:vehicle_image_path,:guide_id,:guide_name,:guide_photo_path,:guide_languages,:guide_days,:guide_daily_rate,:guide_cost,:start_date,:duration,:travel_style,:notes,:estimate,:route_distance_km,:route_duration_minutes,:status,:admin_created,:created_by_admin_id)"
    );
    $requestInsert->execute([
        'reference'=>$reference,'customer_user_id'=>$customerUserId,'b2b_agent_id'=>$b2bAgentId,'customer_name'=>$name,'customer_email'=>$email,'customer_phone'=>substr($phone,0,80),'language'=>$language,'source_tour_template_id'=>$sourceTourTemplateId?:null,'travelers'=>$travelers,'adults'=>$travelers,'room_cost'=>$roomCost,'meal_cost'=>$mealCost,'services_cost'=>$servicesCost,'price_markup_percent'=>$priceMarkupPercent,'price_markup_amount'=>$priceMarkupAmount,
        'vehicle_id'=>(int)$vehicle['id'],'vehicle_name_de'=>$vehicle['name_de'],'vehicle_name_en'=>$vehicle['name_en'],'vehicle_capacity'=>(int)$vehicle['capacity'],'vehicle_service_type'=>$vehicleServiceType,'vehicle_days'=>$vehicleDays,'vehicle_daily_rate'=>$vehicleDailyRate,'vehicle_km_rate'=>$vehicleKmRate,'vehicle_cost'=>$vehicleCost,'vehicle_image_path'=>$vehicle['image_path']?:null,
        'guide_id'=>$guide?(int)$guide['id']:null,'guide_name'=>$guide?$guide['display_name']:'','guide_photo_path'=>$guide&&$guide['photo_path']?$guide['photo_path']:null,'guide_languages'=>$guide?$guide['languages']:'','guide_days'=>$guideDays,'guide_daily_rate'=>$guideDailyRate,'guide_cost'=>$guideCost,
        'start_date'=>$startDate,'duration'=>$duration,'travel_style'=>$style,'notes'=>substr($notes,0,4000),'estimate'=>$estimate,'route_distance_km'=>$routeDistanceKm,'route_duration_minutes'=>$routeDurationMinutes,'status'=>$isAdminMode?'contacted':'new','admin_created'=>$isAdminMode?1:0,'created_by_admin_id'=>$isAdminMode?admin_user()['id']:null,
    ]);
    $requestId = (int)$pdo->lastInsertId();

    $routeInsert = $pdo->prepare('INSERT INTO tour_request_destinations (request_id, destination_id, nights, sort_order) VALUES (?, ?, ?, ?)');
    $routeRowIds = [];
    foreach ($destinationRows as $row) {
        $routeInsert->execute([$requestId, $row['id'], $row['nights'], $row['order']]);
        $routeRowIds[$row['stop_uid']] = (int)$pdo->lastInsertId();
    }

    $itemInsert = $pdo->prepare('INSERT INTO tour_request_items (request_id,request_destination_id,item_id,quantity,room_count,room_type_id,room_type_code,room_type_name_de,room_type_name_en,unit_price,price_basis,line_total,adult_quantity,child_quantity,meal_plan_code,meal_plan_name_de,meal_plan_name_en,meal_plan_supplement) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($validItems as $row) {
        $itemInsert->execute([$requestId,$routeRowIds[$row['stop_uid']]??null,$row['id'],$travelers,$row['room_count'],$row['room_type_id'],$row['room_type_code'],$row['room_type_name_de'],$row['room_type_name_en'],$row['price_per_person'],$row['type']==='accommodation'?'per_room_night':$row['price_basis'],$row['line_total'],$travelers,0,$row['meal_plan_code'],$row['meal_plan_name_de'],$row['meal_plan_name_en'],$row['meal_plan_supplement']]);
    }
    $pdo->commit();

    $_SESSION['submitted_reference'] = $reference;
    if ($isAdminMode) {
        flash('success', t('Die Kundenreise wurde gespeichert.', 'The client journey was saved.'));
        if (admin_can('requests')) redirect('admin/request.php?id=' . $requestId);
        redirect('itinerary.php?ref=' . urlencode($reference));
    }
    redirect('success.php?ref=' . urlencode($reference) . '&lang=' . $language);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', t('Die Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.', 'The enquiry could not be saved. Please try again.'));
    redirect('plan.php?lang=' . $language);
}
