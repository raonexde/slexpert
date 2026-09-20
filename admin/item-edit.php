<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
$item = [
    'id'=>0,'destination_id'=>(int)($_GET['destination_id'] ?? 0),'type'=>'accommodation',
    'classification_id'=>0,'star_rating'=>0,'market_segment'=>'','sltda_registration_number'=>'','sltda_registration_expiry'=>'',
    'name_de'=>'','name_en'=>'','description_de'=>'','description_en'=>'','meta_de'=>'','meta_en'=>'',
    'facilities_de'=>'','facilities_en'=>'','opening_hours_de'=>'','opening_hours_en'=>'','duration_minutes'=>0,'booking_required'=>0,
    'supplier_name'=>'','supplier_contact'=>'','contract_price'=>0,'commission_percent'=>0,'internal_notes'=>'',
    'price_per_person'=>0,'price_basis'=>'per_person','beach_available'=>0,'ayurveda_available'=>0,'maldives_available'=>0,
    'standard_room_guests'=>2,'max_guests_with_extra_bed'=>3,'extra_bed_percent'=>30,'child_percent'=>50,
    'child_max_age'=>12,'ayurveda_night_options'=>'7,14,21,28','image_path'=>'','featured'=>0,'active'=>1,'sort_order'=>0,
];
$mealPlanDefaults = [
    'breakfast' => ['name_de'=>'Frühstück','name_en'=>'Breakfast','supplement'=>0,'sort_order'=>10],
    'room_only' => ['name_de'=>'Nur Übernachtung','name_en'=>'Room only','supplement'=>0,'sort_order'=>20],
    'half_board' => ['name_de'=>'Halbpension','name_en'=>'Half board','supplement'=>28,'sort_order'=>30],
    'full_board' => ['name_de'=>'Vollpension','name_en'=>'Full board','supplement'=>46,'sort_order'=>40],
    'all_inclusive' => ['name_de'=>'All-inclusive','name_en'=>'All inclusive','supplement'=>68,'sort_order'=>50],
];
if ($id) {
    $stmt=db()->prepare('SELECT * FROM catalog_items WHERE id=?');$stmt->execute([$id]);$item=$stmt->fetch()?:$item;
    if (!(int)$item['id']) { http_response_code(404); exit('Item not found'); }
}
$mealPlans = [];
if ($id) {
    $mealStmt = db()->prepare('SELECT * FROM accommodation_meal_plans WHERE catalog_item_id=? ORDER BY sort_order,id');
    $mealStmt->execute([$id]);
    foreach ($mealStmt->fetchAll() as $mealPlan) $mealPlans[$mealPlan['code']] = $mealPlan;
}
foreach ($mealPlanDefaults as $code=>$defaults) {
    if (!isset($mealPlans[$code])) $mealPlans[$code] = array_merge(['code'=>$code,'active'=>1], $defaults);
}
$destinations=db()->query('SELECT id,name_de,country_code FROM destinations ORDER BY country_code,sort_order,name_de')->fetchAll();
$classifications=db()->query('SELECT * FROM catalog_classifications ORDER BY item_type,sort_order,name_de')->fetchAll();

