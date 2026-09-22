<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin_permission('settings', request_is_post());

$defaults = [
    'site_name' => 'Sri Lanka Expert',
    'brand_name' => 'Sri Lanka Expert',
    'brand_byline' => 'by Raonex GmbH',
    'company_name' => 'Raonex GmbH',
    'company_address' => 'August-Bebel-Strasse 26c',
    'company_postcode_city' => '97297 Waldbüttelbrunn',
    'company_phone' => '+49 931 80472297',
    'company_email' => 'info@raonex.de',
    'company_web' => 'https://www.srilankaexpert.de',
    'social_instagram' => '',
    'social_facebook' => '',
    'social_tiktok' => '',
    'social_youtube' => '',
    'google_maps_api_key' => '',
    'google_maps_map_id' => 'DEMO_MAP_ID',
    'global_price_markup_percent' => '10',
];
$settingRows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$values = array_merge($defaults, array_intersect_key($settingRows, $defaults));
$currentKey = trim((string)$values['google_maps_api_key']);

if (request_is_post()) {
    verify_csrf();
    try {
        $newKey = trim((string)($_POST['google_maps_api_key'] ?? ''));
        if (isset($_POST['remove_google_maps_api_key'])) $currentKey = '';
        elseif ($newKey !== '') {
            if (strlen($newKey) > 255 || !preg_match('/^[A-Za-z0-9_-]+$/', $newKey)) throw new RuntimeException('Der API-Schlüssel enthält ungültige Zeichen.');
            $currentKey = $newKey;
        }

        foreach (array_keys($defaults) as $key) {
            if (in_array($key, ['google_maps_api_key', 'google_maps_map_id'], true)) continue;
            $values[$key] = substr(trim((string)($_POST[$key] ?? '')), 0, 500);
        }
        $markupRaw = str_replace(',', '.', (string)$values['global_price_markup_percent']);
        if (!is_numeric($markupRaw) || (float)$markupRaw < 0 || (float)$markupRaw > 200) throw new RuntimeException('Die Preisanpassung muss zwischen 0 und 200 Prozent liegen.');
        $values['global_price_markup_percent'] = rtrim(rtrim(number_format((float)$markupRaw, 2, '.', ''), '0'), '.');
        $values['google_maps_api_key'] = $currentKey;
        $values['google_maps_map_id'] = substr(trim((string)($_POST['google_maps_map_id'] ?? '')), 0, 255) ?: 'DEMO_MAP_ID';
        if ($values['company_email'] !== '' && !filter_var($values['company_email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Bitte eine gültige Firmen-E-Mail-Adresse eingeben.');
        foreach (['company_web','social_instagram','social_facebook','social_tiktok','social_youtube'] as $urlKey) {
            if ($values[$urlKey] !== '' && !filter_var($values[$urlKey], FILTER_VALIDATE_URL)) throw new RuntimeException('Bitte für Website und Social Media vollständige URLs inklusive https:// eingeben.');
        }
        if ($values['brand_name'] === '' || $values['company_name'] === '') throw new RuntimeException('Markenname und Firmenname dürfen nicht leer sein.');

        $save = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        foreach ($values as $key => $value) $save->execute([$key, $value]);
        flash('success', 'Website-, Preis-, Firmen- und Google-Maps-Einstellungen wurden gespeichert.');
        redirect('admin/settings.php');
    } catch (Throwable $exception) {
        flash('error', 'Speichern fehlgeschlagen: ' . $exception->getMessage());
        redirect('admin/settings.php');
    }
}

$maskedKey = $currentKey === '' ? '' : str_repeat('•', max(8, min(24, strlen($currentKey) - 4))) . substr($currentKey, -4);
$adminPage = 'settings';
$adminTitle = 'Einstellungen';
require __DIR__ . '/_header.php';
?>
<div class="admin-content narrow">
    <div class="page-heading compact"><div><p>SYSTEM</p><h1>Einstellungen</h1><span>Marke, Preise, Firmeninformationen, Social Media und Google Maps zentral pflegen.</span></div></div>
    <form class="editor-form" method="post">
        <?= csrf_field() ?>
        <section class="admin-card form-card">
            <div class="card-head"><div><h2>Marke & Website</h2><p>Wird in Seitentitel, Kopf- und Fußbereich verwendet.</p></div></div>
            <div class="form-grid">
                <label>Website-Name<input name="site_name" maxlength="150" value="<?= e($values['site_name']) ?>"></label>
                <label>Markenname<input name="brand_name" maxlength="150" required value="<?= e($values['brand_name']) ?>"></label>
                <label>Marken-Zusatz<input name="brand_byline" maxlength="150" value="<?= e($values['brand_byline']) ?>" placeholder="by Raonex GmbH"></label>
            </div>
        </section>
        <section class="admin-card form-card">
            <div class="card-head"><div><h2>Firma & Kontakt</h2><p>Diese Angaben werden dynamisch im öffentlichen Footer ausgegeben.</p></div></div>
            <div class="form-grid">
                <label>Firmenname<input name="company_name" maxlength="180" required value="<?= e($values['company_name']) ?>"></label>
                <label>Straße & Hausnummer<input name="company_address" maxlength="220" value="<?= e($values['company_address']) ?>"></label>
                <label>PLZ & Ort<input name="company_postcode_city" maxlength="220" value="<?= e($values['company_postcode_city']) ?>"></label>
                <label>Telefon<input name="company_phone" maxlength="80" value="<?= e($values['company_phone']) ?>"></label>
                <label>E-Mail<input name="company_email" type="email" maxlength="190" value="<?= e($values['company_email']) ?>"></label>
                <label>Website<input name="company_web" type="url" maxlength="500" value="<?= e($values['company_web']) ?>" placeholder="https://..."></label>
            </div>
        </section>
        <section class="admin-card form-card">
            <div class="card-head"><div><h2>Social Media</h2><p>Leere Felder werden im Footer automatisch ausgeblendet.</p></div></div>
            <div class="form-grid">
                <label>Instagram URL<input name="social_instagram" type="url" maxlength="500" value="<?= e($values['social_instagram']) ?>" placeholder="https://instagram.com/..."></label>
                <label>Facebook URL<input name="social_facebook" type="url" maxlength="500" value="<?= e($values['social_facebook']) ?>" placeholder="https://facebook.com/..."></label>
                <label>TikTok URL<input name="social_tiktok" type="url" maxlength="500" value="<?= e($values['social_tiktok']) ?>" placeholder="https://tiktok.com/@..."></label>
                <label>YouTube URL<input name="social_youtube" type="url" maxlength="500" value="<?= e($values['social_youtube']) ?>" placeholder="https://youtube.com/@..."></label>
            </div>
        </section>
        <section class="admin-card form-card">
            <div class="card-head"><div><h2>Globale Preisanpassung</h2><p>Erhöht neue Kundenpreise, ohne die gespeicherten Basispreise zu verändern.</p></div></div>
            <div class="form-grid">
                <label>Aufschlag auf Touren und Hotelaufenthalte (%)<input type="number" name="global_price_markup_percent" min="0" max="200" step="0.01" value="<?= e($values['global_price_markup_percent']) ?>"><small>Beispiel: 10 erhöht einen Basispreis von 1.000 € auf 1.100 €. Bereits gespeicherte Anfragen bleiben unverändert.</small></label>
            </div>
        </section>
        <section class="admin-card form-card">
            <div class="card-head"><div><h2>Google Maps & Fahrroute</h2><p>Status: <strong><?= $currentKey !== '' ? 'eingerichtet' : 'API-Schlüssel fehlt' ?></strong></p></div></div>
            <div class="form-grid">
                <label>Neuer Browser-API-Schlüssel<input type="password" name="google_maps_api_key" maxlength="255" autocomplete="new-password" placeholder="<?= $currentKey !== '' ? e($maskedKey . ' · leer lassen zum Beibehalten') : 'AIza…' ?>"><small>Der Schlüssel wird absichtlich nicht vollständig angezeigt.</small></label>
                <label>Map ID<input name="google_maps_map_id" maxlength="255" value="<?= e($values['google_maps_map_id']) ?>" placeholder="DEMO_MAP_ID"><small>Für lokale Tests kann DEMO_MAP_ID verwendet werden.</small></label>
                <?php if ($currentKey !== ''): ?><label class="check"><input type="checkbox" name="remove_google_maps_api_key" value="1"> Gespeicherten API-Schlüssel entfernen</label><?php endif; ?>
            </div>
        </section>
        <section class="admin-card form-card maps-guide">
            <div class="card-head"><div><h2>Google Cloud vorbereiten</h2><p>Einmalige Einrichtung für Karte und Straßenroute</p></div></div>
            <ol><li>Ein Projekt mit Abrechnung auswählen oder erstellen.</li><li><strong>Maps JavaScript API</strong> und <strong>Routes API</strong> aktivieren.</li><li>Einen Browser-API-Schlüssel erstellen und auf beide APIs beschränken.</li><li>Unter Website-Einschränkungen Ihre Domains eintragen.</li></ol>
            <p class="form-help">Ein Browser-Schlüssel wird beim Kartenaufruf technisch an den Webbrowser übertragen. Deshalb sind Domain- und API-Einschränkungen wichtig.</p>
        </section>
        <div class="form-actions"><a href="<?= e(url('admin/index.php')) ?>">Zurück</a><button class="primary-button" type="submit">Einstellungen speichern</button></div>
    </form>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
