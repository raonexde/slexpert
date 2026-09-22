<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM admin_users')->fetchAll(PDO::FETCH_COLUMN);
    $addedSuperAdmin = false;
    if (!in_array('is_super_admin', $columns, true)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
        $addedSuperAdmin = true;
    }
    if (!in_array('active', $columns, true)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_super_admin");
    }
    if (!in_array('last_login_at', $columns, true)) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN last_login_at DATETIME NULL AFTER active");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_user_permissions (
        admin_user_id INT UNSIGNED NOT NULL,
        module_key VARCHAR(50) NOT NULL,
        can_view TINYINT(1) NOT NULL DEFAULT 0,
        can_manage TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (admin_user_id, module_key),
        CONSTRAINT fk_admin_permission_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
        INDEX idx_admin_permission_module (module_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Existing administrators must not lose access during the upgrade.
    if ($addedSuperAdmin) {
        $pdo->exec('UPDATE admin_users SET is_super_admin=1');
    }
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    $superCount = (int)$pdo->query('SELECT COUNT(*) FROM admin_users WHERE is_super_admin=1')->fetchColumn();
    if ($userCount > 0 && $superCount === 0) {
        $pdo->exec('UPDATE admin_users SET is_super_admin=1');
    }
};
