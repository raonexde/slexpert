<?php
declare(strict_types=1);

function admin_user(): ?array
{
    static $checked = false;
    if (!isset($_SESSION['admin_user']) || !is_array($_SESSION['admin_user'])) return null;
    if (!$checked) {
        $checked = true;
        $stmt = db()->prepare('SELECT id,name,email,role,is_super_admin,active FROM admin_users WHERE id=? LIMIT 1');
        $stmt->execute([(int)$_SESSION['admin_user']['id']]);
        $fresh = $stmt->fetch();
        if (!$fresh || !(int)$fresh['active']) {
            unset($_SESSION['admin_user']);
            return null;
        }
        $_SESSION['admin_user'] = $fresh;
    }
    return $_SESSION['admin_user'];
}

function require_admin(): void
{
    if (!admin_user()) {
        $_SESSION['login_return'] = $_SERVER['REQUEST_URI'] ?? url('admin/index.php');
        redirect('admin/login.php');
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_super_admin, active FROM admin_users WHERE email = ? AND active = 1 LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    unset($user['password_hash']);
    $_SESSION['admin_user'] = $user;
    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    return true;
}

function admin_permission_modules(): array
{
    return [
        'requests' => ['label'=>'Reiseanfragen', 'description'=>'Anfragen, Angebote und private Reiseunterlagen'],
        'bookings' => ['label'=>'Buchungen & Zahlungen', 'description'=>'Buchungen, Rechnungswerte und Zahlungseingänge'],
        'customers' => ['label'=>'Kundenkonten', 'description'=>'Kundendaten, Zugänge und Passwörter'],
        'agents' => ['label'=>'B2B-Agenturen', 'description'=>'Agenturen, Provisionen und Portalzugänge'],
        'tours' => ['label'=>'Fertige Reisen', 'description'=>'Reisevorlagen, Routen und Saisonpreise'],
        'hotels' => ['label'=>'Hotelverwaltung', 'description'=>'Hotels, Zimmer, Verpflegung und Saisonpreise'],
        'catalog' => ['label'=>'Reiseziele & Reisebausteine', 'description'=>'Orte, Sehenswürdigkeiten, Aktivitäten und Shops'],
        'classifications' => ['label'=>'Kategorien', 'description'=>'Unterkunfts- und Leistungskategorien'],
        'vehicles' => ['label'=>'Fahrzeugflotte', 'description'=>'Fahrzeuge, Bilder und Preise'],
        'guides' => ['label'=>'Reiseleiter', 'description'=>'Reiseleiter, Dokumente und Freigaben'],
        'settings' => ['label'=>'Einstellungen', 'description'=>'Firma, Preisaufschlag, Google Maps und Social Media'],
        'planner' => ['label'=>'Kundenreisen planen', 'description'=>'Reisen und Aufenthalte im Namen von Kunden erstellen'],
    ];
}

function admin_is_super_admin(): bool
{
    $user = admin_user();
    return $user && (int)($user['is_super_admin'] ?? 0) === 1;
}

function admin_permissions_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT module_key,can_view,can_manage FROM admin_user_permissions WHERE admin_user_id=?');
    $stmt->execute([$userId]);
    $permissions = [];
    foreach ($stmt->fetchAll() as $row) {
        $permissions[(string)$row['module_key']] = [
            'view'=>(bool)$row['can_view'],
            'manage'=>(bool)$row['can_manage'],
        ];
    }
    return $permissions;
}

function admin_can(string $module, bool $manage = false): bool
{
    static $cache = [];
    $user = admin_user();
    if (!$user) return false;
    if ((int)($user['is_super_admin'] ?? 0) === 1) return true;
    $userId = (int)$user['id'];
    if (!isset($cache[$userId])) $cache[$userId] = admin_permissions_for_user($userId);
    $permission = $cache[$userId][$module] ?? ['view'=>false,'manage'=>false];
    return $manage ? (bool)$permission['manage'] : ((bool)$permission['view'] || (bool)$permission['manage']);
}