if (request_is_post()) {
    verify_csrf();
    try {
        $allowedTypes = ['accommodation','sight','activity','restaurant','spice_garden','shop','service'];
        $type = in_array($_POST['type'] ?? '', $allowedTypes, true) ? $_POST['type'] : 'activity';
        $priceBasis = in_array($_POST['price_basis'] ?? '', ['per_person','per_person_night','per_booking','free','on_request'], true) ? $_POST['price_basis'] : 'per_person';
        $classificationId = max(0,(int)($_POST['classification_id']??0));
        if ($classificationId > 0) {
            $classificationStmt = db()->prepare('SELECT item_type FROM catalog_classifications WHERE id=?');
            $classificationStmt->execute([$classificationId]);
            if ($classificationStmt->fetchColumn() !== $type) throw new RuntimeException('Die gewählte Unterkategorie passt nicht zum Angebotstyp.');
        }
        $image=upload_image($_FILES['image']??[],(string)$item['image_path']);
        $expiry = trim((string)($_POST['sltda_registration_expiry']??''));
        if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry)) $expiry = '';
        $record=[
            'destination_id'=>(int)($_POST['destination_id']??0),'type'=>$type,'classification_id'=>$classificationId?:null,
            'star_rating'=>$type==='accommodation'?max(0,min(5,(int)($_POST['star_rating']??0))):0,
            'market_segment'=>$type==='accommodation'&&in_array($_POST['market_segment']??'', ['luxury','premium','mid_range','budget'],true)?$_POST['market_segment']:'',
            'sltda_registration_number'=>substr(trim((string)($_POST['sltda_registration_number']??'')),0,120),'sltda_registration_expiry'=>$expiry?:null,
            'name_de'=>trim((string)($_POST['name_de']??'')),'name_en'=>trim((string)($_POST['name_en']??'')),
            'description_de'=>trim((string)($_POST['description_de']??'')),'description_en'=>trim((string)($_POST['description_en']??'')),
            'meta_de'=>trim((string)($_POST['meta_de']??'')),'meta_en'=>trim((string)($_POST['meta_en']??'')),
            'facilities_de'=>trim((string)($_POST['facilities_de']??'')),'facilities_en'=>trim((string)($_POST['facilities_en']??'')),
            'opening_hours_de'=>substr(trim((string)($_POST['opening_hours_de']??'')),0,255),'opening_hours_en'=>substr(trim((string)($_POST['opening_hours_en']??'')),0,255),
            'duration_minutes'=>max(0,min(1440,(int)($_POST['duration_minutes']??0))),'booking_required'=>isset($_POST['booking_required'])?1:0,
            'supplier_name'=>substr(trim((string)($_POST['supplier_name']??'')),0,190),'supplier_contact'=>trim((string)($_POST['supplier_contact']??'')),
            'contract_price'=>max(0,(float)($_POST['contract_price']??0)),'commission_percent'=>max(0,min(100,(float)($_POST['commission_percent']??0))),
            'internal_notes'=>trim((string)($_POST['internal_notes']??'')),
            'price_per_person'=>in_array($priceBasis,['free','on_request'],true)?0:max(0,(float)($_POST['price_per_person']??0)),
            'price_basis'=>$type==='accommodation'?'per_person_night':$priceBasis,
            'beach_available'=>isset($_POST['beach_available'])?1:0,'ayurveda_available'=>isset($_POST['ayurveda_available'])?1:0,'maldives_available'=>isset($_POST['maldives_available'])?1:0,
            'standard_room_guests'=>max(1,min(4,(int)($_POST['standard_room_guests']??2))),'max_guests_with_extra_bed'=>max(1,min(6,(int)($_POST['max_guests_with_extra_bed']??3))),
            'extra_bed_percent'=>max(0,min(100,(float)($_POST['extra_bed_percent']??30))),'child_percent'=>max(0,min(100,(float)($_POST['child_percent']??50))),
            'child_max_age'=>max(0,min(17,(int)($_POST['child_max_age']??12))),'ayurveda_night_options'=>substr(preg_replace('/[^0-9,]/','',(string)($_POST['ayurveda_night_options']??'7,14,21,28')),0,80),
            'image_path'=>$image,'featured'=>isset($_POST['featured'])?1:0,'active'=>isset($_POST['active'])?1:0,'sort_order'=>(int)($_POST['sort_order']??0),
        ];
        if (!$record['destination_id'] || $record['name_de']==='' || $record['name_en']==='') throw new RuntimeException('Reiseziel und beide Namen sind erforderlich.');
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            $record['id']=$id;
            $pdo->prepare('UPDATE catalog_items SET destination_id=:destination_id,type=:type,classification_id=:classification_id,star_rating=:star_rating,market_segment=:market_segment,sltda_registration_number=:sltda_registration_number,sltda_registration_expiry=:sltda_registration_expiry,name_de=:name_de,name_en=:name_en,description_de=:description_de,description_en=:description_en,meta_de=:meta_de,meta_en=:meta_en,facilities_de=:facilities_de,facilities_en=:facilities_en,opening_hours_de=:opening_hours_de,opening_hours_en=:opening_hours_en,duration_minutes=:duration_minutes,booking_required=:booking_required,supplier_name=:supplier_name,supplier_contact=:supplier_contact,contract_price=:contract_price,commission_percent=:commission_percent,internal_notes=:internal_notes,price_per_person=:price_per_person,price_basis=:price_basis,beach_available=:beach_available,ayurveda_available=:ayurveda_available,maldives_available=:maldives_available,standard_room_guests=:standard_room_guests,max_guests_with_extra_bed=:max_guests_with_extra_bed,extra_bed_percent=:extra_bed_percent,child_percent=:child_percent,child_max_age=:child_max_age,ayurveda_night_options=:ayurveda_night_options,image_path=:image_path,featured=:featured,active=:active,sort_order=:sort_order WHERE id=:id')->execute($record);
        } else {
            $columns=implode(',',array_keys($record));
            $parameters=':'.implode(',:',array_keys($record));
            $pdo->prepare("INSERT INTO catalog_items ($columns) VALUES ($parameters)")->execute($record);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM accommodation_meal_plans WHERE catalog_item_id=?')->execute([$id]);
        if ($type === 'accommodation') {
            $mealInsert = $pdo->prepare('INSERT INTO accommodation_meal_plans (catalog_item_id,code,name_de,name_en,supplement_per_person_night,active,sort_order) VALUES (?,?,?,?,?,?,?)');
            $postedActive = is_array($_POST['meal_plan_active'] ?? null) ? $_POST['meal_plan_active'] : [];
            $postedNamesDe = is_array($_POST['meal_plan_name_de'] ?? null) ? $_POST['meal_plan_name_de'] : [];
            $postedNamesEn = is_array($_POST['meal_plan_name_en'] ?? null) ? $_POST['meal_plan_name_en'] : [];
            $postedSupplements = is_array($_POST['meal_plan_supplement'] ?? null) ? $_POST['meal_plan_supplement'] : [];
            foreach ($mealPlanDefaults as $code=>$defaults) {
                $mealInsert->execute([
                    $id,$code,
                    substr(trim((string)($postedNamesDe[$code] ?? $defaults['name_de'])),0,120),
                    substr(trim((string)($postedNamesEn[$code] ?? $defaults['name_en'])),0,120),
                    max(0,min(5000,(float)($postedSupplements[$code] ?? $defaults['supplement']))),
                    isset($postedActive[$code]) ? 1 : 0,
                    $defaults['sort_order'],
                ]);
            }
        }
        $pdo->commit();
        flash('success','Reisebaustein wurde gespeichert.');redirect('admin/catalog.php');
    } catch(Throwable $exception){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();flash('error','Speichern fehlgeschlagen: '.$exception->getMessage());redirect('admin/item-edit.php'.($id?'?id='.$id:''));}
}

