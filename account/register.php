<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (portal_user()) redirect('account/index.php?lang=' . lang());
$errors = [];
$values = ['name'=>'','email'=>'','phone'=>''];
if (request_is_post()) {
    verify_csrf();
    foreach (array_keys($values) as $key) $values[$key] = trim((string)($_POST[$key] ?? ''));
    $values['email'] = strtolower($values['email']);
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
    if ($values['name'] === '' || strlen($values['name']) > 150) $errors[] = t('Bitte geben Sie Ihren Namen ein.','Please enter your name.');
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('Bitte geben Sie eine gültige E-Mail-Adresse ein.','Please enter a valid email address.');
    if (strlen($password) < 8) $errors[] = t('Das Passwort muss mindestens 8 Zeichen enthalten.','The password must contain at least 8 characters.');
    if (!hash_equals($password, $passwordConfirm)) $errors[] = t('Die Passwörter stimmen nicht überein.','The passwords do not match.');
    if (!$errors) {
        $check = db()->prepare('SELECT COUNT(*) FROM portal_users WHERE email=?');
        $check->execute([$values['email']]);
        if ((int)$check->fetchColumn() > 0) $errors[] = t('Für diese E-Mail-Adresse besteht bereits ein Konto.','An account already exists for this email address.');
    }
    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare("INSERT INTO portal_users (user_type,name,email,password_hash,phone,language) VALUES ('customer',?,?,?,?,?)");
            $insert->execute([$values['name'],$values['email'],password_hash($password,PASSWORD_DEFAULT),substr($values['phone'],0,80),lang()]);
            $customerId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE tour_requests SET customer_user_id=? WHERE customer_user_id IS NULL AND b2b_agent_id IS NULL AND LOWER(customer_email)=?")
                ->execute([$customerId,$values['email']]);
            $pdo->prepare("UPDATE bookings b JOIN tour_requests r ON r.id=b.request_id SET b.customer_user_id=? WHERE b.customer_user_id IS NULL AND r.b2b_agent_id IS NULL AND LOWER(r.customer_email)=?")
                ->execute([$customerId,$values['email']]);
            $pdo->commit();
            attempt_portal_login($values['email'],$password);
            flash('success',t('Ihr Kundenkonto wurde erstellt. Bereits vorhandene Anfragen wurden zugeordnet.','Your customer account has been created. Existing enquiries have been linked.'));
            redirect('account/index.php?lang='.lang());
        } catch (Throwable) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = t('Das Konto konnte nicht erstellt werden. Bitte versuchen Sie es erneut.','The account could not be created. Please try again.');
        }
    }
}
$pageTitle = t('Kundenkonto erstellen','Create customer account');
$bodyClass = 'account-page';
$extraStyles = ['css/account.css'];
require dirname(__DIR__) . '/includes/public-header.php';
?>
<main class="account-auth-shell">
    <section class="account-auth-copy">
        <p><?= e(t('KOSTENLOS REGISTRIEREN','REGISTER FREE')) ?></p>
        <h1><?= e(t('Von der Reiseidee bis zur Buchung.','From travel idea to booking.')) ?></h1>
        <span><?= e(t('Nach der Registrierung sehen Sie auch frühere Anfragen mit derselben E-Mail-Adresse.', 'After registration you can also see earlier enquiries using the same email address.')) ?></span>
    </section>
    <section class="account-auth-panel"><div class="account-auth-card wide">
        <p class="eyebrow dark"><?= e(t('KUNDENKONTO','CUSTOMER ACCOUNT')) ?></p><h2><?= e(t('Konto erstellen','Create account')) ?></h2>
        <?php foreach ($errors as $error): ?><div class="account-alert error"><?= e($error) ?></div><?php endforeach; ?>
        <form method="post"><?= csrf_field() ?>
            <div class="account-form-grid">
                <label><?= e(t('Name','Name')) ?><input name="name" required maxlength="150" value="<?= e($values['name']) ?>" autocomplete="name"></label>
                <label><?= e(t('Telefon / WhatsApp','Phone / WhatsApp')) ?><input name="phone" maxlength="80" value="<?= e($values['phone']) ?>" autocomplete="tel"></label>
                <label class="span-two"><?= e(t('E-Mail-Adresse','Email address')) ?><input name="email" type="email" required maxlength="190" value="<?= e($values['email']) ?>" autocomplete="email"></label>
                <label><?= e(t('Passwort','Password')) ?><input name="password" type="password" minlength="8" required autocomplete="new-password"></label>
                <label><?= e(t('Passwort wiederholen','Repeat password')) ?><input name="password_confirm" type="password" minlength="8" required autocomplete="new-password"></label>
            </div>
            <button class="account-primary" type="submit"><?= e(t('Konto erstellen','Create account')) ?> →</button>
        </form>
        <div class="account-auth-links"><span><?= e(t('Bereits registriert?','Already registered?')) ?></span><a href="<?= e(url('account/login.php?lang='.lang())) ?>"><?= e(t('Anmelden','Sign in')) ?></a></div>
    </div></section>
</main>
<?php require dirname(__DIR__) . '/includes/public-footer.php'; ?>