function require_admin_permission(string $module, bool $manage = false): void
{
    require_admin();
    if (admin_can($module, $manage)) return;
    flash('error', $manage
        ? 'Sie haben für diesen Bereich keine Bearbeitungsberechtigung.'
        : 'Sie haben für diesen Bereich keine Zugriffsberechtigung.');
    redirect('admin/index.php');
}

function require_super_admin(): void
{
    require_admin();
    if (admin_is_super_admin()) return;
    flash('error', 'Die Benutzer- und Rechteverwaltung ist nur für Hauptadministratoren verfügbar.');
    redirect('admin/index.php');
}

function admin_logout(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

function portal_user(): ?array
{
    static $checked = false;
    if (!isset($_SESSION['portal_user']) || !is_array($_SESSION['portal_user'])) return null;
    if (!$checked) {
        $checked = true;
        $stmt = db()->prepare(
            "SELECT u.id,u.user_type,u.b2b_agent_id,u.name,u.email,u.phone,u.language,
                    a.company_name,a.agency_code,a.commission_percent
             FROM portal_users u LEFT JOIN b2b_agents a ON a.id=u.b2b_agent_id
             WHERE u.id=? AND u.active=1 AND (u.user_type='customer' OR a.active=1) LIMIT 1"
        );
        $stmt->execute([(int)$_SESSION['portal_user']['id']]);
        $fresh = $stmt->fetch();
        if (!$fresh) {
            unset($_SESSION['portal_user']);
            return null;
        }
        $_SESSION['portal_user'] = $fresh;
    }
    return $_SESSION['portal_user'];
}

function require_portal_user(): void
{
    if (!portal_user()) {
        $_SESSION['portal_login_return'] = $_SERVER['REQUEST_URI'] ?? url('account/index.php');
        redirect('account/login.php?lang=' . lang());
    }
}

function attempt_portal_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        "SELECT u.id,u.user_type,u.b2b_agent_id,u.name,u.email,u.phone,u.language,u.password_hash,
                a.company_name,a.agency_code,a.commission_percent
         FROM portal_users u
         LEFT JOIN b2b_agents a ON a.id=u.b2b_agent_id
         WHERE u.email=? AND u.active=1 AND (u.user_type='customer' OR a.active=1)
         LIMIT 1"
    );
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    unset($user['password_hash']);
    $_SESSION['portal_user'] = $user;
    $_SESSION['lang'] = $user['language'] === 'en' ? 'en' : 'de';
    db()->prepare('UPDATE portal_users SET last_login_at=NOW() WHERE id=?')->execute([$user['id']]);
    if ($user['user_type'] === 'customer') {
        db()->prepare(
            "UPDATE tour_requests SET customer_user_id=?
             WHERE customer_user_id IS NULL AND b2b_agent_id IS NULL AND LOWER(customer_email)=?"
        )->execute([(int)$user['id'], strtolower((string)$user['email'])]);
        db()->prepare(
            "UPDATE bookings b JOIN tour_requests r ON r.id=b.request_id
             SET b.customer_user_id=?
             WHERE b.customer_user_id IS NULL AND r.b2b_agent_id IS NULL AND LOWER(r.customer_email)=?"
        )->execute([(int)$user['id'], strtolower((string)$user['email'])]);
    }
    return true;
}

function portal_logout(): void
{
    unset($_SESSION['portal_user']);
    session_regenerate_id(true);
}

function portal_can_access_request(array $request): bool
{
    if (admin_can('requests')) return true;
    $user = portal_user();
    if (!$user) return false;
    if ($user['user_type'] === 'agent') {
        return (int)($request['b2b_agent_id'] ?? 0) > 0
            && (int)$request['b2b_agent_id'] === (int)$user['b2b_agent_id'];
    }
    return (int)($request['customer_user_id'] ?? 0) === (int)$user['id'];
}

function portal_can_access_booking(array $booking): bool
{
    if (admin_can('bookings')) return true;
    $user = portal_user();
    if (!$user) return false;
    if ($user['user_type'] === 'agent') {
        return (int)($booking['b2b_agent_id'] ?? 0) > 0
            && (int)$booking['b2b_agent_id'] === (int)$user['b2b_agent_id'];
    }
    return (int)($booking['customer_user_id'] ?? 0) === (int)$user['id'];
}
