<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
$item = [
    'id'=>0,'destination_id'=>(int)($_GET['destination_id'] ?? 0),'type'=>'accommodation',
    'name_de'=>'','name_en'=>'','description_de'=>'','description_en'=>'','meta_de'=>'','meta_en'=>'',
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

if (request_is_post()) {
    verify_csrf();
    try {
        $type = in_array($_POST['type'] ?? '', ['accommodation','sight','activity','shop','service'], true) ? $_POST['type'] : 'activity';
        $priceBasis = in_array($_POST['price_basis'] ?? '', ['per_person','per_person_night','per_booking'], true) ? $_POST['price_basis'] : 'per_person';
        $image=upload_image($_FILES['image']??[],(string)$item['image_path']);
        $values=[
            (int)($_POST['destination_id']??0),$type,
            trim((string)($_POST['name_de']??'')),trim((string)($_POST['name_en']??'')),
            trim((string)($_POST['description_de']??'')),trim((string)($_POST['description_en']??'')),
            trim((string)($_POST['meta_de']??'')),trim((string)($_POST['meta_en']??'')),
            max(0,(float)($_POST['price_per_person']??0)),$type==='accommodation'?'per_person_night':$priceBasis,
            isset($_POST['beach_available'])?1:0,isset($_POST['ayurveda_available'])?1:0,isset($_POST['maldives_available'])?1:0,
            max(1,min(4,(int)($_POST['standard_room_guests']??2))),max(1,min(6,(int)($_POST['max_guests_with_extra_bed']??3))),
            max(0,min(100,(float)($_POST['extra_bed_percent']??30))),max(0,min(100,(float)($_POST['child_percent']??50))),
            max(0,min(17,(int)($_POST['child_max_age']??12))),substr(preg_replace('/[^0-9,]/','',(string)($_POST['ayurveda_night_options']??'7,14,21,28')),0,80),
            $image,isset($_POST['featured'])?1:0,
            isset($_POST['active'])?1:0,(int)($_POST['sort_order']??0),
        ];
        if (!$values[0] || $values[2]==='' || $values[3]==='') throw new RuntimeException('Reiseziel und beide Namen sind erforderlich.');
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            $values[]=$id;
            $pdo->prepare('UPDATE catalog_items SET destination_id=?,type=?,name_de=?,name_en=?,description_de=?,description_en=?,meta_de=?,meta_en=?,price_per_person=?,price_basis=?,beach_available=?,ayurveda_available=?,maldives_available=?,standard_room_guests=?,max_guests_with_extra_bed=?,extra_bed_percent=?,child_percent=?,child_max_age=?,ayurveda_night_options=?,image_path=?,featured=?,active=?,sort_order=? WHERE id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,price_basis,beach_available,ayurveda_available,maldives_available,standard_room_guests,max_guests_with_extra_bed,extra_bed_percent,child_percent,child_max_age,ayurveda_night_options,image_path,featured,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
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

$typeLabels=['accommodation'=>'Unterkunft','sight'=>'Sehenswürdigkeit','activity'=>'Aktivität','shop'=>'Shop / Einkaufsstopp','service'=>'Zusatzleistung / Ayurveda-Paket'];
$adminPage='catalog';$adminTitle=$id?'Baustein bearbeiten':'Baustein erstellen';require __DIR__.'/_header.php';
?>
<div class="admin-content narrow">
    <a class="back-admin" href="catalog.php">← Reisebausteine</a>
    <div class="page-heading compact"><div><p>ANGEBOT</p><h1><?= $id?'Reisebaustein bearbeiten':'Neuer Reisebaustein' ?></h1><span>Unterkunft, Sehenswürdigkeit, Aktivität oder lokaler Shop.</span></div></div>
    <form class="editor-form" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Zuordnung</h2><p>Ort, Reiseziel und Kategorie</p></div></div><div class="form-grid">
            <div class="form-field"><div class="field-label-row"><label for="destination-id">Ort / Reiseziel</label><a class="inline-create" href="destination-edit.php?return_to=item-edit">＋ Neuen Ort hinzufügen</a></div><select id="destination-id" name="destination_id" required><option value="">Bitte wählen</option><?php foreach($destinations as $destination):?><option value="<?= (int)$destination['id'] ?>"<?= selected($destination['id'],$item['destination_id']) ?>><?= e($destination['country_code']==='MV'?'Malediven · ':'Sri Lanka · ') ?><?= e($destination['name_de']) ?></option><?php endforeach;?></select><small>Der neue Ort erscheint anschließend automatisch in dieser Liste.</small></div>
            <label>Typ<select name="type"><?php foreach($typeLabels as $value=>$label):?><option value="<?= e($value) ?>"<?= selected($value,$item['type']) ?>><?= e($label) ?></option><?php endforeach;?></select></label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Titel & Beschreibung</h2><p>Deutsch und Englisch</p></div></div><div class="form-grid">
            <label>Name Deutsch<input name="name_de" required value="<?= e($item['name_de']) ?>"></label><label>Name Englisch<input name="name_en" required value="<?= e($item['name_en']) ?>"></label>
            <label>Beschreibung Deutsch<textarea name="description_de" rows="4" required><?= e($item['description_de']) ?></textarea></label><label>Beschreibung Englisch<textarea name="description_en" rows="4" required><?= e($item['description_en']) ?></textarea></label>
            <label>Kurzinfo Deutsch<input name="meta_de" value="<?= e($item['meta_de']) ?>" placeholder="z. B. 3 Stunden · Eintritt inklusive"></label><label>Kurzinfo Englisch<input name="meta_en" value="<?= e($item['meta_en']) ?>"></label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Preis & Darstellung</h2><p>Zimmerpreis oder Leistungspreis, Bild und Sichtbarkeit</p></div></div><div class="form-grid thirds">
            <label><span data-price-label><?= $item['type']==='accommodation'?'Doppelzimmerpreis pro Zimmer / Nacht (€)':'Leistungspreis (€)' ?></span><input type="number" min="0" step="0.01" name="price_per_person" value="<?= e($item['price_per_person']) ?>"></label><label data-price-basis-field>Berechnung<select name="price_basis"><option value="per_person"<?= selected('per_person',$item['price_basis']) ?>>Pro Person</option><option value="per_person_night"<?= selected('per_person_night',$item['price_basis']) ?>>Pro Person / Nacht</option><option value="per_booking"<?= selected('per_booking',$item['price_basis']) ?>>Pro Buchung</option></select></label><label>Sortierung<input type="number" name="sort_order" value="<?= (int)$item['sort_order'] ?>"></label>
            <label class="check"><input type="checkbox" name="featured" value="1"<?= checked((bool)$item['featured']) ?>> Als Empfehlung markieren</label>
            <label class="span-two">Bild (JPG, PNG oder WEBP · max. 5 MB)<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><?php if($item['image_path']):?><small>Aktuell: <?= e($item['image_path']) ?></small><?php endif;?></label>
            <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool)$item['active']) ?>> Öffentlich aktiv</label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Hotelaufenthalte</h2><p>In welchen Einzelhotel-Bereichen soll dieses Angebot erscheinen?</p></div></div><div class="form-grid">
            <label class="check"><input type="checkbox" name="beach_available" value="1"<?= checked((bool)$item['beach_available']) ?>> Für Strandurlaub verfügbar</label>
            <label class="check"><input type="checkbox" name="ayurveda_available" value="1"<?= checked((bool)$item['ayurveda_available']) ?>> Für Ayurveda-Aufenthalt verfügbar</label>
            <label class="check"><input type="checkbox" name="maldives_available" value="1"<?= checked((bool)$item['maldives_available']) ?>> Für Malediven-Urlaub verfügbar</label>
        </div><p class="form-help">Bei Hotels steuern diese Felder die Hotelauswahl. Bei Ausflügen, Aktivitäten, Shops und Zusatzleistungen steuern sie, in welchem Bereich die Leistung angeboten wird.</p></section>
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
(function(){var type=document.querySelector('select[name="type"]'),section=document.querySelector('[data-meal-plan-section]'),occupancy=document.querySelector('[data-occupancy-section]'),priceLabel=document.querySelector('[data-price-label]'),basis=document.querySelector('[data-price-basis-field]');function update(){var accommodation=type.value==='accommodation';section.hidden=!accommodation;occupancy.hidden=!accommodation;section.querySelectorAll('input').forEach(function(input){input.disabled=!accommodation;});occupancy.querySelectorAll('input').forEach(function(input){input.disabled=!accommodation;});basis.hidden=accommodation;basis.querySelector('select').disabled=accommodation;priceLabel.textContent=accommodation?'Doppelzimmerpreis pro Zimmer / Nacht (€)':'Leistungspreis (€)';}type.addEventListener('change',update);update();})();
</script>
<?php require __DIR__.'/_footer.php';?>
