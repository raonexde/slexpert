<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$destinations = db()->query("SELECT * FROM destinations WHERE active = 1 AND country_code='LK' ORDER BY sort_order, name_de")->fetchAll();
$items = db()->query(
    "SELECT c.*, d.code AS destination_code, d.accent AS destination_accent,
            cc.name_de AS classification_de, cc.name_en AS classification_en
     FROM catalog_items c JOIN destinations d ON d.id = c.destination_id
     LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id
     WHERE c.active = 1 AND d.active = 1 AND d.country_code='LK'
     ORDER BY c.destination_id, c.type, c.sort_order, c.name_de"
)->fetchAll();
$mealPlanRows = db()->query(
    "SELECT mp.* FROM accommodation_meal_plans mp
     JOIN catalog_items c ON c.id=mp.catalog_item_id
     WHERE mp.active=1 AND c.active=1 AND c.type='accommodation'
     ORDER BY mp.catalog_item_id,mp.sort_order,mp.id"
)->fetchAll();
$mealPlansByItem = [];
foreach ($mealPlanRows as $mealPlan) $mealPlansByItem[(int)$mealPlan['catalog_item_id']][] = $mealPlan;
$vehicles = db()->query('SELECT * FROM vehicles WHERE active = 1 ORDER BY capacity, sort_order, id')->fetchAll();
$guides = db()->query("SELECT id,display_name,languages,specializations,driver_guide,daily_rate,photo_path FROM guides WHERE status='approved' AND active=1 ORDER BY featured DESC,sort_order,display_name")->fetchAll();
$settings = db()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_maps_api_key', 'google_maps_map_id')")->fetchAll(PDO::FETCH_KEY_PAIR);

$isAdminMode = (($_GET['mode'] ?? '') === 'admin') && admin_user();
$portalFormUser = portal_user();
$customerFormUser = $portalFormUser && $portalFormUser['user_type'] === 'customer' ? $portalFormUser : null;
$requestedTourId = max(0, (int)($_GET['tour'] ?? 0));
$sourceTour = null;
$initialTourStops = [];
if ($requestedTourId > 0) {
    $tourStmt = db()->prepare('SELECT * FROM tour_templates WHERE id=? AND (active=1 OR ?=1) LIMIT 1');
    $tourStmt->execute([$requestedTourId, $isAdminMode ? 1 : 0]);
    $sourceTour = $tourStmt->fetch() ?: null;
    if ($sourceTour) {
        $templateStopStmt = db()->prepare('SELECT s.* FROM tour_template_stops s WHERE s.tour_template_id=? ORDER BY s.sort_order,s.id');
        $templateStopStmt->execute([$sourceTour['id']]);
        $initialTourStops = $templateStopStmt->fetchAll();
        if ($initialTourStops) {
            $templateStopIds = array_column($initialTourStops, 'id');
            $templatePlaceholders = implode(',', array_fill(0, count($templateStopIds), '?'));
            $templateItemStmt = db()->prepare("SELECT tour_template_stop_id,catalog_item_id FROM tour_template_stop_items WHERE tour_template_stop_id IN ($templatePlaceholders) ORDER BY sort_order,id");
            $templateItemStmt->execute($templateStopIds);
            $templateItemsByStop = [];
            foreach ($templateItemStmt->fetchAll() as $templateItem) $templateItemsByStop[(int)$templateItem['tour_template_stop_id']][] = (int)$templateItem['catalog_item_id'];
            foreach ($initialTourStops as &$initialTourStop) $initialTourStop['item_ids'] = $templateItemsByStop[(int)$initialTourStop['id']] ?? [];
            unset($initialTourStop);
        }
    }
}
$requestedDestination = (int)($_GET['destination'] ?? 0);
$activeDestinationIds = array_map(static fn(array $destination): int => (int)$destination['id'], $destinations);
$initialDestination = in_array($requestedDestination, $activeDestinationIds, true)
    ? $requestedDestination
    : 0;
