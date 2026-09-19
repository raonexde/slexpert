<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (request_is_post()) {
    verify_csrf();
    $language = ($_POST['language'] ?? 'de') === 'en' ? 'en' : 'de';
    if (trim((string)($_POST['website'] ?? '')) !== '') redirect('guide-register.php?lang=' . $language);
    $firstName = substr(trim((string)($_POST['first_name'] ?? '')), 0, 100);
    $lastName = substr(trim((string)($_POST['last_name'] ?? '')), 0, 100);
    $email = strtolower(substr(trim((string)($_POST['email'] ?? '')), 0, 190));
    $phone = substr(trim((string)($_POST['phone'] ?? '')), 0, 80);
    $licenseNumber = substr(trim((string)($_POST['license_number'] ?? '')), 0, 120);
    $languages = substr(trim((string)($_POST['languages'] ?? '')), 0, 500);
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $licenseNumber === '' || $languages === '' || !isset($_POST['consent'])) {
        flash('error', t('Bitte füllen Sie alle Pflichtfelder aus und bestätigen Sie die Datenschutzeinwilligung.', 'Please complete all required fields and confirm the data-processing consent.'));
        redirect('guide-register.php?lang=' . $language);
    }
    try {
        $photo = upload_image($_FILES['photo'] ?? []);
        $licenseDocument = upload_private_guide_document($_FILES['license_document'] ?? []);
        $identityDocument = upload_private_guide_document($_FILES['identity_document'] ?? []);
        $insuranceDocument = upload_private_guide_document($_FILES['insurance_document'] ?? []);
        do {
            $guideCode = 'G-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $exists = db()->prepare('SELECT COUNT(*) FROM guides WHERE guide_code=?');
            $exists->execute([$guideCode]);
        } while ((int)$exists->fetchColumn() > 0);
        $displayName = substr(trim((string)($_POST['display_name'] ?? '')), 0, 190) ?: $firstName . ' ' . $lastName;
        $stmt = db()->prepare("INSERT INTO guides
            (guide_code,status,first_name,last_name,display_name,email,phone,whatsapp,date_of_birth,gender,nationality,address,city,district,country,nic_passport,license_number,license_type,license_expiry,tourism_registration,years_experience,languages,service_regions,specializations,driver_guide,daily_rate,bio_de,bio_en,emergency_name,emergency_phone,photo_path,license_document_path,identity_document_path,insurance_document_path,consent_at,active,featured,sort_order)
            VALUES (?,'pending',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),0,0,100)");
        $stmt->execute([
            $guideCode,$firstName,$lastName,$displayName,$email,$phone,substr(trim((string)($_POST['whatsapp']??'')),0,80),trim((string)($_POST['date_of_birth']??''))?:null,substr(trim((string)($_POST['gender']??'')),0,40),substr(trim((string)($_POST['nationality']??'')),0,100),substr(trim((string)($_POST['address']??'')),0,2000),substr(trim((string)($_POST['city']??'')),0,120),substr(trim((string)($_POST['district']??'')),0,120),substr(trim((string)($_POST['country']??'Sri Lanka')),0,120),substr(trim((string)($_POST['nic_passport']??'')),0,120),$licenseNumber,substr(trim((string)($_POST['license_type']??'')),0,120),trim((string)($_POST['license_expiry']??''))?:null,substr(trim((string)($_POST['tourism_registration']??'')),0,120),max(0,min(70,(int)($_POST['years_experience']??0))),$languages,substr(trim((string)($_POST['service_regions']??'')),0,500),substr(trim((string)($_POST['specializations']??'')),0,500),isset($_POST['driver_guide'])?1:0,max(0,round((float)str_replace(',','.',(string)($_POST['daily_rate']??0)),2)),substr(trim((string)($_POST['bio_de']??'')),0,5000),substr(trim((string)($_POST['bio_en']??'')),0,5000),substr(trim((string)($_POST['emergency_name']??'')),0,150),substr(trim((string)($_POST['emergency_phone']??'')),0,80),$photo?:null,$licenseDocument?:null,$identityDocument?:null,$insuranceDocument?:null,
        ]);
        flash('success', t('Vielen Dank. Ihre Registrierung wurde unter der Referenz '.$guideCode.' eingereicht und wird geprüft.', 'Thank you. Your registration was submitted under reference '.$guideCode.' and will be reviewed.'));
        redirect('guide-register.php?lang=' . $language . '&submitted=1');
    } catch (Throwable $exception) {
        flash('error', t('Die Registrierung konnte nicht gespeichert werden. Prüfen Sie Dateiformat und Dateigröße.', 'The registration could not be saved. Please check file type and size.'));
        redirect('guide-register.php?lang=' . $language);
    }
}

