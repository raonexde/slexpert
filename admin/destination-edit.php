<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('catalog', request_is_post());
$id = (int)($_GET['id'] ?? 0);
$returnTo = (string)($_POST['return_to'] ?? $_GET['return_to'] ?? '');
$returnTo = !$id && $returnTo === 'item-edit' ? 'item-edit' : '';
$destination = [
    'id'=>0,'country_code'=>'LK','slug'=>'','code'=>'','name_de'=>'','name_en'=>'','region_de'=>'','region_en'=>'',
    'intro_de'=>'','intro_en'=>'','default_nights'=>2,'price_from'=>0,'accent'=>'leaf',
    'image_path'=>'','latitude'=>'','longitude'=>'','google_place_id'=>'','sort_order'=>0,'active'=>1,
];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM destinations WHERE id=?');
    $stmt->execute([$id]);
    $destination = $stmt->fetch() ?: $destination;
    if (!(int)$destination['id']) { http_response_code(404); exit('Destination not found'); }
}

if (request_is_post()) {
    verify_csrf();
    try {
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?: '';
        $slug = trim($slug, '-');
        $image = upload_image($_FILES['image'] ?? [], (string)$destination['image_path']);
        $latitudeRaw = trim((string)($_POST['latitude'] ?? ''));
        $longitudeRaw = trim((string)($_POST['longitude'] ?? ''));
        if ($latitudeRaw === '' || $longitudeRaw === '') throw new RuntimeException('Breiten- und Längengrad sind für die Kartenroute erforderlich.');
        $latitude = $latitudeRaw === '' ? null : filter_var($latitudeRaw, FILTER_VALIDATE_FLOAT);
        $longitude = $longitudeRaw === '' ? null : filter_var($longitudeRaw, FILTER_VALIDATE_FLOAT);
        if ($latitudeRaw !== '' && ($latitude === false || $latitude < -90 || $latitude > 90)) throw new RuntimeException('Der Breitengrad muss zwischen -90 und 90 liegen.');
        if ($longitudeRaw !== '' && ($longitude === false || $longitude < -180 || $longitude > 180)) throw new RuntimeException('Der Längengrad muss zwischen -180 und 180 liegen.');
        $countryCode = in_array($_POST['country_code'] ?? '', ['LK','MV'], true) ? (string)$_POST['country_code'] : 'LK';
        $values = [
            $countryCode, $slug, strtoupper(substr(trim((string)($_POST['code'] ?? '')),0,8)),
            trim((string)($_POST['name_de'] ?? '')), trim((string)($_POST['name_en'] ?? '')),
            trim((string)($_POST['region_de'] ?? '')), trim((string)($_POST['region_en'] ?? '')),
            trim((string)($_POST['intro_de'] ?? '')), trim((string)($_POST['intro_en'] ?? '')),
            max(0,min(14,(int)($_POST['default_nights'] ?? 2))), max(0,(float)($_POST['price_from'] ?? 0)),
            in_array($_POST['accent'] ?? '', ['leaf','ochre','mist','clay','ocean','sand'], true) ? $_POST['accent'] : 'leaf',
            $image, $latitude, $longitude, substr(trim((string)($_POST['google_place_id'] ?? '')), 0, 255),
            (int)($_POST['sort_order'] ?? 0), isset($_POST['active']) ? 1 : 0,
        ];
        if ($slug === '' || $values[3] === '' || $values[4] === '') throw new RuntimeException('Slug and both names are required.');
        if ($id) {
            $values[] = $id;
            db()->prepare('UPDATE destinations SET country_code=?,slug=?,code=?,name_de=?,name_en=?,region_de=?,region_en=?,intro_de=?,intro_en=?,default_nights=?,price_from=?,accent=?,image_path=?,latitude=?,longitude=?,google_place_id=?,sort_order=?,active=? WHERE id=?')->execute($values);
        } else {
            db()->prepare('INSERT INTO destinations (country_code,slug,code,name_de,name_en,region_de,region_en,intro_de,intro_en,default_nights,price_from,accent,image_path,latitude,longitude,google_place_id,sort_order,active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute($values);
            $id = (int)db()->lastInsertId();
        }
        flash('success','Reiseziel wurde gespeichert.');
        if ($returnTo === 'item-edit') redirect('admin/item-edit.php?destination_id=' . $id);
        redirect('admin/catalog.php');
    } catch (Throwable $exception) {
        flash('error','Speichern fehlgeschlagen: ' . $exception->getMessage());
        $query = $id ? '?id='.$id : ($returnTo === 'item-edit' ? '?return_to=item-edit' : '');
        redirect('admin/destination-edit.php' . $query);
    }
}

$mapSettings = db()->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('google_maps_api_key', 'google_maps_map_id')")->fetchAll(PDO::FETCH_KEY_PAIR);
$adminPage = 'catalog';
$adminTitle = $id ? 'Reiseziel bearbeiten' : 'Reiseziel erstellen';
require __DIR__ . '/_header.php';
?>
<div class="admin-content narrow">
    <a class="back-admin" href="<?= $returnTo === 'item-edit' ? 'item-edit.php' : 'catalog.php' ?>">← <?= $returnTo === 'item-edit' ? 'Neuer Reisebaustein' : 'Reisebausteine' ?></a>
    <div class="page-heading compact"><div><p>ORT / REISEZIEL</p><h1><?= $id ? 'Reiseziel bearbeiten' : 'Neuen Ort hinzufügen' ?></h1><span>Der Ort wird im Reiseplaner und für neue Unterkünfte, Sehenswürdigkeiten, Aktivitäten und Shops verfügbar.</span></div></div>
    <form class="editor-form" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($returnTo !== ''): ?><input type="hidden" name="return_to" value="<?= e($returnTo) ?>"><?php endif; ?>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Grunddaten</h2><p>Name, Region und technische Zuordnung</p></div></div><div class="form-grid">
            <label>Land<select name="country_code"><option value="LK"<?= selected('LK',$destination['country_code']) ?>>Sri Lanka</option><option value="MV"<?= selected('MV',$destination['country_code']) ?>>Malediven</option></select></label>
            <label>Slug<input name="slug" required value="<?= e($destination['slug']) ?>" placeholder="z. B. anuradhapura"></label>
            <label>Kürzel<input name="code" required maxlength="8" value="<?= e($destination['code']) ?>" placeholder="ANU"></label>
            <label>Name Deutsch<input name="name_de" required value="<?= e($destination['name_de']) ?>"></label>
            <label>Name Englisch<input name="name_en" required value="<?= e($destination['name_en']) ?>"></label>
            <label>Region Deutsch<input name="region_de" value="<?= e($destination['region_de']) ?>"></label>
            <label>Region Englisch<input name="region_en" value="<?= e($destination['region_en']) ?>"></label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Beschreibung</h2><p>Kurzer Text für die öffentliche Reisekarte</p></div></div><div class="form-grid">
            <label>Beschreibung Deutsch<textarea name="intro_de" rows="5" required><?= e($destination['intro_de']) ?></textarea></label>
            <label>Beschreibung Englisch<textarea name="intro_en" rows="5" required><?= e($destination['intro_en']) ?></textarea></label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Planung & Darstellung</h2><p>Empfohlene Dauer, Einstiegspreis und Bild</p></div></div><div class="form-grid thirds">
            <label>Empfohlene Nächte<input type="number" min="0" max="14" name="default_nights" value="<?= (int)$destination['default_nights'] ?>"><small>0 für Flughafen, Tagesstopp oder reine Durchfahrt</small></label>
            <label>Preis ab (€)<input type="number" min="0" step="0.01" name="price_from" value="<?= e($destination['price_from']) ?>"></label>
            <label>Sortierung<input type="number" name="sort_order" value="<?= (int)$destination['sort_order'] ?>"></label>
            <label>Farbwelt<select name="accent"><?php foreach (['leaf'=>'Grün','ochre'=>'Ocker','mist'=>'Salbei','clay'=>'Terracotta','ocean'=>'Ozean','sand'=>'Sand'] as $value=>$label): ?><option value="<?= e($value) ?>"<?= selected($value,$destination['accent']) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="span-two">Bild (JPG, PNG oder WEBP · max. 5 MB)<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><?php if ($destination['image_path']): ?><small>Aktuell: <?= e($destination['image_path']) ?></small><?php endif; ?></label>
            <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool)$destination['active']) ?>> Öffentlich aktiv</label>
        </div></section>
        <section class="admin-card form-card"><div class="card-head"><div><h2>Google Maps</h2><p>Position des Reiseziels für Fahrroute und Kartenmarker · Koordinaten sind erforderlich</p></div></div><div class="form-grid thirds">
            <label>Breitengrad<input type="number" min="-90" max="90" step="0.0000001" name="latitude" required value="<?= e($destination['latitude']) ?>" placeholder="z. B. 7.2906000"><small>In Google Maps mit Rechtsklick auf den Ort kopieren.</small></label>
            <label>Längengrad<input type="number" min="-180" max="180" step="0.0000001" name="longitude" required value="<?= e($destination['longitude']) ?>" placeholder="z. B. 80.6337000"><small>Zweiter Wert der kopierten Koordinaten.</small></label>
            <label>Google Place ID (optional)<input name="google_place_id" maxlength="255" value="<?= e($destination['google_place_id']) ?>" placeholder="ChIJ…"></label>
        </div><div class="destination-map-wrap"><div class="destination-map" data-destination-map></div><div class="destination-map-message<?= trim((string)($mapSettings['google_maps_api_key'] ?? '')) === '' ? ' visible' : '' ?>" data-destination-map-status><?= trim((string)($mapSettings['google_maps_api_key'] ?? '')) === '' ? 'Google Maps ist noch nicht eingerichtet. API-Schlüssel unter Einstellungen speichern; die Koordinaten können weiterhin manuell eingegeben werden.' : 'Karte wird geladen …' ?></div></div><p class="map-edit-help">Auf die Karte klicken oder den Marker ziehen. Breiten- und Längengrad werden automatisch übernommen.</p></section>
        <div class="form-actions"><a href="<?= $returnTo === 'item-edit' ? 'item-edit.php' : 'catalog.php' ?>">Abbrechen</a><button class="primary-button" type="submit"><?= $returnTo === 'item-edit' ? 'Ort speichern & Baustein anlegen' : 'Reiseziel speichern' ?></button></div>
    </form>
</div>
<script>window.DESTINATION_MAP_DATA=<?= json_encode(['apiKey'=>trim((string)($mapSettings['google_maps_api_key'] ?? '')),'mapId'=>trim((string)($mapSettings['google_maps_map_id'] ?? '')) ?: 'DEMO_MAP_ID','latitude'=>$destination['latitude'] !== '' && $destination['latitude'] !== null ? (float)$destination['latitude'] : null,'longitude'=>$destination['longitude'] !== '' && $destination['longitude'] !== null ? (float)$destination['longitude'] : null], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset('js/destination-map.js')) ?>"></script>
<?php require __DIR__ . '/_footer.php'; ?>
