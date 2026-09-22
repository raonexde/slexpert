<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('tours', request_is_post());

$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$duplicateId = $id === 0 ? max(0, (int)($_GET['duplicate'] ?? 0)) : 0;
$tour = [
    'id'=>0,'slug'=>'','category'=>'discovery','title_de'=>'','title_en'=>'','eyebrow_de'=>'','eyebrow_en'=>'',
    'intro_de'=>'','intro_en'=>'','description_de'=>'','description_en'=>'','duration_nights'=>0,
    'min_travelers'=>1,'max_travelers'=>20,'price_from'=>0,'season_de'=>'Ganzjährig','season_en'=>'Year-round',
    'difficulty_de'=>'Angenehm','difficulty_en'=>'Easy','includes_de'=>'','includes_en'=>'','excludes_de'=>'','excludes_en'=>'',
    'image_path'=>'','featured'=>0,'active'=>0,'sort_order'=>100,
];
$stops = [];
$prices = [];
$hiking = [
    'enabled'=>0,'distance_km'=>0,'elevation_gain_m'=>0,'elevation_loss_m'=>0,'min_elevation_m'=>0,'max_elevation_m'=>0,
    'moving_minutes'=>0,'total_minutes'=>0,'route_type_de'=>'Strecke · kein Rundweg','route_type_en'=>'One way · not a loop',
    'start_location_de'=>'','start_location_en'=>'','end_location_de'=>'','end_location_en'=>'','guide_required'=>1,'source_url'=>'','notes_de'=>'','notes_en'=>'',
];

$loadId = $id > 0 ? $id : $duplicateId;
if ($loadId > 0) {
    $stmt = db()->prepare('SELECT * FROM tour_templates WHERE id=?');
    $stmt->execute([$loadId]);
    $loaded = $stmt->fetch();
    if (!$loaded) { http_response_code(404); exit('Tour not found'); }
    $tour = $loaded;
    $stopStmt = db()->prepare('SELECT s.*,GROUP_CONCAT(si.catalog_item_id ORDER BY si.sort_order,si.id) item_ids FROM tour_template_stops s LEFT JOIN tour_template_stop_items si ON si.tour_template_stop_id=s.id WHERE s.tour_template_id=? GROUP BY s.id ORDER BY s.sort_order,s.id');
    $stopStmt->execute([$loadId]);
    foreach ($stopStmt->fetchAll() as $stop) {
        $stop['item_ids'] = $stop['item_ids'] === null ? [] : array_map('intval', explode(',', (string)$stop['item_ids']));
        $stops[] = $stop;
    }
    $priceStmt = db()->prepare('SELECT * FROM tour_template_prices WHERE tour_template_id=? ORDER BY sort_order,id');
    $priceStmt->execute([$loadId]);
    $prices = $priceStmt->fetchAll();
    $hikingStmt = db()->prepare('SELECT * FROM tour_hiking_details WHERE tour_template_id=?');
    $hikingStmt->execute([$loadId]);
    $loadedHiking = $hikingStmt->fetch();
    if ($loadedHiking) $hiking = array_merge($hiking,$loadedHiking,['enabled'=>1]);
    if ($duplicateId > 0) {
        $tour['id'] = 0;
        $tour['slug'] = $tour['slug'] . '-copy';
        $tour['title_de'] .= ' – Kopie';
        $tour['title_en'] .= ' – Copy';
        $tour['active'] = 0;
        $tour['featured'] = 0;
    }
}

$destinations = db()->query("SELECT id,name_de,name_en,active FROM destinations WHERE country_code='LK' ORDER BY active DESC,sort_order,name_de")->fetchAll();
$catalogItems = db()->query("SELECT c.id,c.destination_id,c.type,c.name_de,c.name_en,c.active FROM catalog_items c JOIN destinations d ON d.id=c.destination_id WHERE d.country_code='LK' ORDER BY c.destination_id,(c.type='accommodation') DESC,c.type,c.sort_order,c.name_de")->fetchAll();
$catalogDestinationById = [];
foreach ($catalogItems as $catalogItem) $catalogDestinationById[(int)$catalogItem['id']] = (int)$catalogItem['destination_id'];

