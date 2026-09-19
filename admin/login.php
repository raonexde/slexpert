<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (admin_user()) redirect('admin/index.php');

$error = '';
if (request_is_post()) {
    verify_csrf();
    if (attempt_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
        $return = $_SESSION['login_return'] ?? url('admin/index.php');
        unset($_SESSION['login_return']);
        header('Location: ' . $return);
        exit;
    }
    $error = 'E-Mail-Adresse oder Passwort ist nicht korrekt.';
}
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login</title><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head>
<body class="login-page">
<main class="login-shell">
    <section class="login-visual"><a href="<?= e(url()) ?>">SRI LANKA · EXPERT</a><div><p>ADMINISTRATION</p><h1>Ihre Reisen.<br>Persönlich verwaltet.</h1></div><small>by Raonex GmbH · PHP + MySQL</small></section>
    <section class="login-panel">
        <div class="login-card">
            <p class="admin-eyebrow">GESCHÜTZTER BEREICH</p><h2>Willkommen zurück.</h2><p>Melden Sie sich an, um Anfragen und Reisebausteine zu verwalten.</p>
            <?php if (isset($_GET['installed'])): ?><div class="login-success">✓ Installation erfolgreich. Sie können sich jetzt anmelden.</div><?php endif; ?>
            <?php if ($error): ?><div class="login-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post"><?= csrf_field() ?><label>E-Mail-Adresse<input name="email" type="email" required autofocus autocomplete="username"></label><label>Passwort<input name="password" type="password" required autocomplete="current-password"></label><button class="login-button" type="submit">Anmelden →</button></form>
            <a class="back-link" href="<?= e(url()) ?>">← Zur Website</a>
        </div>
    </section>
</main>
</body>
</html>
