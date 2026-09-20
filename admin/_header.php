<?php
declare(strict_types=1);
require_admin();
$adminPage = $adminPage ?? '';
$adminTitle = $adminTitle ?? 'Admin';
$user = admin_user();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($adminTitle) ?> · Admin</title>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<main class="admin-shell">
    <aside class="sidebar">
        <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>"><span>SL</span><strong>SRI LANKA<small>EXPERT</small></strong></a>
        <p>VERWALTUNG</p>
        <nav>
            <a class="<?= $adminPage === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>"><span>◫</span>Übersicht</a>
            <a class="<?= $adminPage === 'requests' ? 'active' : '' ?>" href="<?= e(url('admin/requests.php')) ?>"><span>◇</span>Reiseanfragen</a>
            <a class="<?= $adminPage === 'bookings' ? 'active' : '' ?>" href="<?= e(url('admin/bookings.php')) ?>"><span>▣</span>Buchungen</a>
            <a class="<?= $adminPage === 'customers' ? 'active' : '' ?>" href="<?= e(url('admin/customers.php')) ?>"><span>◉</span>Kundenkonten</a>
            <a class="<?= $adminPage === 'agents' ? 'active' : '' ?>" href="<?= e(url('admin/agents.php')) ?>"><span>◎</span>B2B-Agenturen</a>
            <a class="<?= $adminPage === 'tours' ? 'active' : '' ?>" href="<?= e(url('admin/tours.php')) ?>"><span>◈</span>Fertige Reisen</a>
            <a class="<?= $adminPage === 'catalog' ? 'active' : '' ?>" href="<?= e(url('admin/catalog.php')) ?>"><span>▦</span>Reisebausteine</a>
            <a class="<?= $adminPage === 'classifications' ? 'active' : '' ?>" href="<?= e(url('admin/classifications.php')) ?>"><span>≡</span>Kategorien</a>
            <a class="<?= $adminPage === 'vehicles' ? 'active' : '' ?>" href="<?= e(url('admin/vehicles.php')) ?>"><span>▤</span>Fahrzeugflotte</a>
            <a class="<?= $adminPage === 'guides' ? 'active' : '' ?>" href="<?= e(url('admin/guides.php')) ?>"><span>◎</span>Reiseleiter</a>
            <a class="<?= $adminPage === 'settings' ? 'active' : '' ?>" href="<?= e(url('admin/settings.php')) ?>"><span>⚙</span>Einstellungen</a>
            <a href="<?= e(url('plan.php?mode=admin')) ?>"><span>＋</span>Kundenreise planen</a>
            <a href="<?= e(url('stay.php?type=beach&mode=admin')) ?>"><span>＋</span>Strandaufenthalt</a>
            <a href="<?= e(url('stay.php?type=ayurveda&mode=admin')) ?>"><span>＋</span>Ayurveda-Aufenthalt</a>
            <a href="<?= e(url('stay.php?type=maldives&mode=admin')) ?>"><span>＋</span>Malediven-Aufenthalt</a>
        </nav>
        <div class="sidebar-user"><span><?= e(strtoupper(substr($user['name'],0,2))) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div></div>
    </aside>
    <section class="admin-workspace">
        <header class="topbar">
            <button type="button" data-sidebar-toggle>☰</button>
            <div><small>SRI LANKA EXPERT</small><strong>Admin Portal</strong></div>
            <nav><a href="<?= e(url()) ?>" target="_blank">Website ansehen ↗</a><a class="top-primary" href="<?= e(url('plan.php?mode=admin')) ?>">＋ Neue Reise</a><form action="<?= e(url('admin/logout.php')) ?>" method="post"><?= csrf_field() ?><button type="submit">Abmelden</button></form></nav>
        </header>
        <?php foreach (pull_flashes() as $flash): ?><div class="admin-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