if (request_is_post()) {
    verify_csrf();
    $pdo = db();
    try {
        $titleDe = trim((string)($_POST['title_de'] ?? ''));
        $titleEn = trim((string)($_POST['title_en'] ?? ''));
        if ($titleDe === '' || $titleEn === '') throw new RuntimeException('Deutscher und englischer Titel sind erforderlich.');
        $category = (string)($_POST['category'] ?? 'discovery');
        $categories = ['winter','summer','sport','culture','discovery','backpacker','ayurveda','family','luxury'];
        if (!in_array($category, $categories, true)) $category = 'discovery';
        $slug = slugify((string)($_POST['slug'] ?? $titleDe));
        if ($slug === '') $slug = slugify($titleDe);
        $candidate = $slug;
        $suffix = 2;
        $slugCheck = $pdo->prepare('SELECT id FROM tour_templates WHERE slug=? AND id<>?');
        do {
            $slugCheck->execute([$candidate,$id]);
            if (!$slugCheck->fetchColumn()) break;
            $candidate = substr($slug,0,150) . '-' . $suffix++;
        } while ($suffix < 1000);
        $slug = $candidate;

        $destinationIds = is_array($_POST['destination_id'] ?? null) ? $_POST['destination_id'] : [];
        $nightValues = is_array($_POST['stop_nights'] ?? null) ? $_POST['stop_nights'] : [];
        $titleDeValues = is_array($_POST['stop_title_de'] ?? null) ? $_POST['stop_title_de'] : [];
        $titleEnValues = is_array($_POST['stop_title_en'] ?? null) ? $_POST['stop_title_en'] : [];
        $descriptionDeValues = is_array($_POST['stop_description_de'] ?? null) ? $_POST['stop_description_de'] : [];
        $descriptionEnValues = is_array($_POST['stop_description_en'] ?? null) ? $_POST['stop_description_en'] : [];
        $itemValues = is_array($_POST['stop_items'] ?? null) ? $_POST['stop_items'] : [];
        $validStops = [];
        $durationNights = 0;
        foreach ($destinationIds as $index=>$destinationIdRaw) {
            $destinationId = (int)$destinationIdRaw;
            if ($destinationId < 1) continue;
            $nights = max(0,min(28,(int)($nightValues[$index] ?? 1)));
            $durationNights += $nights;
            $selectedItems = [];
            foreach ((array)($itemValues[$index] ?? []) as $catalogItemIdRaw) {
                $catalogItemId = (int)$catalogItemIdRaw;
                if (($catalogDestinationById[$catalogItemId] ?? 0) === $destinationId) $selectedItems[] = $catalogItemId;
            }
            $validStops[] = [
                'destination_id'=>$destinationId,'nights'=>$nights,
                'title_de'=>substr(trim((string)($titleDeValues[$index] ?? '')),0,190),
                'title_en'=>substr(trim((string)($titleEnValues[$index] ?? '')),0,190),
                'description_de'=>trim((string)($descriptionDeValues[$index] ?? '')),
                'description_en'=>trim((string)($descriptionEnValues[$index] ?? '')),
                'item_ids'=>array_values(array_unique($selectedItems)),
            ];
        }
        if (!$validStops) throw new RuntimeException('Mindestens eine Station ist erforderlich.');

        $image = upload_image($_FILES['image'] ?? [], (string)($tour['image_path'] ?? ''));
        $values = [
            $slug,$category,$titleDe,$titleEn,trim((string)($_POST['eyebrow_de'] ?? '')),trim((string)($_POST['eyebrow_en'] ?? '')),
            trim((string)($_POST['intro_de'] ?? '')),trim((string)($_POST['intro_en'] ?? '')),trim((string)($_POST['description_de'] ?? '')),trim((string)($_POST['description_en'] ?? '')),
            $durationNights,max(1,min(50,(int)($_POST['min_travelers'] ?? 1))),max(1,min(50,(int)($_POST['max_travelers'] ?? 20))),
            max(0,round((float)str_replace(',','.',(string)($_POST['price_from'] ?? '0')),2)),trim((string)($_POST['season_de'] ?? '')),trim((string)($_POST['season_en'] ?? '')),
            trim((string)($_POST['difficulty_de'] ?? '')),trim((string)($_POST['difficulty_en'] ?? '')),trim((string)($_POST['includes_de'] ?? '')),trim((string)($_POST['includes_en'] ?? '')),
            trim((string)($_POST['excludes_de'] ?? '')),trim((string)($_POST['excludes_en'] ?? '')),$image,isset($_POST['featured'])?1:0,isset($_POST['active'])?1:0,
            max(-9999,min(9999,(int)($_POST['sort_order'] ?? 0))),
        ];

        $pdo->beginTransaction();
        if ($id > 0) {
            $values[] = $id;
            $pdo->prepare('UPDATE tour_templates SET slug=?,category=?,title_de=?,title_en=?,eyebrow_de=?,eyebrow_en=?,intro_de=?,intro_en=?,description_de=?,description_en=?,duration_nights=?,min_travelers=?,max_travelers=?,price_from=?,season_de=?,season_en=?,difficulty_de=?,difficulty_en=?,includes_de=?,includes_en=?,excludes_de=?,excludes_en=?,image_path=?,featured=?,active=?,sort_order=? WHERE id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO tour_templates (slug,category,title_de,title_en,eyebrow_de,eyebrow_en,intro_de,intro_en,description_de,description_en,duration_nights,min_travelers,max_travelers,price_from,season_de,season_en,difficulty_de,difficulty_en,includes_de,includes_en,excludes_de,excludes_en,image_path,featured,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM tour_template_stops WHERE tour_template_id=?')->execute([$id]);
        $insertStop = $pdo->prepare('INSERT INTO tour_template_stops (tour_template_id,destination_id,day_start,day_end,nights,title_de,title_en,description_de,description_en,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $insertItem = $pdo->prepare('INSERT INTO tour_template_stop_items (tour_template_stop_id,catalog_item_id,included,sort_order) VALUES (?,?,1,?)');
        $day = 1;
        foreach ($validStops as $position=>$stop) {
            $dayEnd = $day + max(1,$stop['nights']) - 1;
            $insertStop->execute([$id,$stop['destination_id'],$day,$dayEnd,$stop['nights'],$stop['title_de'],$stop['title_en'],$stop['description_de'],$stop['description_en'],$position*10]);
            $stopId = (int)$pdo->lastInsertId();
            foreach ($stop['item_ids'] as $itemPosition=>$catalogItemId) $insertItem->execute([$stopId,$catalogItemId,$itemPosition*10]);
            $day = $dayEnd + 1;
        }

        $pdo->prepare('DELETE FROM tour_template_prices WHERE tour_template_id=?')->execute([$id]);
        $labelsDe = is_array($_POST['price_label_de'] ?? null) ? $_POST['price_label_de'] : [];
        $labelsEn = is_array($_POST['price_label_en'] ?? null) ? $_POST['price_label_en'] : [];
        $validFrom = is_array($_POST['price_valid_from'] ?? null) ? $_POST['price_valid_from'] : [];
        $validTo = is_array($_POST['price_valid_to'] ?? null) ? $_POST['price_valid_to'] : [];
        $priceMins = is_array($_POST['price_min_travelers'] ?? null) ? $_POST['price_min_travelers'] : [];
        $priceMaxes = is_array($_POST['price_max_travelers'] ?? null) ? $_POST['price_max_travelers'] : [];
        $priceAmounts = is_array($_POST['price_per_person'] ?? null) ? $_POST['price_per_person'] : [];
        $priceActive = is_array($_POST['price_active'] ?? null) ? $_POST['price_active'] : [];
        $insertPrice = $pdo->prepare('INSERT INTO tour_template_prices (tour_template_id,label_de,label_en,valid_from,valid_to,min_travelers,max_travelers,price_per_person,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach ($priceAmounts as $index=>$amountRaw) {
            $amount = max(0,round((float)str_replace(',','.',(string)$amountRaw),2));
            $labelDe = substr(trim((string)($labelsDe[$index] ?? '')),0,160);
            $labelEn = substr(trim((string)($labelsEn[$index] ?? '')),0,160);
            if ($amount <= 0 && $labelDe === '' && $labelEn === '') continue;
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($validFrom[$index] ?? '')) ? $validFrom[$index] : null;
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($validTo[$index] ?? '')) ? $validTo[$index] : null;
            $insertPrice->execute([$id,$labelDe,$labelEn,$from,$to,max(1,min(50,(int)($priceMins[$index] ?? 1))),max(1,min(50,(int)($priceMaxes[$index] ?? 20))),$amount,isset($priceActive[$index])?1:0,$index*10]);
        }

        if (isset($_POST['is_hiking_itinerary'])) {
            $sourceUrl = substr(trim((string)($_POST['hiking_source_url'] ?? '')),0,500);
            if ($sourceUrl !== '' && !filter_var($sourceUrl,FILTER_VALIDATE_URL)) throw new RuntimeException('Die Quellen-URL der Wanderung ist ungültig.');
            $hikingValues = [
                $id,
                max(0,round((float)str_replace(',','.',(string)($_POST['hiking_distance_km'] ?? '0')),2)),
                max(0,min(65535,(int)($_POST['hiking_elevation_gain_m'] ?? 0))),
                max(0,min(65535,(int)($_POST['hiking_elevation_loss_m'] ?? 0))),
                max(0,min(65535,(int)($_POST['hiking_min_elevation_m'] ?? 0))),
                max(0,min(65535,(int)($_POST['hiking_max_elevation_m'] ?? 0))),
                max(0,min(65535,(int)($_POST['hiking_moving_minutes'] ?? 0))),
                max(0,min(65535,(int)($_POST['hiking_total_minutes'] ?? 0))),
                substr(trim((string)($_POST['hiking_route_type_de'] ?? '')),0,120),
                substr(trim((string)($_POST['hiking_route_type_en'] ?? '')),0,120),
                substr(trim((string)($_POST['hiking_start_location_de'] ?? '')),0,190),
                substr(trim((string)($_POST['hiking_start_location_en'] ?? '')),0,190),
                substr(trim((string)($_POST['hiking_end_location_de'] ?? '')),0,190),
                substr(trim((string)($_POST['hiking_end_location_en'] ?? '')),0,190),
                isset($_POST['hiking_guide_required'])?1:0,
                $sourceUrl,
                trim((string)($_POST['hiking_notes_de'] ?? '')),
                trim((string)($_POST['hiking_notes_en'] ?? '')),
            ];
            $pdo->prepare('INSERT INTO tour_hiking_details (tour_template_id,distance_km,elevation_gain_m,elevation_loss_m,min_elevation_m,max_elevation_m,moving_minutes,total_minutes,route_type_de,route_type_en,start_location_de,start_location_en,end_location_de,end_location_en,guide_required,source_url,notes_de,notes_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE distance_km=VALUES(distance_km),elevation_gain_m=VALUES(elevation_gain_m),elevation_loss_m=VALUES(elevation_loss_m),min_elevation_m=VALUES(min_elevation_m),max_elevation_m=VALUES(max_elevation_m),moving_minutes=VALUES(moving_minutes),total_minutes=VALUES(total_minutes),route_type_de=VALUES(route_type_de),route_type_en=VALUES(route_type_en),start_location_de=VALUES(start_location_de),start_location_en=VALUES(start_location_en),end_location_de=VALUES(end_location_de),end_location_en=VALUES(end_location_en),guide_required=VALUES(guide_required),source_url=VALUES(source_url),notes_de=VALUES(notes_de),notes_en=VALUES(notes_en)')->execute($hikingValues);
        } else {
            $pdo->prepare('DELETE FROM tour_hiking_details WHERE tour_template_id=?')->execute([$id]);
        }
        $pdo->commit();
        flash('success','Reisevorlage wurde gespeichert.');
        redirect('admin/tour-edit.php?id=' . $id);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error','Speichern fehlgeschlagen: ' . $exception->getMessage());
        redirect('admin/tour-edit.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

if (!$stops) $stops[] = ['destination_id'=>'','nights'=>1,'title_de'=>'','title_en'=>'','description_de'=>'','description_en'=>'','item_ids'=>[]];
if (!$prices) $prices[] = ['label_de'=>'Richtpreis ab','label_en'=>'Guide price from','valid_from'=>'','valid_to'=>'','min_travelers'=>1,'max_travelers'=>20,'price_per_person'=>$tour['price_from'],'active'=>1];

$categoryLabels = ['winter'=>'Winterurlaub','summer'=>'Sommerurlaub','sport'=>'Sport & Abenteuer','culture'=>'Kultur','discovery'=>'Kennenlernen','backpacker'=>'Backpacker','ayurveda'=>'Ayurveda plus','family'=>'Familie','luxury'=>'Luxus'];
$adminPage = 'tours';
$adminTitle = $id > 0 ? 'Reise bearbeiten' : 'Reise erstellen';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <a class="back-admin" href="tours.php">← Fertige Reisen</a>
    <div class="page-heading compact"><div><p>REISEVORLAGE</p><h1><?= $id > 0 ? 'Reise bearbeiten' : 'Neue Reise erstellen' ?></h1><span>Alle Texte, Etappen, Leistungen und Saisonpreise sind später wieder änderbar.</span></div><?php if ($id > 0): ?><div class="heading-actions"><a class="secondary-button" target="_blank" href="<?= e(url('tour.php?slug=' . rawurlencode((string)$tour['slug']))) ?>">Vorschau ↗</a><a class="secondary-button" href="tour-edit.php?duplicate=<?= $id ?>">Duplizieren</a></div><?php endif; ?></div>
    <form class="editor-form" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <section class="admin-card form-card"><div class="card-head"><div><h2>Grunddaten</h2><p>Titel, Kategorie und Veröffentlichung</p></div></div><div class="form-grid thirds">
            <label>Titel Deutsch<input name="title_de" required maxlength="190" value="<?= e($tour['title_de']) ?>"></label>
            <label>Titel Englisch<input name="title_en" required maxlength="190" value="<?= e($tour['title_en']) ?>"></label>
            <label>URL-Slug<input name="slug" maxlength="160" value="<?= e($tour['slug']) ?>" placeholder="wird automatisch erzeugt"></label>
            <label>Kategorie<select name="category"><?php foreach ($categoryLabels as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($value,$tour['category']) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Richtpreis ab / Person (€)<input type="number" name="price_from" min="0" step="0.01" value="<?= e($tour['price_from']) ?>"></label>
            <label>Sortierung<input type="number" name="sort_order" min="-9999" max="9999" value="<?= (int)$tour['sort_order'] ?>"></label>
            <label>Mindestens Reisende<input type="number" name="min_travelers" min="1" max="50" value="<?= (int)$tour['min_travelers'] ?>"></label>
            <label>Maximal Reisende<input type="number" name="max_travelers" min="1" max="50" value="<?= (int)$tour['max_travelers'] ?>"></label>
            <label class="check"><input type="checkbox" name="featured" value="1"<?= checked((bool)$tour['featured']) ?>> Auf der Startseite empfehlen</label>
            <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool)$tour['active']) ?>> Öffentlich veröffentlicht</label>
            <label class="span-two">Titelbild (JPG, PNG oder WEBP)<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><?php if ($tour['image_path']): ?><small>Aktuell: <?= e($tour['image_path']) ?></small><?php endif; ?></label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Öffentliche Beschreibung</h2><p>Deutsch und Englisch</p></div></div><div class="form-grid">
            <label>Kicker Deutsch<input name="eyebrow_de" maxlength="190" value="<?= e($tour['eyebrow_de']) ?>"></label><label>Kicker Englisch<input name="eyebrow_en" maxlength="190" value="<?= e($tour['eyebrow_en']) ?>"></label>
            <label>Kurzeinleitung Deutsch<textarea name="intro_de" rows="3"><?= e($tour['intro_de']) ?></textarea></label><label>Kurzeinleitung Englisch<textarea name="intro_en" rows="3"><?= e($tour['intro_en']) ?></textarea></label>
            <label>Beschreibung Deutsch<textarea name="description_de" rows="6"><?= e($tour['description_de']) ?></textarea></label><label>Beschreibung Englisch<textarea name="description_en" rows="6"><?= e($tour['description_en']) ?></textarea></label>
            <label>Beste Reisezeit Deutsch<input name="season_de" value="<?= e($tour['season_de']) ?>"></label><label>Beste Reisezeit Englisch<input name="season_en" value="<?= e($tour['season_en']) ?>"></label>
            <label>Reiseart Deutsch<input name="difficulty_de" value="<?= e($tour['difficulty_de']) ?>"></label><label>Reiseart Englisch<input name="difficulty_en" value="<?= e($tour['difficulty_en']) ?>"></label>
            <label>Enthalten Deutsch<textarea name="includes_de" rows="6" placeholder="Eine Leistung pro Zeile"><?= e($tour['includes_de']) ?></textarea></label><label>Included English<textarea name="includes_en" rows="6" placeholder="One item per line"><?= e($tour['includes_en']) ?></textarea></label>
            <label>Nicht enthalten Deutsch<textarea name="excludes_de" rows="5" placeholder="Eine Leistung pro Zeile"><?= e($tour['excludes_de']) ?></textarea></label><label>Excluded English<textarea name="excludes_en" rows="5" placeholder="One item per line"><?= e($tour['excludes_en']) ?></textarea></label>
        </div></section>
        <section class="admin-card form-card hiking-editor"><div class="card-head"><div><h2>Wanderroute</h2><p>Optionale Wanderdaten für Detailseite und Druckversion</p></div><label class="check"><input type="checkbox" name="is_hiking_itinerary" value="1"<?= checked((bool)$hiking['enabled']) ?>> Als Wanderreise anzeigen</label></div><div class="form-grid thirds">
            <label>Distanz (km)<input type="number" name="hiking_distance_km" min="0" step="0.01" value="<?= e($hiking['distance_km']) ?>"></label>
            <label>Aufstieg (m)<input type="number" name="hiking_elevation_gain_m" min="0" step="1" value="<?= (int)$hiking['elevation_gain_m'] ?>"></label>
            <label>Abstieg (m)<input type="number" name="hiking_elevation_loss_m" min="0" step="1" value="<?= (int)$hiking['elevation_loss_m'] ?>"></label>
            <label>Niedrigster Punkt (m)<input type="number" name="hiking_min_elevation_m" min="0" step="1" value="<?= (int)$hiking['min_elevation_m'] ?>"></label>
            <label>Höchster Punkt (m)<input type="number" name="hiking_max_elevation_m" min="0" step="1" value="<?= (int)$hiking['max_elevation_m'] ?>"></label>
            <label>Bewegungszeit (Min.)<input type="number" name="hiking_moving_minutes" min="0" step="1" value="<?= (int)$hiking['moving_minutes'] ?>"></label>
            <label>Gesamtzeit (Min.)<input type="number" name="hiking_total_minutes" min="0" step="1" value="<?= (int)$hiking['total_minutes'] ?>"></label>
            <label>Routentyp Deutsch<input name="hiking_route_type_de" maxlength="120" value="<?= e($hiking['route_type_de']) ?>"></label>
            <label>Route type English<input name="hiking_route_type_en" maxlength="120" value="<?= e($hiking['route_type_en']) ?>"></label>
            <label>Start Deutsch<input name="hiking_start_location_de" maxlength="190" value="<?= e($hiking['start_location_de']) ?>"></label>
            <label>Start English<input name="hiking_start_location_en" maxlength="190" value="<?= e($hiking['start_location_en']) ?>"></label>
            <label>Ziel Deutsch<input name="hiking_end_location_de" maxlength="190" value="<?= e($hiking['end_location_de']) ?>"></label>
            <label>Finish English<input name="hiking_end_location_en" maxlength="190" value="<?= e($hiking['end_location_en']) ?>"></label>
            <label class="check"><input type="checkbox" name="hiking_guide_required" value="1"<?= checked((bool)$hiking['guide_required']) ?>> Wanderführer erforderlich</label>
            <label class="span-two">Quelle / Referenzroute<input type="url" name="hiking_source_url" maxlength="500" value="<?= e($hiking['source_url']) ?>" placeholder="https://..."></label>
            <label>Hinweise Deutsch<textarea name="hiking_notes_de" rows="5"><?= e($hiking['notes_de']) ?></textarea></label>
            <label>Notes English<textarea name="hiking_notes_en" rows="5"><?= e($hiking['notes_en']) ?></textarea></label>
        </div><p class="form-help">Die Google-Karte zeigt bei Wanderreisen nur die Region. Die exakte GPS-Spur bleibt über die Quellen-URL erreichbar, damit keine Straßenroute als Wanderweg dargestellt wird.</p></section>
        <section class="admin-card tour-route-builder"><div class="card-head"><div><h2>Reiseverlauf</h2><p>Etappen verschieben, Nächte ändern und Hotels, Sehenswürdigkeiten, Aktivitäten oder Shops hinzufügen.</p></div><button class="secondary-button" type="button" data-add-stop>＋ Station</button></div><div class="tour-stop-list" data-stop-list></div><p class="form-help">Eine Unterkunft und mehrere weitere Leistungen können pro Station vorausgewählt werden. 0 Nächte sind für reine Zwischenstopps möglich.</p></section>
        <section class="admin-card tour-price-builder"><div class="card-head"><div><h2>Saisonpreise</h2><p>Optionale Preisstaffeln pro Person nach Zeitraum und Gruppengröße.</p></div><button class="secondary-button" type="button" data-add-price>＋ Preiszeile</button></div><div class="tour-price-list" data-price-list></div></section>
        <div class="form-actions"><a href="tours.php">Abbrechen</a><button class="primary-button" type="submit">Reisevorlage speichern</button></div>
    </form>
</div>
<script>window.TOUR_EDITOR_DATA=<?= json_encode(['destinations'=>$destinations,'items'=>$catalogItems,'stops'=>$stops,'prices'=>$prices],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="<?= e(asset('js/admin-tours.js')) ?>"></script>
<?php require __DIR__ . '/_footer.php'; ?>
