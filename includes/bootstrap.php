<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ceylon_tours_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
}

require_once __DIR__ . '/functions.php';
ensure_installed();

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Berlin');

require_once __DIR__ . '/database.php';
run_pending_migrations();
require_once __DIR__ . '/auth.php';