$initialStyle = (string)($_GET['style'] ?? 'culture');
if ($sourceTour) $initialStyle = match ($sourceTour['category']) { 'sport','summer','winter' => 'nature', 'ayurveda' => 'ayurveda', 'family' => 'family', default => 'culture' };
$pageTitle = $sourceTour ? t('Reise anpassen: ', 'Customise tour: ') . $sourceTour['title_'.lang()] : t('Persönliche Reise planen', 'Plan your personal journey');
$bodyClass = 'planner-page';
require __DIR__ . '/includes/public-header.php';

$plannerData = [
    'language' => lang(),
    'initialDestination' => $sourceTour ? 0 : $initialDestination,
    'initialTour' => $sourceTour ? [
        'id' => (int)$sourceTour['id'],
        'title_de' => $sourceTour['title_de'],
        'title_en' => $sourceTour['title_en'],
        'stops' => array_map(static fn(array $stop): array => [
            'destination_id' => (int)$stop['destination_id'],
            'nights' => (int)$stop['nights'],
            'item_ids' => array_map('intval', $stop['item_ids'] ?? []),
        ], $initialTourStops),
    ] : null,
    'destinations' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'], 'code' => $row['code'],
            'name_de' => $row['name_de'], 'name_en' => $row['name_en'],
            'region_de' => $row['region_de'], 'region_en' => $row['region_en'],
            'nights' => (int)$row['default_nights'], 'price' => price_with_markup((float)$row['price_from']),
            'accent' => $row['accent'], 'image' => $row['image_path'] ? url($row['image_path']) : '',
            'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
            'google_place_id' => $row['google_place_id'] ?? '',
        ];
    }, $destinations),
    'items' => array_map(static function (array $row) use ($mealPlansByItem): array {
        return [
            'id' => (int)$row['id'], 'destination_id' => (int)$row['destination_id'],
            'type' => $row['type'], 'name_de' => $row['name_de'], 'name_en' => $row['name_en'],
            'description_de' => $row['description_de'], 'description_en' => $row['description_en'],
            'meta_de' => $row['meta_de'], 'meta_en' => $row['meta_en'],
            'classification_de' => $row['classification_de'] ?? '', 'classification_en' => $row['classification_en'] ?? '',
            'star_rating' => (int)$row['star_rating'], 'market_segment' => $row['market_segment'],
            'facilities_de' => $row['facilities_de'], 'facilities_en' => $row['facilities_en'],
            'opening_hours_de' => $row['opening_hours_de'], 'opening_hours_en' => $row['opening_hours_en'],
            'duration_minutes' => (int)$row['duration_minutes'], 'booking_required' => (bool)$row['booking_required'],
            'price' => price_with_markup((float)$row['price_per_person']), 'price_basis'=>$row['price_basis'], 'featured' => (bool)$row['featured'],
            'code' => $row['destination_code'], 'accent' => $row['destination_accent'],
            'image' => $row['image_path'] ? url($row['image_path']) : '',
            'meal_plans' => array_map(static fn(array $plan): array => [
                'code'=>$plan['code'],'name_de'=>$plan['name_de'],'name_en'=>$plan['name_en'],
                'supplement'=>price_with_markup((float)$plan['supplement_per_person_night']),
            ], $mealPlansByItem[(int)$row['id']] ?? []),
        ];
    }, $items),
    'maps' => [
        'apiKey' => trim((string)($settings['google_maps_api_key'] ?? '')),
        'mapId' => trim((string)($settings['google_maps_map_id'] ?? '')) ?: 'DEMO_MAP_ID',
    ],
    'vehicles' => array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'name_de' => $row['name_de'],
        'name_en' => $row['name_en'],
        'capacity' => (int)$row['capacity'],
        'price_per_day_with_driver' => price_with_markup((float)$row['price_per_day_with_driver']),
        'self_drive_allowed' => (bool)$row['self_drive_allowed'],
        'price_per_day_without_driver' => price_with_markup((float)$row['price_per_day_without_driver']),
        'driver_price_per_km' => price_with_markup((float)$row['driver_price_per_km']),
        'image' => $row['image_path'] ? url($row['image_path']) : '',
    ], $vehicles),
    'guides' => array_map(static fn(array $row): array => [
        'id'=>(int)$row['id'],'name'=>$row['display_name'],'languages'=>$row['languages'],
        'specializations'=>$row['specializations'],'driver_guide'=>(bool)$row['driver_guide'],
        'daily_rate'=>price_with_markup((float)$row['daily_rate']),
        'image'=>$row['photo_path']?url($row['photo_path']):'',
    ], $guides),
    'labels' => [
        'nights' => t('Nächte', 'nights'), 'night' => t('Nacht', 'night'),
        'choose' => t('Auswählen', 'Choose'), 'chosen' => t('Gewählt', 'Chosen'),
        'included' => t('Inklusive', 'Included'), 'person' => t('Pers.', 'person'),
        'free' => t('Kostenfrei', 'Free'), 'onRequest' => t('Preis auf Anfrage', 'Price on request'),
        'bookingRequired' => t('Reservierung erforderlich', 'Reservation required'),
        'empty' => t('Für diese Kategorie sind noch keine Angebote hinterlegt.', 'No options have been added to this category yet.'),
        'from' => t('ab', 'from'),
        'mapTitle' => t('Ihre Fahrroute', 'Your driving route'),
        'mapHelp' => t('Reiseziele auswählen oder ihre Reihenfolge ändern.', 'Choose destinations or change their order.'),
        'mapMissingKey' => t('Google Maps ist noch nicht eingerichtet. Der Administrator kann den API-Schlüssel unter Einstellungen hinzufügen.', 'Google Maps is not configured yet. The administrator can add the API key under Settings.'),
        'mapMissingCoordinates' => t('Für mindestens ein Reiseziel fehlen Kartenkoordinaten.', 'Map coordinates are missing for at least one destination.'),
        'mapLoading' => t('Fahrroute wird berechnet …', 'Calculating driving route…'),
        'mapError' => t('Die Fahrroute konnte nicht berechnet werden.', 'The driving route could not be calculated.'),
        'distance' => t('Fahrstrecke', 'Driving distance'),
        'driveTime' => t('Fahrzeit', 'Driving time'),
        'hours' => t('Std.', 'hr'),
        'minutes' => t('Min.', 'min'),
        'moveUp' => t('Früher in der Route', 'Move earlier in route'),
        'moveDown' => t('Später in der Route', 'Move later in route'),
        'addAgain' => t('Diesen Ort erneut am Routenende hinzufügen', 'Add this place again at the end of the route'),
        'addAgainShort' => t('Nochmals am Routenende', 'Add again at route end'),
        'alreadySelectedAddAgain' => t('Bereits gewählt · nochmals hinzufügen', 'Already selected · add again'),
        'addStop' => t('Ort zur Route hinzufügen', 'Add place to route'),
        'removeStop' => t('Diesen Routenstopp entfernen', 'Remove this route stop'),
        'printRoute' => t('Route drucken', 'Print route'),
        'maxStops' => t('Eine Route kann maximal 20 Reiseziele enthalten.', 'A route can contain a maximum of 20 destinations.'),
        'mealPlan' => t('Verpflegung wählen', 'Choose meal plan'),
        'perPersonNight' => t('pro Pers. / Nacht', 'per person / night'),
        'includedMealPlan' => t('ohne Zuschlag', 'no supplement'),
        'room' => t('Zimmer', 'room'),
        'rooms' => t('Zimmer', 'rooms'),
        'roomSelection' => t('Anzahl Doppelzimmer', 'Number of double rooms'),
        'doubleRoomNotice' => t('Standardbelegung: maximal 2 Reisende pro Zimmer', 'Standard occupancy: maximum 2 travellers per room'),
        'perRoomNight' => t('Zimmer / Nacht', 'room / night'),
        'perPerson' => t('pro Person', 'per person'),
        'perPersonNightService' => t('pro Person / Nacht','per person / night'),
        'perBooking' => t('pro Buchung','per booking'),
        'typeAccommodation' => t('Unterkunft', 'Accommodation'),
        'typeSight' => t('Sehenswürdigkeit', 'Sight'),
        'typeActivity' => t('Aktivität', 'Activity'),
        'typeRestaurant' => t('Restaurant', 'Restaurant'),
        'typeSpice_garden' => t('Gewürzgarten', 'Spice garden'),
        'typeShop' => t('Lokaler Shop', 'Local shop'),
        'typeService' => t('Zusatzleistung', 'Additional service'),
        'selectDestination' => t('Wählen Sie ein Reiseziel', 'Choose a destination'),
        'selectDestinationHelp' => t('Fügen Sie links zuerst einen Ort zu Ihrer Route hinzu. Danach können Sie Hotel, Verpflegung und Erlebnisse auswählen.', 'First add a place to your route on the left. You can then choose the hotel, meal plan and experiences.'),
        'recommendedVehicle' => t('Empfohlenes Fahrzeug', 'Recommended vehicle'),
        'vehicleCapacity' => t('bis zu %d Reisende', 'up to %d travellers'),
        'vehicleHelp' => t('Automatisch nach Gruppengröße empfohlen. Ein größeres Fahrzeug kann gewählt werden.', 'Automatically recommended by group size. You may choose a larger vehicle.'),
        'noVehicle' => t('Für diese Gruppengröße ist kein Fahrzeug eingerichtet.', 'No vehicle is configured for this group size.'),
        'vehicleService' => t('Fahreroption', 'Driver option'),
        'withDriver' => t('Mit Fahrer', 'With driver'),
        'selfDrive' => t('Ohne Fahrer / Selbstfahrer', 'Without driver / self-drive'),
        'vehicleDays' => t('Fahrzeugtage', 'vehicle days'),
        'perDay' => t('pro Tag', 'per day'),
        'perKm' => t('pro km', 'per km'),
        'vehicleCost' => t('Fahrzeugkosten', 'Vehicle cost'),
        'distancePending' => t('Kilometerkosten folgen nach Berechnung der Google-Route.', 'Kilometre cost follows after the Google route is calculated.'),
        'billableDistance' => t('Berechenbare Fahrstrecke (km)', 'Billable driving distance (km)'),
        'distanceHelp' => t('Wird von Google Maps ausgefüllt oder kann manuell eingetragen werden.', 'Filled by Google Maps or entered manually.'),
        'guide' => t('Reiseleitung', 'Tour guide'),
        'noGuide' => t('Keine Reiseleitung gewünscht', 'No tour guide requested'),
        'guideDays' => t('Guide-Tage', 'guide days'),
    ],
];
?>
<main>
    <section class="planner-intro">
        <p class="eyebrow dark"><?= $sourceTour ? e(t('REISEIDEE INDIVIDUELL ANPASSEN','CUSTOMISE A TOUR IDEA')) : 'TAILOR-MADE JOURNEY' ?></p>
        <h1><?= e($sourceTour ? $sourceTour['title_'.lang()] : t('Ihre Reise nimmt Form an.', 'Your journey is taking shape.')) ?></h1>
        <p><?= e($sourceTour ? t('Die vorgeschlagene Route ist geladen. Sie können jeden Ort, jede Nacht, jedes Hotel und jedes Erlebnis verändern.', 'The suggested route is loaded. You can change every destination, night, hotel and experience.') : t('Wählen Sie Ihre Route und gestalten Sie jeden Aufenthalt im Detail.', 'Choose your route and refine each stay in detail.')) ?></p>
    </section>
    <form id="tour-planner" action="<?= e(url('request-submit.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="language" value="<?= e(lang()) ?>">
        <input type="hidden" name="mode" value="<?= $isAdminMode ? 'admin' : 'customer' ?>">
        <input type="hidden" name="source_tour_template_id" value="<?= $sourceTour ? (int)$sourceTour['id'] : 0 ?>">
        <input type="hidden" name="destinations_json" id="destinations-json">
        <input type="hidden" name="items_json" id="items-json">
        <input type="hidden" name="estimate" id="estimate-input">
        <input type="hidden" name="route_distance_km" id="route-distance-input" value="0">
        <input type="hidden" name="route_duration_minutes" id="route-duration-input" value="0">

        <nav class="mobile-planner-progress" aria-label="<?= e(t('Planungsschritte', 'Planning steps')) ?>">
            <button type="button" data-mobile-view="route"><span>1</span><?= e(t('Route', 'Route')) ?></button>
            <button type="button" data-mobile-view="builder"><span>2</span><?= e(t('Hotel & Erlebnisse', 'Stay & experiences')) ?></button>
            <button type="button" data-mobile-view="summary"><span>3</span><?= e(t('Preis & Anfrage', 'Price & enquiry')) ?></button>
        </nav>

        <div class="planner-grid">
            <aside class="planner-panel route-panel">
                <div class="mobile-panel-head"><strong><?= e(t('Route zusammenstellen', 'Build your route')) ?></strong><button type="button" data-mobile-close aria-label="<?= e(t('Schließen', 'Close')) ?>">×</button></div>
                <div class="panel-heading"><span><?= e(t('Ihre Route', 'Your route')) ?></span><b data-route-count>0</b></div>
                <div class="route-status-legend" aria-label="<?= e(t('Farblegende der Route', 'Route colour legend')) ?>">
                    <span><i class="status-selected-once"></i><?= e(t('1× gewählt', 'Selected once')) ?></span>
                    <span><i class="status-repeat-destination"></i><?= e(t('2× oder mehr', 'Twice or more')) ?></span>
                    <span><i class="status-zero-nights"></i><?= e(t('0 Nächte', '0 nights')) ?></span>
                </div>
                <label class="route-search"><span><?= e(t('Ort suchen', 'Search places')) ?></span><input type="search" data-destination-search placeholder="<?= e(t('z. B. Anuradhapura', 'e.g. Anuradhapura')) ?>"></label>
                <div class="route-options" data-route-options></div>
                <p class="route-order-help"><?= e(t('Mit den Pfeilen ändern Sie die Reihenfolge. Mit ＋ fügen Sie denselben Ort erneut hinzu, z. B. als Rückkehrstopp.', 'Use the arrows to change the order. Use ＋ to add the same place again, for example as a return stop.')) ?></p>
            </aside>

            <section class="planner-panel builder-panel">
                <header class="planner-print-header print-only"><p><?= e(strtoupper(site_setting('brand_name', 'Sri Lanka Expert'))) ?> · <?= e(strtoupper(site_setting('brand_byline', 'by Raonex GmbH'))) ?></p><h1><?= e(t('Ihre maßgeschneiderte Sri-Lanka-Reise', 'Your tailor-made Sri Lanka journey')) ?></h1><span><?= e(t('Persönliche Routenübersicht', 'Personal route overview')) ?> · <?= e(date('d.m.Y')) ?></span></header>
                <?php if ($isAdminMode): ?><div class="admin-builder-note">✓ <?= e(t('Admin-Modus: Sie planen diese Reise im Namen eines Kunden.', 'Admin mode: You are planning this journey for a client.')) ?></div><?php endif; ?>
                <div class="builder-head"><div><p data-active-region></p><h2 data-active-name></h2><button class="mobile-add-place" type="button" data-mobile-view="route">＋ <?= e(t('Ort hinzufügen oder Route ändern', 'Add a place or change route')) ?></button></div><div class="night-control"><button type="button" data-night-down>−</button><span data-active-nights></span><button type="button" data-night-up>＋</button></div></div>
                <div class="trip-basics">
                    <label><?= e(t('Reisebeginn', 'Start date')) ?><input type="date" name="start_date" min="<?= date('Y-m-d') ?>"></label>
                    <label><?= e(t('Reisende', 'Travellers')) ?><select name="travelers" data-travelers><?php for ($i=1;$i<=50;$i++): ?><option value="<?= $i ?>"<?= selected($i, (int)($_GET['travelers'] ?? 2)) ?>><?= $i ?></option><?php endfor; ?></select></label>
                    <label><?= e(t('Gesamtnächte', 'Total nights')) ?><input type="number" name="duration" data-trip-duration min="0" max="280" readonly value="0"><small><?= e(t('Summe der gewählten Nächte', 'Sum of the selected nights')) ?></small></label>
                    <label><?= e(t('Reisestil', 'Travel style')) ?><select name="travel_style"><option value="culture"<?= selected('culture', $initialStyle) ?>><?= e(t('Kultur & Genuss', 'Culture & food')) ?></option><option value="nature"<?= selected('nature', $initialStyle) ?>><?= e(t('Natur & Safari', 'Nature & safari')) ?></option><option value="ayurveda"<?= selected('ayurveda', $initialStyle) ?>><?= e(t('Ayurveda & Ruhe', 'Ayurveda & calm')) ?></option><option value="family"<?= selected('family', $initialStyle) ?>><?= e(t('Familienreise', 'Family journey')) ?></option></select></label>
                </div>
                <section class="route-map-card" aria-labelledby="route-map-title" data-route-map-card>
                    <div class="route-map-heading"><div><p>GOOGLE MAPS</p><h3 id="route-map-title"><?= e(t('Ihre Fahrroute', 'Your driving route')) ?></h3></div><div class="route-metrics"><span><small><?= e(t('Fahrstrecke', 'Driving distance')) ?></small><strong data-route-distance>—</strong></span><span><small><?= e(t('Fahrzeit', 'Driving time')) ?></small><strong data-route-duration>—</strong></span></div><button class="mobile-map-toggle" type="button" data-mobile-map-toggle data-show-label="<?= e(t('Karte anzeigen','Show map')) ?>" data-hide-label="<?= e(t('Karte schließen','Hide map')) ?>"><?= e(t('Karte anzeigen','Show map')) ?></button></div>
                    <div class="google-route-map"><div class="map-canvas" data-google-route-map></div><div class="map-placeholder visible" data-map-status><?= e(t('Karte wird vorbereitet …', 'Preparing map…')) ?></div></div>
                </section>
                <section class="planner-print-itinerary print-only"><h2><?= e(t('Reiseverlauf', 'Journey route')) ?></h2><p class="planner-print-vehicle"><span><?= e(t('Fahrzeug', 'Vehicle')) ?></span><strong data-print-vehicle>—</strong></p><p class="planner-print-vehicle" data-print-guide-row hidden><span><?= e(t('Reiseleitung', 'Tour guide')) ?></span><strong data-print-guide>—</strong></p><ol data-print-route-list></ol></section>
                <div class="category-tabs" data-category-tabs>
                    <button type="button" data-type="accommodation" class="active"><?= e(t('Unterkunft', 'Accommodation')) ?></button>
                    <button type="button" data-type="sight"><?= e(t('Sehenswertes', 'Sights')) ?></button>
                    <button type="button" data-type="activity"><?= e(t('Aktivitäten', 'Activities')) ?></button>
                    <button type="button" data-type="restaurant"><?= e(t('Restaurants', 'Restaurants')) ?></button>
                    <button type="button" data-type="spice_garden"><?= e(t('Gewürzgärten', 'Spice gardens')) ?></button>
                    <button type="button" data-type="shop"><?= e(t('Lokale Shops', 'Local shops')) ?></button>
                    <button type="button" data-type="service"><?= e(t('Zusatzleistungen', 'Additional services')) ?></button>
                </div>
                <div class="catalog-options" data-catalog-options></div>
            </section>

            <aside class="planner-panel summary-panel">
                <p><?= e(t('Aktuelle Schätzung', 'Current estimate')) ?></p>
                <strong class="estimate" data-estimate>0 €</strong>
                <span class="estimate-note"><?= e(t('inkl. gewählter Leistungen · exkl. Flug', 'selected services included · flights excluded')) ?></span>
                <div class="summary-line"></div>
                <ul class="summary-route" data-summary-route></ul>
                <div class="summary-line"></div>
                <div class="vehicle-choice">
                    <div class="transport-preview" data-vehicle-preview hidden><img data-vehicle-image alt=""><div><small><?= e(t('IHR FAHRZEUG','YOUR VEHICLE')) ?></small><strong data-vehicle-preview-name></strong></div></div>
                    <label><?= e(t('Empfohlenes Fahrzeug', 'Recommended vehicle')) ?><select name="vehicle_id" data-vehicle-select></select></label>
                    <label><?= e(t('Fahreroption', 'Driver option')) ?><select name="vehicle_service_type" data-vehicle-service></select></label>
                    <label><?= e(t('Berechenbare Fahrstrecke (km)', 'Billable driving distance (km)')) ?><input type="number" data-vehicle-distance min="0" max="5000" step="0.1" value="0"><small><?= e(t('Wird von Google Maps ausgefüllt oder kann manuell eingetragen werden.', 'Filled by Google Maps or entered manually.')) ?></small></label>
                    <small data-vehicle-capacity></small>
                    <strong data-vehicle-pricing></strong>
                    <p><?= e(t('Automatisch nach Gruppengröße empfohlen. Ein größeres Fahrzeug kann gewählt werden.', 'Automatically recommended by group size. You may choose a larger vehicle.')) ?></p>
                </div>
                <div class="summary-line"></div>
                <div class="guide-choice">
                    <label><?= e(t('Reiseleitung (optional)','Tour guide (optional)')) ?><select name="guide_id" data-guide-select><option value="0"><?= e(t('Keine Reiseleitung gewünscht','No tour guide requested')) ?></option></select></label>
                    <div class="guide-planner-preview" data-guide-preview hidden><img data-guide-image alt=""><div><strong data-guide-name></strong><small data-guide-details></small><em data-guide-pricing></em></div></div>
                    <p><?= e(t('Nur geprüfte und freigegebene Reiseleiter werden angezeigt.','Only reviewed and approved guides are shown.')) ?></p>
                </div>
                <div class="summary-line"></div>
                <div class="contact-fields">
                    <label><?= e(t('Name', 'Name')) ?><input name="customer_name" required maxlength="150" value="<?= e($customerFormUser['name']??'') ?>"></label>
                    <label><?= e(t('E-Mail', 'Email')) ?><input name="customer_email" type="email" required maxlength="190" value="<?= e($customerFormUser['email']??'') ?>"></label>
                    <label><?= e(t('Telefon / WhatsApp', 'Phone / WhatsApp')) ?><input name="customer_phone" maxlength="80" value="<?= e($customerFormUser['phone']??'') ?>"></label>
                    <label><?= e(t('Wünsche & Hinweise', 'Wishes & notes')) ?><textarea name="notes" rows="4" maxlength="4000"></textarea></label>
                </div>
                <button class="button button-print" type="button" data-print-route><?= e(t('Route anzeigen & drucken', 'View & print route')) ?> <span>⌁</span></button>
                <button class="button button-gold" type="submit"><?= e($isAdminMode ? t('Kundenreise speichern', 'Save client journey') : t('Unverbindlich anfragen', 'Send enquiry')) ?> <span>→</span></button>
                <p class="summary-fine"><?= e(t('Noch keine Buchung. Wir prüfen Verfügbarkeit und Preis persönlich.', 'No booking yet. We personally verify availability and price.')) ?></p>
            </aside>
        </div>
        <nav class="mobile-planner-bottom" aria-label="<?= e(t('Planungsschritte', 'Planning steps')) ?>">
            <button type="button" data-mobile-view="route"><span>⌖</span><?= e(t('Route', 'Route')) ?><b data-mobile-route-count>0</b></button>
            <button type="button" data-mobile-view="builder"><span>⌂</span><?= e(t('Aufenthalt', 'Stay')) ?></button>
            <button type="button" data-mobile-view="summary"><span>€</span><?= e(t('Preis', 'Price')) ?><strong data-mobile-estimate>0 €</strong></button>
        </nav>
    </form>
</main>
<script>window.PLANNER_DATA = <?= json_encode($plannerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('js/planner.js')) ?>"></script>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
