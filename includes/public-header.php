<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? t('Sri Lanka individuell erleben', 'Experience Sri Lanka your way');
$bodyClass = $bodyClass ?? '';
$currentLang = lang();
$query = $_GET;
$brandName = site_setting('brand_name', 'Sri Lanka Expert');
$brandByline = site_setting('brand_byline', 'by Raonex GmbH');
$siteName = site_setting('site_name', $brandName);
$portalHeaderUser = portal_user();
?>
<!doctype html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="<?= e(t('Individuelle Sri-Lanka-Rundreisen, Ayurveda, Strandurlaub und ausgewählte Malediven-Resorts.', 'Tailor-made Sri Lanka journeys, Ayurveda, beach holidays and selected Maldives resorts.')) ?>">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <?php foreach (($extraStyles ?? []) as $extraStyle): ?><link rel="stylesheet" href="<?= e(asset($extraStyle)) ?>"><?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="public-header">
    <a class="brand" href="<?= e(url('index.php?lang=' . $currentLang)) ?>">
        <span class="brand-mark">SL</span>
        <span><strong><?= e(strtoupper($brandName)) ?></strong><small><?= e(strtoupper($brandByline)) ?></small></span>
    </a>
    <button class="mobile-menu" type="button" aria-label="Menu" data-menu-toggle>☰</button>
    <nav class="public-nav" data-menu>
        <a href="<?= e(url('plan.php?lang=' . $currentLang)) ?>"><?= e(t('Reise planen', 'Plan your trip')) ?></a>
        <a href="<?= e(url('tours.php?lang=' . $currentLang)) ?>"><?= e(t('Reiseideen', 'Tour ideas')) ?></a>
        <a href="<?= e(url('stay.php?type=beach&lang=' . $currentLang)) ?>"><?= e(t('Strandurlaub', 'Beach vacation')) ?></a>
        <a href="<?= e(url('stay.php?type=ayurveda&lang=' . $currentLang)) ?>"><?= e(t('Ayurveda', 'Ayurveda')) ?></a>
        <a href="<?= e(url('maldives.php?lang=' . $currentLang)) ?>"><?= e(t('Malediven', 'Maldives')) ?></a>
        <a href="<?= e(url('index.php?lang=' . $currentLang . '#places')) ?>"><?= e(t('Reiseziele', 'Destinations')) ?></a>
        <a href="<?= e(url('index.php?lang=' . $currentLang . '#how')) ?>"><?= e(t('So funktioniert es', 'How it works')) ?></a>
        <a href="<?= e(url('index.php?lang=' . $currentLang . '#about')) ?>"><?= e(t('Über uns', 'About us')) ?></a>
        <a href="<?= e(url(($portalHeaderUser ? 'account/index.php' : 'account/login.php') . '?lang=' . $currentLang)) ?>"><?= e($portalHeaderUser ? t('Mein Konto','My account') : t('Anmelden','Sign in')) ?></a>
    </nav>
    <div class="header-tools">
        <div class="language-switch">
            <a class="<?= $currentLang === 'de' ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($query, ['lang' => 'de']))) ?>">DE</a>
            <span>/</span>
            <a class="<?= $currentLang === 'en' ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($query, ['lang' => 'en']))) ?>">EN</a>
        </div>
        <a class="header-plan" href="<?= e(url(($portalHeaderUser ? 'account/index.php' : 'plan.php') . '?lang=' . $currentLang)) ?>"><?= e($portalHeaderUser ? ($portalHeaderUser['user_type']==='agent' ? t('Agentur-Portal','Agent portal') : t('Mein Konto','My account')) : t('Meine Reise', 'My journey')) ?> <span>→</span></a>
    </div>
</header>
<?php foreach (pull_flashes() as $flash): ?>
    <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
