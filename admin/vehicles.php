<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('vehicles', request_is_post());

$id = max(0, (int)($_GET['id'] ?? 0));

if (request_is_post()) {
    verify_csrf();
    $postedId = max(0, (int)($_POST['id'] ?? 0));
    $code = strtolower(trim((string)($_POST['code'] ?? '')));
    $code = trim((string)preg_replace('/[^a-z0-9]+/', '_', $code), '_');
    $nameDe = trim((string)($_POST['name_de'] ?? ''));
    $nameEn = trim((string)($_POST['name_en'] ?? ''));
    $capacity = max(1, min(100, (int)($_POST['capacity'] ?? 1)));
    $priceWithDriver = max(0, min(100000, round((float)str_replace(',', '.', (string)($_POST['price_per_day_with_driver'] ?? '0')), 2)));
    $priceWithoutDriver = max(0, min(100000, round((float)str_replace(',', '.', (string)($_POST['price_per_day_without_driver'] ?? '0')), 2)));
    $driverPricePerKm = max(0, min(1000, round((float)str_replace(',', '.', (string)($_POST['driver_price_per_km'] ?? '0.30')), 2)));
    $selfDriveAllowed = $capacity <= 3 && isset($_POST['self_drive_allowed']) ? 1 : 0;
    $sortOrder = max(-9999, min(9999, (int)($_POST['sort_order'] ?? 0)));
    $active = isset($_POST['active']) ? 1 : 0;

    try {
        if ($code === '' || $nameDe === '' || $nameEn === '') {
            throw new RuntimeException('Code sowie deutscher und englischer Name sind erforderlich.');
        }
        $existingImage = '';
        if ($postedId > 0) {
            $existingStmt = db()->prepare('SELECT image_path FROM vehicles WHERE id=?');
            $existingStmt->execute([$postedId]);
            $existingImage = (string)($existingStmt->fetchColumn() ?: '');
        }
        $image = isset($_POST['remove_image']) ? '' : upload_image($_FILES['image'] ?? [], $existingImage);
        if ($postedId > 0) {
            $save = db()->prepare('UPDATE vehicles SET code=?,name_de=?,name_en=?,capacity=?,price_per_day_with_driver=?,self_drive_allowed=?,price_per_day_without_driver=?,driver_price_per_km=?,image_path=?,active=?,sort_order=? WHERE id=?');
            $save->execute([$code,$nameDe,$nameEn,$capacity,$priceWithDriver,$selfDriveAllowed,$priceWithoutDriver,$driverPricePerKm,$image,$active,$sortOrder,$postedId]);
            flash('success', 'Fahrzeug wurde aktualisiert.');
        } else {
            $save = db()->prepare('INSERT INTO vehicles (code,name_de,name_en,capacity,price_per_day_with_driver,self_drive_allowed,price_per_day_without_driver,driver_price_per_km,image_path,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $save->execute([$code,$nameDe,$nameEn,$capacity,$priceWithDriver,$selfDriveAllowed,$priceWithoutDriver,$driverPricePerKm,$image,$active,$sortOrder]);
            $postedId = (int)db()->lastInsertId();
            flash('success', 'Fahrzeug wurde hinzugefügt.');
        }
        redirect('admin/vehicles.php?id=' . $postedId);
    } catch (Throwable $exception) {
        flash('error', 'Speichern fehlgeschlagen. Bitte prüfen Sie, ob der Code bereits verwendet wird.');
        redirect('admin/vehicles.php' . ($postedId > 0 ? '?id=' . $postedId : ''));
    }
}

$vehicle = [
    'id'=>0,'code'=>'','name_de'=>'','name_en'=>'','capacity'=>4,
    'price_per_day_with_driver'=>0,'self_drive_allowed'=>0,'price_per_day_without_driver'=>0,'driver_price_per_km'=>0.30,
    'image_path'=>'','active'=>1,'sort_order'=>60,
];
if ($id > 0) {
    $vehicleStmt = db()->prepare('SELECT * FROM vehicles WHERE id=?');
    $vehicleStmt->execute([$id]);
    $loadedVehicle = $vehicleStmt->fetch();
    if ($loadedVehicle) $vehicle = $loadedVehicle;
}
$vehicles = db()->query('SELECT * FROM vehicles ORDER BY capacity,sort_order,id')->fetchAll();

$adminPage = 'vehicles';
$adminTitle = 'Fahrzeugflotte';
require __DIR__ . '/_header.php';
?>
<div class="admin-content">
    <div class="page-heading compact"><div><p>TRANSPORT</p><h1>Fahrzeugflotte</h1><span>Kapazitäten verwalten; der Planer empfiehlt automatisch das kleinste passende aktive Fahrzeug.</span></div><a class="primary-button" href="vehicles.php">＋ Neues Fahrzeug</a></div>
    <div class="detail-grid fleet-grid">
        <form class="editor-form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$vehicle['id'] ?>">
            <section class="admin-card form-card">
                <div class="card-head"><div><h2><?= (int)$vehicle['id'] > 0 ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug' ?></h2><p>Deutsch & Englisch</p></div></div>
                <div class="form-grid thirds">
                    <label>Code<input name="code" required maxlength="40" value="<?= e($vehicle['code']) ?>" placeholder="z. B. mini_van"><small>Eindeutig, ohne Leerzeichen</small></label>
                    <label>Name Deutsch<input name="name_de" required maxlength="120" value="<?= e($vehicle['name_de']) ?>" placeholder="Mini Van"></label>
                    <label>Name Englisch<input name="name_en" required maxlength="120" value="<?= e($vehicle['name_en']) ?>" placeholder="Mini Van"></label>
                    <label>Maximale Reisende<input name="capacity" type="number" required min="1" max="100" value="<?= (int)$vehicle['capacity'] ?>"></label>
                    <label>Sortierung<input name="sort_order" type="number" min="-9999" max="9999" value="<?= (int)$vehicle['sort_order'] ?>"></label>
                    <label class="check"><input type="checkbox" name="active" value="1"<?= (int)$vehicle['active'] === 1 ? ' checked' : '' ?>> Aktiv und im Planer verfügbar</label>
                    <label class="span-two">Fahrzeugbild (JPG, PNG oder WEBP · max. 5 MB)<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small>Das Bild erscheint im Planer und in der Druckansicht.</small></label>
                    <?php if ($vehicle['image_path']): ?><div class="vehicle-admin-preview"><img src="<?= e(url($vehicle['image_path'])) ?>" alt=""><label class="check"><input type="checkbox" name="remove_image" value="1"> Bild entfernen</label></div><?php endif; ?>
                </div>
            </section>
            <section class="admin-card form-card">
                <div class="card-head"><div><h2>Preise & Fahrer</h2><p>Tagespreise und zusätzliche Kilometerkosten</p></div></div>
                <div class="form-grid thirds">
                    <label>Mit Fahrer / Tag (€)<input name="price_per_day_with_driver" type="number" min="0" max="100000" step="0.01" value="<?= e(number_format((float)$vehicle['price_per_day_with_driver'],2,'.','')) ?>"><small>Für alle Fahrzeuge verfügbar</small></label>
                    <label>Mit Fahrer / km (€)<input name="driver_price_per_km" type="number" min="0" max="1000" step="0.01" value="<?= e(number_format((float)$vehicle['driver_price_per_km'],2,'.','')) ?>"><small>Standard: 0,30 € pro km</small></label>
                    <label class="check"><input type="checkbox" name="self_drive_allowed" value="1"<?= (int)$vehicle['self_drive_allowed'] === 1 && (int)$vehicle['capacity'] <= 3 ? ' checked' : '' ?>> Ohne Fahrer erlauben (nur bis 3 Reisende)</label>
                    <label>Ohne Fahrer / Tag (€)<input name="price_per_day_without_driver" type="number" min="0" max="100000" step="0.01" value="<?= e(number_format((float)$vehicle['price_per_day_without_driver'],2,'.','')) ?>"><small>Wird nur bei Fahrzeugen bis 3 Reisende verwendet</small></label>
                </div>
            </section>
            <div class="form-actions"><a href="vehicles.php">Zurücksetzen</a><button class="primary-button" type="submit">Fahrzeug speichern</button></div>
        </form>
        <section class="admin-card">
            <div class="card-head"><div><h2>Aktuelle Flotte</h2><p><?= count($vehicles) ?> Fahrzeuge</p></div></div>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Bild</th><th>Fahrzeug</th><th>Kapazität</th><th>Preise</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($vehicles as $row): ?><tr>
                <td><?php if($row['image_path']):?><img class="admin-table-thumb" src="<?= e(url($row['image_path'])) ?>" alt=""><?php else:?><span class="admin-table-placeholder">▤</span><?php endif;?></td>
                <td><strong><?= e($row['name_de']) ?></strong><small><?= e($row['name_en']) ?> · <?= e($row['code']) ?></small></td>
                <td>bis <?= (int)$row['capacity'] ?> Reisende</td>
                <td><strong><?= e(money_precise($row['price_per_day_with_driver'])) ?> / Tag mit Fahrer</strong><small>+ <?= e(money_precise($row['driver_price_per_km'])) ?> / km<?= (int)$row['self_drive_allowed'] === 1 && (int)$row['capacity'] <= 3 ? ' · ' . e(money_precise($row['price_per_day_without_driver'])) . ' / Tag ohne Fahrer' : '' ?></small></td>
                <td><span class="status <?= (int)$row['active'] === 1 ? 'confirmed' : 'archived' ?>"><?= (int)$row['active'] === 1 ? 'Aktiv' : 'Inaktiv' ?></span></td>
                <td><a href="vehicles.php?id=<?= (int)$row['id'] ?>">Bearbeiten →</a></td>
            </tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