$pageTitle = t('Reiseleiter registrieren', 'Guide registration');
$bodyClass = 'guide-register-page';
require __DIR__ . '/includes/public-header.php';
?>
<main class="guide-register-main">
    <section class="planner-intro"><p class="eyebrow dark"><?= e(t('PARTNER WERDEN','BECOME A PARTNER')) ?></p><h1><?= e(t('Registrierung für Reiseleiter','Tour guide registration')) ?></h1><p><?= e(t('Bewerben Sie sich als Reiseleiter oder Driver-Guide. Nach Prüfung kann Ihr Profil für Kundenreisen freigegeben werden.','Apply as a tour guide or driver-guide. After review, your profile can be approved for customer tours.')) ?></p></section>
    <?php foreach(pull_flashes() as $flash):?><div class="public-form-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach;?>
    <form class="guide-application" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="language" value="<?= e(lang()) ?>"><label class="hp-field">Website<input name="website" tabindex="-1" autocomplete="off"></label>
        <section><h2><?= e(t('Persönliche Angaben','Personal details')) ?></h2><div class="guide-form-grid">
            <label><?= e(t('Vorname *','First name *')) ?><input name="first_name" required maxlength="100"></label><label><?= e(t('Nachname *','Last name *')) ?><input name="last_name" required maxlength="100"></label>
            <label><?= e(t('Anzeigename','Display name')) ?><input name="display_name" maxlength="190"></label><label><?= e(t('Geburtsdatum','Date of birth')) ?><input name="date_of_birth" type="date"></label>
            <label><?= e(t('Geschlecht','Gender')) ?><input name="gender" maxlength="40"></label><label><?= e(t('Nationalität','Nationality')) ?><input name="nationality" maxlength="100"></label>
            <label><?= e(t('E-Mail *','Email *')) ?><input name="email" type="email" required maxlength="190"></label><label><?= e(t('Telefon *','Phone *')) ?><input name="phone" required maxlength="80"></label>
            <label>WhatsApp<input name="whatsapp" maxlength="80"></label><label><?= e(t('NIC / Reisepass','NIC / passport')) ?><input name="nic_passport" maxlength="120"></label>
            <label class="wide"><?= e(t('Adresse','Address')) ?><textarea name="address" rows="3"></textarea></label><label><?= e(t('Ort','City')) ?><input name="city" maxlength="120"></label><label><?= e(t('Distrikt','District')) ?><input name="district" maxlength="120"></label><label><?= e(t('Land','Country')) ?><input name="country" value="Sri Lanka" maxlength="120"></label>
        </div></section>
        <section><h2><?= e(t('Qualifikation & Einsatz','Qualifications & service')) ?></h2><div class="guide-form-grid">
            <label><?= e(t('Lizenznummer *','Licence number *')) ?><input name="license_number" required maxlength="120"></label><label><?= e(t('Lizenzart','Licence type')) ?><input name="license_type" maxlength="120"></label>
            <label><?= e(t('Lizenz gültig bis','Licence expiry')) ?><input name="license_expiry" type="date"></label><label><?= e(t('Tourismus-Registrierung','Tourism registration')) ?><input name="tourism_registration" maxlength="120"></label>
            <label><?= e(t('Berufserfahrung (Jahre)','Experience (years)')) ?><input name="years_experience" type="number" min="0" max="70" value="0"></label><label><?= e(t('Tageshonorar (€)','Daily rate (€)')) ?><input name="daily_rate" type="number" min="0" step="0.01" value="0"></label>
            <label class="wide"><?= e(t('Sprachen *','Languages *')) ?><input name="languages" required maxlength="500" placeholder="Deutsch, English, Sinhala, Tamil"></label>
            <label class="wide"><?= e(t('Einsatzregionen','Service regions')) ?><input name="service_regions" maxlength="500" placeholder="Island-wide, Cultural Triangle, South Coast …"></label>
            <label class="wide"><?= e(t('Spezialisierungen','Specialisations')) ?><input name="specializations" maxlength="500" placeholder="Culture, wildlife, hiking, families …"></label>
            <label class="check wide"><input type="checkbox" name="driver_guide" value="1"> <?= e(t('Ich bin auch als Driver-Guide verfügbar.','I am also available as a driver-guide.')) ?></label>
            <label class="wide"><?= e(t('Profil Deutsch','Profile in German')) ?><textarea name="bio_de" rows="4" maxlength="5000"></textarea></label><label class="wide"><?= e(t('Profil Englisch','Profile in English')) ?><textarea name="bio_en" rows="4" maxlength="5000"></textarea></label>
        </div></section>
        <section><h2><?= e(t('Foto, Dokumente & Notfallkontakt','Photo, documents & emergency contact')) ?></h2><div class="guide-form-grid">
            <label><?= e(t('Profilfoto','Profile photo')) ?><input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small>JPG, PNG, WEBP · max. 5 MB</small></label>
            <label><?= e(t('Lizenznachweis','Licence document')) ?><input type="file" name="license_document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"><small>PDF, JPG, PNG · max. 8 MB · privat</small></label>
            <label><?= e(t('Identitätsnachweis','Identity document')) ?><input type="file" name="identity_document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"><small>PDF, JPG, PNG · max. 8 MB · privat</small></label>
            <label><?= e(t('Versicherungsnachweis','Insurance document')) ?><input type="file" name="insurance_document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"><small>Optional · privat</small></label>
            <label><?= e(t('Notfallkontakt','Emergency contact')) ?><input name="emergency_name" maxlength="150"></label><label><?= e(t('Notfalltelefon','Emergency phone')) ?><input name="emergency_phone" maxlength="80"></label>
            <label class="check wide"><input type="checkbox" name="consent" value="1" required> <?= e(t('Ich stimme der Speicherung und Prüfung meiner Angaben und Dokumente für die Partnerregistrierung zu. *','I consent to the storage and review of my details and documents for partner registration. *')) ?></label>
        </div></section>
        <button class="button button-gold" type="submit"><?= e(t('Registrierung einreichen','Submit registration')) ?> <span>→</span></button>
    </form>
</main>
<?php require __DIR__ . '/includes/public-footer.php'; ?>
