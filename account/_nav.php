<?php
$portal = portal_user();
?>
<nav class="account-nav" aria-label="<?= e(t('Kundenkonto','Customer account')) ?>">
    <a class="<?= ($accountPage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('account/index.php?lang='.lang())) ?>"><?= e($portal && $portal['user_type']==='agent' ? t('Agentur-Portal','Agent portal') : t('Mein Konto','My account')) ?></a>
    <a href="<?= e(url('plan.php?lang='.lang())) ?>"><?= e(t('Neue Rundreise','New tour')) ?></a>
    <a href="<?= e(url('stay.php?type=beach&lang='.lang())) ?>"><?= e(t('Neuer Hotelaufenthalt','New hotel stay')) ?></a>
    <a class="<?= ($accountPage ?? '') === 'profile' ? 'active' : '' ?>" href="<?= e(url('account/profile.php?lang='.lang())) ?>"><?= e(t('Profil','Profile')) ?></a>
    <form action="<?= e(url('account/logout.php')) ?>" method="post"><?= csrf_field() ?><button type="submit"><?= e(t('Abmelden','Sign out')) ?></button></form>
</nav>