$typeLabels=['accommodation'=>'Unterkunft','sight'=>'Sehenswürdigkeit','activity'=>'Aktivitätszentrum','restaurant'=>'Restaurant','spice_garden'=>'Gewürzgarten','shop'=>'Shop / Einkaufsstopp','service'=>'Zusatzleistung / Ayurveda-Paket'];
$adminPage='catalog';$adminTitle=$id?'Baustein bearbeiten':'Baustein erstellen';require __DIR__.'/_header.php';
?>
<div class="admin-content narrow">
    <a class="back-admin" href="catalog.php">← Reisebausteine</a>
    <div class="page-heading compact"><div><p>ANGEBOT</p><h1><?= $id?'Reisebaustein bearbeiten':'Neuer Reisebaustein' ?></h1><span>Unterkunft, Sehenswürdigkeit, Aktivität, Restaurant, Gewürzgarten, Shop oder Zusatzleistung.</span></div></div>
    <form class="editor-form" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Zuordnung</h2><p>Ort, Reiseziel und Kategorie</p></div></div><div class="form-grid">
            <div class="form-field"><div class="field-label-row"><label for="destination-id">Ort / Reiseziel</label><a class="inline-create" href="destination-edit.php?return_to=item-edit">＋ Neuen Ort hinzufügen</a></div><select id="destination-id" name="destination_id" required><option value="">Bitte wählen</option><?php foreach($destinations as $destination):?><option value="<?= (int)$destination['id'] ?>"<?= selected($destination['id'],$item['destination_id']) ?>><?= e($destination['country_code']==='MV'?'Malediven · ':'Sri Lanka · ') ?><?= e($destination['name_de']) ?></option><?php endforeach;?></select><small>Der neue Ort erscheint anschließend automatisch in dieser Liste.</small></div>
            <label>Typ<select name="type"><?php foreach($typeLabels as $value=>$label):?><option value="<?= e($value) ?>"<?= selected($value,$item['type']) ?>><?= e($label) ?></option><?php endforeach;?></select></label>
            <div class="form-field"><div class="field-label-row"><label for="classification-id">Unterkategorie / Klassifizierung</label><a class="inline-create" href="classifications.php">Kategorien verwalten</a></div><select id="classification-id" name="classification_id"><option value="0">Keine Unterkategorie</option><?php foreach($classifications as $classification):?><option value="<?= (int)$classification['id'] ?>" data-item-type="<?= e($classification['item_type']) ?>"<?= selected($classification['id'],$item['classification_id']) ?>><?= e($classification['name_de']) ?><?= $classification['active']?'':' · inaktiv' ?></option><?php endforeach;?></select><small>Die Liste wird passend zum ausgewählten Typ gefiltert.</small></div>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Titel & Beschreibung</h2><p>Deutsch und Englisch</p></div></div><div class="form-grid">
            <label>Name Deutsch<input name="name_de" required value="<?= e($item['name_de']) ?>"></label><label>Name Englisch<input name="name_en" required value="<?= e($item['name_en']) ?>"></label>
            <label>Beschreibung Deutsch<textarea name="description_de" rows="4" required><?= e($item['description_de']) ?></textarea></label><label>Beschreibung Englisch<textarea name="description_en" rows="4" required><?= e($item['description_en']) ?></textarea></label>
            <label>Kurzinfo Deutsch<input name="meta_de" value="<?= e($item['meta_de']) ?>" placeholder="z. B. 3 Stunden · Eintritt inklusive"></label><label>Kurzinfo Englisch<input name="meta_en" value="<?= e($item['meta_en']) ?>"></label>
            <label>Ausstattung / Merkmale Deutsch<textarea name="facilities_de" rows="3" placeholder="Pool, Klimaanlage, WLAN, barrierefrei …"><?= e($item['facilities_de']) ?></textarea></label><label>Facilities / features English<textarea name="facilities_en" rows="3"><?= e($item['facilities_en']) ?></textarea></label>
            <label>Öffnungszeiten Deutsch<input name="opening_hours_de" maxlength="255" value="<?= e($item['opening_hours_de']) ?>" placeholder="Täglich 09:00–18:00"></label><label>Opening hours English<input name="opening_hours_en" maxlength="255" value="<?= e($item['opening_hours_en']) ?>"></label>
            <label>Dauer in Minuten<input type="number" min="0" max="1440" name="duration_minutes" value="<?= (int)$item['duration_minutes'] ?>"><small>0, wenn nicht relevant.</small></label><label class="check"><input type="checkbox" name="booking_required" value="1"<?= checked((bool)$item['booking_required']) ?>> Reservierung erforderlich</label>
        </div></section>
        <section class="admin-card form-card" data-accommodation-classification><div class="card-head"><div><h2>Hotelklassifizierung</h2><p>Offizielle Einstufung und Verkaufssegment getrennt verwalten</p></div></div><div class="form-grid thirds">
            <label>Offizielle Sterne<select name="star_rating"><option value="0"<?= selected(0,$item['star_rating']) ?>>Nicht klassifiziert</option><?php for($stars=1;$stars<=5;$stars++):?><option value="<?= $stars ?>"<?= selected($stars,$item['star_rating']) ?>><?= $stars ?> Stern<?= $stars===1?'':'e' ?></option><?php endfor;?></select></label>
            <label>Verkaufssegment<select name="market_segment"><option value=""<?= selected('',$item['market_segment']) ?>>Nicht festgelegt</option><option value="luxury"<?= selected('luxury',$item['market_segment']) ?>>Luxury</option><option value="premium"<?= selected('premium',$item['market_segment']) ?>>Premium</option><option value="mid_range"<?= selected('mid_range',$item['market_segment']) ?>>Mid-range</option><option value="budget"<?= selected('budget',$item['market_segment']) ?>>Budget</option></select></label>
            <label>SLTDA-Registrierungsnummer<input name="sltda_registration_number" maxlength="120" value="<?= e($item['sltda_registration_number']) ?>"></label>
            <label>Registrierung gültig bis<input type="date" name="sltda_registration_expiry" value="<?= e((string)$item['sltda_registration_expiry']) ?>"></label>
        </div><p class="form-help">Luxury ist ein Verkaufssegment. Die Sterne bleiben eine getrennte offizielle Klassifizierung.</p></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Preis & Darstellung</h2><p>Zimmerpreis oder Leistungspreis, Bild und Sichtbarkeit</p></div></div><div class="form-grid thirds">
            <label><span data-price-label><?= $item['type']==='accommodation'?'Doppelzimmerpreis pro Zimmer / Nacht (€)':'Leistungspreis (€)' ?></span><input type="number" min="0" step="0.01" name="price_per_person" value="<?= e($item['price_per_person']) ?>"></label><label data-price-basis-field>Berechnung<select name="price_basis"><option value="per_person"<?= selected('per_person',$item['price_basis']) ?>>Pro Person</option><option value="per_person_night"<?= selected('per_person_night',$item['price_basis']) ?>>Pro Person / Nacht</option><option value="per_booking"<?= selected('per_booking',$item['price_basis']) ?>>Pro Buchung</option><option value="free"<?= selected('free',$item['price_basis']) ?>>Kostenfrei</option><option value="on_request"<?= selected('on_request',$item['price_basis']) ?>>Preis auf Anfrage</option></select></label><label>Sortierung<input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>"></label>
            <label class="check"><input type="checkbox" name="featured" value="1"<?= checked((bool)$item['featured']) ?>> Als Empfehlung markieren</label>
            <label class="span-two">Bild (JPG, PNG oder WEBP · max. 5 MB)<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><?php if($item['image_path']):?><small>Aktuell: <?= e($item['image_path']) ?></small><?php endif;?></label>
            <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool)$item['active']) ?>> Öffentlich aktiv</label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Hotelaufenthalte</h2><p>In welchen Einzelhotel-Bereichen soll dieses Angebot erscheinen?</p></div></div><div class="form-grid">
            <label class="check"><input type="checkbox" name="beach_available" value="1"<?= checked((bool)$item['beach_available']) ?>> Für Strandurlaub verfügbar</label>
            <label class="check"><input type="checkbox" name="ayurveda_available" value="1"<?= checked((bool)$item['ayurveda_available']) ?>> Für Ayurveda-Aufenthalt verfügbar</label>
            <label class="check"><input type="checkbox" name="maldives_available" value="1"<?= checked((bool)$item['maldives_available']) ?>> Für Malediven-Urlaub verfügbar</label>
        </div><p class="form-help">Bei Hotels steuern diese Felder die Hotelauswahl. Bei Ausflügen, Aktivitäten, Shops und Zusatzleistungen steuern sie, in welchem Bereich die Leistung angeboten wird.</p></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Lieferant & interne Kalkulation</h2><p>Diese Angaben werden Kunden nicht angezeigt</p></div></div><div class="form-grid thirds">
            <label>Lieferant / Vertragspartner<input name="supplier_name" maxlength="190" value="<?= e($item['supplier_name']) ?>"></label>
            <label>Vertragspreis (€)<input type="number" min="0" step="0.01" name="contract_price" value="<?= e($item['contract_price']) ?>"></label>
            <label>Provision (%)<input type="number" min="0" max="100" step="0.01" name="commission_percent" value="<?= e($item['commission_percent']) ?>"></label>
            <label class="span-two">Kontakt<textarea name="supplier_contact" rows="3" placeholder="Name, E-Mail, Telefon, WhatsApp"><?= e($item['supplier_contact']) ?></textarea></label>
            <label class="span-two">Interne Hinweise<textarea name="internal_notes" rows="3"><?= e($item['internal_notes']) ?></textarea></label>
        </div></section>
        <section class="admin-card form-card" data-occupancy-section><div class="card-head"><div><h2>Zimmerbelegung & Kinder</h2><p>Regeln für Doppelzimmer im Strand-, Ayurveda- und Malediven-Planer</p></div></div><div class="form-grid thirds">
            <label>Standardgäste pro Zimmer<input type="number" min="1" max="4" name="standard_room_guests" value="<?= (int)$item['standard_room_guests'] ?>"></label>
            <label>Maximal mit Zusatzbett<input type="number" min="1" max="6" name="max_guests_with_extra_bed" value="<?= (int)$item['max_guests_with_extra_bed'] ?>"></label>
            <label>Zusatzbett Erwachsene (%)<input type="number" min="0" max="100" step="0.01" name="extra_bed_percent" value="<?= e($item['extra_bed_percent']) ?>"></label>
            <label>Kinderpreis (%)<input type="number" min="0" max="100" step="0.01" name="child_percent" value="<?= e($item['child_percent']) ?>"></label>
            <label>Kind bis einschließlich Alter<input type="number" min="0" max="17" name="child_max_age" value="<?= (int)$item['child_max_age'] ?>"></label>
            <label>Ayurveda-Nächte<input name="ayurveda_night_options" value="<?= e($item['ayurveda_night_options']) ?>" placeholder="7,14,21,28"><small>Erlaubte Aufenthalte, mit Komma getrennt.</small></label>
        </div><p class="form-help">Standard: Doppelzimmer für 2 Gäste, dritter Gast im Zusatzbett +30 %. Kinder bis 12 Jahre zahlen 50 % des Erwachsenen-Zuschlags und der Verpflegung.</p></section>
        <section class="admin-card form-card meal-plan-admin" data-meal-plan-section><div class="card-head"><div><h2>Verpflegung im Hotel</h2><p>Verfügbare Verpflegungsarten und Zuschlag pro Person und Nacht</p></div></div><div class="meal-plan-table">
            <div class="meal-plan-row meal-plan-head"><span>Aktiv</span><span>Name Deutsch</span><span>Name Englisch</span><span>Zuschlag (€)</span></div>
            <?php foreach($mealPlanDefaults as $code=>$defaults): $mealPlan=$mealPlans[$code]; ?><div class="meal-plan-row">
                <label class="meal-plan-toggle"><input type="checkbox" name="meal_plan_active[<?= e($code) ?>]" value="1"<?= checked((bool)$mealPlan['active']) ?>><span><?= e(str_replace('_',' ',$code)) ?></span></label>
                <input name="meal_plan_name_de[<?= e($code) ?>]" maxlength="120" value="<?= e($mealPlan['name_de']) ?>" required>
                <input name="meal_plan_name_en[<?= e($code) ?>]" maxlength="120" value="<?= e($mealPlan['name_en']) ?>" required>
                <input type="number" min="0" max="5000" step="0.01" name="meal_plan_supplement[<?= e($code) ?>]" value="<?= e($mealPlan['supplement_per_person_night'] ?? $mealPlan['supplement']) ?>">
            </div><?php endforeach; ?>
        </div><p class="form-help">Der Zuschlag wird mit der Anzahl der Reisenden und den Nächten am Reiseziel berechnet. Mindestens eine aktive Verpflegungsart wird empfohlen.</p></section>
        <div class="form-actions"><a href="catalog.php">Abbrechen</a><button class="primary-button" type="submit">Baustein speichern</button></div>
    </form>
