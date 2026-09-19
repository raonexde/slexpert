<?php
declare(strict_types=1);

function admin_user(): ?array
{
    return isset($_SESSION['admin_user']) && is_array($_SESSION['admin_user'])
        ? $_SESSION['admin_user']
        : null;
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
    $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM admin_users WHERE email = ? AND active = 1 LIMIT 1');
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
    if (admin_user()) return true;
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
    if (admin_user()) return true;
    $user = portal_user();
    if (!$user) return false;
    if ($user['user_type'] === 'agent') {
        return (int)($booking['b2b_agent_id'] ?? 0) > 0
            && (int)$booking['b2b_agent_id'] === (int)$user['b2b_agent_id'];
    }
    return (int)($booking['customer_user_id'] ?? 0) === (int)$user['id'];
}
