<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (portal_user()) redirect('account/index.php?lang=' . lang());
$error = '';
if (request_is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (attempt_portal_login($email, $password)) {
        redirect('account/index.php?lang=' . lang());
    }
    $error = t('E-Mail-Adresse oder Passwort ist nicht korrekt.', 'The email address or password is incorrect.');
}
$pageTitle = t('Kunden- und Agentur-Login','Customer and agent login');
$bodyClass = 'account-page';
$extraStyles = ['css/account.css'];
require dirname(__DIR__) . '/includes/public-header.php';
?>
<main class="account-auth-shell">
    <section class="account-auth-copy">
        <p><?= e(t('IHR REISEKONTO','YOUR TRAVEL ACCOUNT')) ?></p>
        <h1><?= e(t('Ihre Reise immer im Blick.','Your journey, always at hand.')) ?></h1>
        <span><?= e(t('Anfragen, Angebote, Buchungen, Zahlungen und Reisedokumente an einem sicheren Ort.', 'Enquiries, proposals, bookings, payments and travel documents in one secure place.')) ?></span>
    </section>
    <section class="account-auth-panel"><div class="account-auth-card">
        <p class="eyebrow dark"><?= e(t('KUNDEN & B2B-AGENTUREN','CUSTOMERS & B2B AGENTS')) ?></p>
        <h2><?= e(t('Anmelden','Sign in')) ?></h2>
        <?php if ($error): ?><div class="account-alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label><?= e(t('E-Mail-Adresse','Email address')) ?><input name="email" type="email" required autofocus autocomplete="username"></label>
            <label><?= e(t('Passwort','Password')) ?><input name="password" type="password" required autocomplete="current-password"></label>
            <button class="account-primary" type="submit"><?= e(t('Sicher anmelden','Sign in securely')) ?> →</button>
        </form>
        <div class="account-auth-links"><span><?= e(t('Noch kein Kundenkonto?','No customer account yet?')) ?></span><a href="<?= e(url('account/register.php?lang='.lang())) ?>"><?= e(t('Jetzt registrieren','Register now')) ?></a></div>
        <small><?= e(t('B2B-Agenturen erhalten ihre Zugangsdaten vom Administrator.', 'B2B agents receive their login details from the administrator.')) ?></small>
    </div></section>
</main>
<?php require dirname(__DIR__) . '/includes/public-footer.php'; ?>