</div>
<script>
(function(){var type=document.querySelector('select[name="type"]'),section=document.querySelector('[data-meal-plan-section]'),occupancy=document.querySelector('[data-occupancy-section]'),hotelClass=document.querySelector('[data-accommodation-classification]'),priceLabel=document.querySelector('[data-price-label]'),basis=document.querySelector('[data-price-basis-field]'),classification=document.querySelector('[name="classification_id"]');function update(){var accommodation=type.value==='accommodation';section.hidden=!accommodation;occupancy.hidden=!accommodation;hotelClass.hidden=!accommodation;section.querySelectorAll('input').forEach(function(input){input.disabled=!accommodation;});occupancy.querySelectorAll('input').forEach(function(input){input.disabled=!accommodation;});hotelClass.querySelectorAll('input,select').forEach(function(input){input.disabled=!accommodation;});basis.hidden=accommodation;basis.querySelector('select').disabled=accommodation;priceLabel.textContent=accommodation?'Doppelzimmerpreis pro Zimmer / Nacht (€)':'Leistungspreis (€)';Array.from(classification.options).forEach(function(option,index){if(index===0)return;option.hidden=option.getAttribute('data-item-type')!==type.value;});var current=classification.options[classification.selectedIndex];if(current&&current.hidden)classification.value='0';}type.addEventListener('change',update);update();})();
</script>
<?php require __DIR__.'/_footer.php';?>
