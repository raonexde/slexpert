<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$stayType = in_array($_GET['type'] ?? '', ['beach','ayurveda','maldives'], true) ? (string)$_GET['type'] : 'beach';
$availabilityColumn = match ($stayType) { 'ayurveda'=>'ayurveda_available', 'maldives'=>'maldives_available', default=>'beach_available' };
$countryCode = $stayType === 'maldives' ? 'MV' : 'LK';
$hotelStmt = db()->prepare(
    "SELECT c.*,d.name_de AS destination_name_de,d.name_en AS destination_name_en,
            d.region_de,d.region_en,d.code AS destination_code,d.accent AS destination_accent,
            cc.name_de AS classification_de,cc.name_en AS classification_en
     FROM catalog_items c JOIN destinations d ON d.id=c.destination_id
     LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id
     WHERE c.type='accommodation' AND c.active=1 AND d.active=1 AND d.country_code=? AND c.$availabilityColumn=1
     ORDER BY d.sort_order,c.featured DESC,c.sort_order,c.name_de"
);
$hotelStmt->execute([$countryCode]);
$hotels = $hotelStmt->fetchAll();
$hotelIds = array_map(static fn(array $hotel): int => (int)$hotel['id'], $hotels);
$mealPlansByHotel = [];
if ($hotelIds) {
    $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
    $mealStmt = db()->prepare("SELECT * FROM accommodation_meal_plans WHERE active=1 AND catalog_item_id IN ($placeholders) ORDER BY catalog_item_id,sort_order,id");
    $mealStmt->execute($hotelIds);
    foreach ($mealStmt->fetchAll() as $mealPlan) $mealPlansByHotel[(int)$mealPlan['catalog_item_id']][] = $mealPlan;
}
$serviceStmt = db()->prepare(
    "SELECT c.*,d.code AS destination_code,d.accent AS destination_accent,
            cc.name_de AS classification_de,cc.name_en AS classification_en
     FROM catalog_items c JOIN destinations d ON d.id=c.destination_id
     LEFT JOIN catalog_classifications cc ON cc.id=c.classification_id
     WHERE c.type IN ('sight','activity','restaurant','spice_garden','shop','service') AND c.active=1 AND d.active=1 AND d.country_code=? AND c.$availabilityColumn=1
     ORDER BY c.destination_id,c.type,c.featured DESC,c.sort_order,c.name_de"
);
$serviceStmt->execute([$countryCode]);
$services = $serviceStmt->fetchAll();
$destinations = [];
foreach ($hotels as $hotel) {
    $destinationId = (int)$hotel['destination_id'];
    $destinations[$destinationId] = [
        'id'=>$destinationId,
        'name_de'=>$hotel['destination_name_de'],'name_en'=>$hotel['destination_name_en'],
        'region_de'=>$hotel['region_de'],'region_en'=>$hotel['region_en'],
    ];
}
$isAdminMode = (($_GET['mode'] ?? '') === 'admin') && admin_user();
$portalFormUser = portal_user();
$customerFormUser = $portalFormUser && $portalFormUser['user_type'] === 'customer' ? $portalFormUser : null;
$pageTitle = match ($stayType) {
    'ayurveda'=>t('Ayurveda-Aufenthalt planen','Plan an Ayurveda retreat'),
    'maldives'=>t('Malediven-Resort buchen','Book a Maldives resort'),
    default=>t('Strandurlaub planen','Plan a beach vacation'),
};
$bodyClass = 'stay-page ' . $stayType . '-stay-page';
$extraStyles = ['css/stay.css'];
require __DIR__ . '/includes/public-header.php';

$stayData = [
    'language'=>lang(),
    'type'=>$stayType,
    'initialHotelId'=>max(0,(int)($_GET['hotel']??0)),
    'hotels'=>array_map(static function(array $hotel) use ($mealPlansByHotel): array {
        return [
            'id'=>(int)$hotel['id'],'destination_id'=>(int)$hotel['destination_id'],
            'name_de'=>$hotel['name_de'],'name_en'=>$hotel['name_en'],
            'description_de'=>$hotel['description_de'],'description_en'=>$hotel['description_en'],
            'meta_de'=>$hotel['meta_de'],'meta_en'=>$hotel['meta_en'],
            'classification_de'=>$hotel['classification_de']??'','classification_en'=>$hotel['classification_en']??'',
            'star_rating'=>(int)$hotel['star_rating'],'market_segment'=>$hotel['market_segment'],
            'facilities_de'=>$hotel['facilities_de'],'facilities_en'=>$hotel['facilities_en'],
            'destination_name_de'=>$hotel['destination_name_de'],'destination_name_en'=>$hotel['destination_name_en'],
            'region_de'=>$hotel['region_de'],'region_en'=>$hotel['region_en'],
            'code'=>$hotel['destination_code'],'accent'=>$hotel['destination_accent'],
            'rate'=>price_with_markup((float)$hotel['price_per_person']),
            'standard_guests'=>(int)$hotel['standard_room_guests'],
            'maximum_guests'=>(int)$hotel['max_guests_with_extra_bed'],
            'extra_bed_percent'=>(float)$hotel['extra_bed_percent'],
            'child_percent'=>(float)$hotel['child_percent'],
            'child_max_age'=>(int)$hotel['child_max_age'],
            'night_options'=>array_values(array_filter(array_map('intval',explode(',',(string)$hotel['ayurveda_night_options'])),static fn(int $night): bool=>$night>0&&$night<=90)),
            'image'=>$hotel['image_path']?url($hotel['image_path']):'',
            'featured'=>(bool)$hotel['featured'],
            'meal_plans'=>array_map(static fn(array $plan): array=>[
                'code'=>$plan['code'],'name_de'=>$plan['name_de'],'name_en'=>$plan['name_en'],
                'supplement'=>price_with_markup((float)$plan['supplement_per_person_night']),
            ],$mealPlansByHotel[(int)$hotel['id']]??[]),
        ];
    },$hotels),
    'services'=>array_map(static fn(array $service): array=>[
        'id'=>(int)$service['id'],'destination_id'=>(int)$service['destination_id'],'type'=>$service['type'],
        'name_de'=>$service['name_de'],'name_en'=>$service['name_en'],
        'description_de'=>$service['description_de'],'description_en'=>$service['description_en'],
        'meta_de'=>$service['meta_de'],'meta_en'=>$service['meta_en'],
        'classification_de'=>$service['classification_de']??'','classification_en'=>$service['classification_en']??'',
        'price'=>price_with_markup((float)$service['price_per_person']),'price_basis'=>$service['price_basis'],
        'image'=>$service['image_path']?url($service['image_path']):'',
        'featured'=>(bool)$service['featured'],'code'=>$service['destination_code'],'accent'=>$service['destination_accent'],
    ],$services),
    'labels'=>[
        'chooseHotel'=>t('Hotel auswählen','Choose hotel'),'chosen'=>t('Ausgewählt','Selected'),
        'perRoomNight'=>t('pro Doppelzimmer / Nacht','per double room / night'),
        'perPerson'=>t('pro Person','per person'),'perPersonNight'=>t('pro Person / Nacht','per person / night'),
        'perBooking'=>t('pro Buchung','per booking'),'included'=>t('Inklusive','Included'),
        'roomCost'=>t('Zimmer','Rooms'),'extraBedCost'=>t('Zusatzbetten','Extra beds'),
        'mealCost'=>t('Verpflegung','Meal plan'),'servicesCost'=>t('Zusatzleistungen','Additional services'),
        'night'=>t('Nacht','night'),'nights'=>t('Nächte','nights'),'rooms'=>t('Zimmer','rooms'),
        'adult'=>t('Erwachsener','adult'),'adults'=>t('Erwachsene','adults'),
        'child'=>t('Kind','child'),'children'=>t('Kinder','children'),
        'selectHotelFirst'=>t('Wählen Sie zuerst ein Hotel.','Choose a hotel first.'),
        'noServices'=>t('Für dieses Hotel sind noch keine Zusatzleistungen hinterlegt.','No additional services have been added for this hotel yet.'),
        'occupancyError'=>t('Für diese Gästezahl benötigen Sie mehr Zimmer. Pro Doppelzimmer sind maximal drei Gäste inklusive Zusatzbett möglich.','More rooms are required for this number of guests. A double room accommodates up to three guests including an extra bed.'),
        'pricingRule'=>t('Zimmerpreis × Zimmer × Nächte. %g Standardgäste pro Zimmer; Zusatzbett +%e %. Kinder bis %a Jahre zahlen %c % des entsprechenden Erwachsenen-Zuschlags.','Room rate × rooms × nights. %g standard guests per room; extra bed +%e%. Children up to age %a pay %c% of the corresponding adult supplement.'),
        'allInclusiveRequired'=>t('Ayurveda-Aufenthalte beinhalten verpflichtend All-inclusive.','Ayurveda retreats require all-inclusive.'),
        'typeSight'=>t('Ausflüge','Excursions'),'typeActivity'=>t('Aktivitäten','Activities'),
        'typeRestaurant'=>t('Restaurants','Restaurants'),'typeSpiceGarden'=>t('Gewürzgärten','Spice gardens'),
        'typeShop'=>t('Lokale Shops','Local shops'),'typeService'=>t('Zusatzleistungen','Additional services'),
        'free'=>t('Kostenfrei','Free'),'onRequest'=>t('Preis auf Anfrage','Price on request'),
        'emptyHotels'=>t('Noch keine passenden Hotels eingerichtet. Aktivieren Sie Hotels im Admin für diesen Bereich.','No matching hotels are configured yet. Enable hotels for this section in admin.'),
    ],
];
?>
<main>
    <section class="stay-hero">
        <div>
            <p class="eyebrow"><?= e(match($stayType){'ayurveda'=>t('AYURVEDA & REGENERATION','AYURVEDA & REJUVENATION'),'maldives'=>t('MALEDIVEN · INSELRESORTS','MALDIVES · ISLAND RESORTS'),default=>t('STRAND & ERHOLUNG','BEACH & RELAXATION')}) ?></p>
            <h1><?= e(match($stayType){'ayurveda'=>t('Ein Hotel. Zeit für neues Gleichgewicht.','One hotel. Time to restore balance.'),'maldives'=>t('Eine Insel. Ihr persönlicher Malediven-Urlaub.','One island. Your personal Maldives escape.'),default=>t('Ein Hotel. Ihr persönlicher Strandurlaub.','One hotel. Your personal beach vacation.')}) ?></h1>
            <p><?= e(match($stayType){'ayurveda'=>t('Wählen Sie ein Ayurveda-Hotel, 7 bis 28 Nächte und ergänzen Sie Behandlungen oder Ausflüge.','Choose one Ayurveda hotel, 7 to 28 nights, and add treatments or excursions.'),'maldives'=>t('Wählen Sie ein Resort, Zimmer und Verpflegung. Flug, Schnellboot- oder Wasserflugzeugtransfer und Erlebnisse können separat ergänzt werden.','Choose one resort, rooms and meals. Add flights, speedboat or seaplane transfers and experiences separately.'),default=>t('Wählen Sie ein Strandhotel, Zimmer und Verpflegung. Transfers und Erlebnisse können Sie individuell ergänzen.','Choose one beach hotel, rooms and meals. Add transfers and experiences individually.')}) ?></p>
        </div>
        <nav class="stay-mode-switch" aria-label="Stay type">
            <a class="<?= $stayType==='beach'?'active':'' ?>" href="<?= e(url('stay.php?type=beach&lang='.lang())) ?>"><?= e(t('Strandurlaub','Beach vacation')) ?></a>
            <a class="<?= $stayType==='ayurveda'?'active':'' ?>" href="<?= e(url('stay.php?type=ayurveda&lang='.lang())) ?>"><?= e(t('Ayurveda-Hotels','Ayurveda hotels')) ?></a>
            <a class="<?= $stayType==='maldives'?'active':'' ?>" href="<?= e(url('stay.php?type=maldives&lang='.lang())) ?>"><?= e(t('Malediven-Resorts','Maldives resorts')) ?></a>
            <a href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Rundreise','Tailor-made tour')) ?></a>
        </nav>
    </section>

    <form id="stay-planner" action="<?= e(url('stay-submit.php')) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="language" value="<?= e(lang()) ?>">
        <input type="hidden" name="request_type" value="<?= e($stayType) ?>">
        <input type="hidden" name="mode" value="<?= $isAdminMode?'admin':'customer' ?>">
        <input type="hidden" name="hotel_id" data-hotel-input value="">
        <input type="hidden" name="services_json" data-services-input value="[]">
        <input type="hidden" name="estimate" data-estimate-input value="0">

        <div class="stay-layout">
            <section class="stay-builder">
                <?php if($isAdminMode):?><div class="admin-builder-note">✓ <?= e(t('Admin-Modus: Sie erstellen diesen Hotelaufenthalt für einen Kunden.','Admin mode: you are creating this hotel stay for a client.')) ?></div><?php endif;?>
                <section class="stay-card stay-basics-card">
                    <div class="stay-section-head"><span>01</span><div><p><?= e(t('REISEDATEN','STAY DETAILS')) ?></p><h2><?= e(t('Wer reist wann?','Who is travelling and when?')) ?></h2></div></div>
                    <div class="stay-fields">
                        <label><?= e(t('Anreise','Arrival')) ?><input type="date" name="start_date" min="<?= date('Y-m-d') ?>"></label>
                        <label><?= e(t('Erwachsene','Adults')) ?><select name="adults" data-adults><?php for($i=1;$i<=20;$i++):?><option value="<?= $i ?>"<?= selected($i,2) ?>><?= $i ?></option><?php endfor;?></select></label>
                        <label><?= e(t('Kinder bis 12 Jahre','Children up to 12')) ?><select name="children" data-children><?php for($i=0;$i<=10;$i++):?><option value="<?= $i ?>"><?= $i ?></option><?php endfor;?></select><small><?= e(t('Ältere Kinder bitte als Erwachsene zählen.','Count older children as adults.')) ?></small></label>
                        <label><?= e(t('Doppelzimmer','Double rooms')) ?><select name="rooms" data-rooms><?php for($i=1;$i<=10;$i++):?><option value="<?= $i ?>"><?= $i ?></option><?php endfor;?></select></label>
                        <label><?= e(t('Aufenthaltsdauer','Length of stay')) ?><select name="nights" data-nights></select></label>
                        <label><?= e($stayType==='maldives'?t('Atoll','Atoll'):t('Region / Ort','Region / place')) ?><select data-destination-filter><option value="0"><?= e($stayType==='maldives'?t('Alle Atolle','All atolls'):t('Alle Orte','All destinations')) ?></option><?php foreach($destinations as $destination):?><option value="<?= (int)$destination['id'] ?>"><?= e($destination['name_'.lang()]) ?></option><?php endforeach;?></select></label>
                    </div>
                    <p class="stay-occupancy-message" data-occupancy-message></p>
                </section>

                <section class="stay-card">
                    <div class="stay-section-head"><span>02</span><div><p><?= e($stayType==='maldives'?t('RESORTAUSWAHL','RESORT SELECTION'):t('HOTELAUSWAHL','HOTEL SELECTION')) ?></p><h2><?= e($stayType==='maldives'?t('Wählen Sie genau ein Inselresort.','Choose exactly one island resort.'):t('Wählen Sie genau ein Hotel.','Choose exactly one hotel.')) ?></h2></div></div>
                    <div class="stay-hotel-grid" data-hotel-grid></div>
                    <p class="stay-pricing-rule" data-pricing-rule></p>
                </section>

                <section class="stay-card" data-meal-section hidden>
                    <div class="stay-section-head"><span>03</span><div><p><?= e(t('VERPFLEGUNG','MEAL PLAN')) ?></p><h2><?= e($stayType==='ayurveda'?t('All-inclusive ist enthalten.','All-inclusive is required.'):t('Wählen Sie Ihre Verpflegung.','Choose your meal plan.')) ?></h2></div></div>
                    <label class="stay-meal-field"><?= e(t('Verpflegungsart','Meal plan')) ?><select name="meal_plan_code" data-meal-plan></select><small data-meal-note></small></label>
                </section>

                <section class="stay-card">
                    <div class="stay-section-head"><span>04</span><div><p><?= e(t('OPTIONAL','OPTIONAL')) ?></p><h2><?= e(t('Zusätzliche Leistungen auswählen.','Choose additional services.')) ?></h2></div></div>
                    <nav class="stay-service-tabs" data-service-tabs>
                        <button type="button" data-service-type="service" class="active"><?= e(t('Leistungen','Services')) ?></button>
                        <button type="button" data-service-type="sight"><?= e(t('Ausflüge','Excursions')) ?></button>
                        <button type="button" data-service-type="activity"><?= e(t('Aktivitäten','Activities')) ?></button>
                        <button type="button" data-service-type="restaurant"><?= e(t('Restaurants','Restaurants')) ?></button>
                        <button type="button" data-service-type="spice_garden"><?= e(t('Gewürzgärten','Spice gardens')) ?></button>
                        <button type="button" data-service-type="shop"><?= e(t('Lokale Shops','Local shops')) ?></button>
                    </nav>
                    <div class="stay-service-grid" data-service-grid></div>
                </section>
            </section>

            <aside class="stay-summary">
                <p class="eyebrow dark"><?= e(t('AKTUELLE SCHÄTZUNG','CURRENT ESTIMATE')) ?></p>
                <strong class="stay-total" data-total>0 €</strong>
                <span><?= e(t('inkl. gewählter Leistungen · exkl. Flug','selected services included · flights excluded')) ?></span>
                <div class="stay-selected" data-selected-stay></div>
                <dl class="stay-breakdown">
                    <div><dt><?= e(t('Zimmer','Rooms')) ?></dt><dd data-room-total>0 €</dd></div>
                    <div><dt><?= e(t('Zusatzbetten','Extra beds')) ?></dt><dd data-extra-total>0 €</dd></div>
                    <div><dt><?= e(t('Verpflegung','Meal plan')) ?></dt><dd data-meal-total>0 €</dd></div>
                    <div><dt><?= e(t('Zusatzleistungen','Additional services')) ?></dt><dd data-services-total>0 €</dd></div>
                </dl>
                <div class="contact-fields">
                    <label><?= e(t('Name','Name')) ?><input name="customer_name" required maxlength="150" value="<?= e($customerFormUser['name']??'') ?>"></label>
                    <label><?= e(t('E-Mail','Email')) ?><input name="customer_email" type="email" required maxlength="190" value="<?= e($customerFormUser['email']??'') ?>"></label>
                    <label><?= e(t('Telefon / WhatsApp','Phone / WhatsApp')) ?><input name="customer_phone" maxlength="80" value="<?= e($customerFormUser['phone']??'') ?>"></label>
                    <label><?= e(t('Wünsche & Hinweise','Wishes & notes')) ?><textarea name="notes" rows="4" maxlength="4000"></textarea></label>
                </div>
                <button class="button button-gold" type="submit" data-submit-stay disabled><?= e($isAdminMode?t('Aufenthalt speichern','Save client stay'):t('Buchungsanfrage senden','Send booking request')) ?> <span>→</span></button>
                <p class="summary-fine"><?= e(t('Noch keine bestätigte Buchung. Wir prüfen Hotelverfügbarkeit und Endpreis persönlich.','This is not a confirmed booking. We personally verify hotel availability and the final price.')) ?></p>
            </aside>
        </div>
    </form>
</main>
<script>window.STAY_DATA=<?= json_encode($stayData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('js/stay-planner.js')) ?>"></script>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
