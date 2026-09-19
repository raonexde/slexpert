<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS b2b_agents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            agency_code VARCHAR(40) NOT NULL UNIQUE,
            company_name VARCHAR(190) NOT NULL,
            contact_name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(80) NOT NULL DEFAULT '',
            address TEXT NOT NULL,
            country VARCHAR(120) NOT NULL DEFAULT '',
            tax_id VARCHAR(120) NOT NULL DEFAULT '',
            commission_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            notes TEXT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_b2b_agents_active_company (active, company_name),
            INDEX idx_b2b_agents_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS portal_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_type ENUM('customer','agent') NOT NULL DEFAULT 'customer',
            b2b_agent_id INT UNSIGNED NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(80) NOT NULL DEFAULT '',
            language ENUM('de','en') NOT NULL DEFAULT 'de',
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_portal_user_agent FOREIGN KEY (b2b_agent_id) REFERENCES b2b_agents(id) ON DELETE SET NULL,
            INDEX idx_portal_users_type_active (user_type, active),
            INDEX idx_portal_users_agent (b2b_agent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $requestColumns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('customer_user_id', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN customer_user_id INT UNSIGNED NULL AFTER reference');
    }
    $requestColumns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('b2b_agent_id', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN b2b_agent_id INT UNSIGNED NULL AFTER customer_user_id');
    }

    $requestIndexes = $pdo->query('SHOW INDEX FROM tour_requests')->fetchAll();
    $requestIndexNames = array_values(array_unique(array_column($requestIndexes, 'Key_name')));
    if (!in_array('idx_requests_customer', $requestIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD INDEX idx_requests_customer (customer_user_id, created_at)');
    }
    if (!in_array('idx_requests_agent', $requestIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD INDEX idx_requests_agent (b2b_agent_id, created_at)');
    }

    $foreignKeyExists = static function (string $table, string $constraint) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?'
        );
        $stmt->execute([$table, $constraint]);
        return (int)$stmt->fetchColumn() > 0;
    };
    if (!$foreignKeyExists('tour_requests', 'fk_request_customer_user')) {
        $pdo->exec('ALTER TABLE tour_requests ADD CONSTRAINT fk_request_customer_user FOREIGN KEY (customer_user_id) REFERENCES portal_users(id) ON DELETE SET NULL');
    }
    if (!$foreignKeyExists('tour_requests', 'fk_request_b2b_agent')) {
        $pdo->exec('ALTER TABLE tour_requests ADD CONSTRAINT fk_request_b2b_agent FOREIGN KEY (b2b_agent_id) REFERENCES b2b_agents(id) ON DELETE SET NULL');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_reference VARCHAR(24) NOT NULL UNIQUE,
            request_id INT UNSIGNED NOT NULL UNIQUE,
            customer_user_id INT UNSIGNED NULL,
            b2b_agent_id INT UNSIGNED NULL,
            status ENUM('provisional','confirmed','in_progress','completed','cancelled') NOT NULL DEFAULT 'provisional',
            payment_status ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            travel_start_date DATE NULL,
            travel_end_date DATE NULL,
            payment_due_date DATE NULL,
            agent_commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            agent_commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            customer_notes TEXT NOT NULL,
            internal_notes TEXT NOT NULL,
            created_by_admin_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_booking_request FOREIGN KEY (request_id) REFERENCES tour_requests(id) ON DELETE RESTRICT,
            CONSTRAINT fk_booking_customer FOREIGN KEY (customer_user_id) REFERENCES portal_users(id) ON DELETE SET NULL,
            CONSTRAINT fk_booking_agent FOREIGN KEY (b2b_agent_id) REFERENCES b2b_agents(id) ON DELETE SET NULL,
            CONSTRAINT fk_booking_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_bookings_status_created (status, created_at),
            INDEX idx_bookings_payment_status (payment_status, created_at),
            INDEX idx_bookings_customer (customer_user_id, created_at),
            INDEX idx_bookings_agent (b2b_agent_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS booking_payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method ENUM('bank_transfer','card','paypal','cash','other') NOT NULL DEFAULT 'bank_transfer',
            transaction_reference VARCHAR(190) NOT NULL DEFAULT '',
            notes TEXT NOT NULL,
            created_by_admin_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
            CONSTRAINT fk_payment_admin FOREIGN KEY (created_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_booking_payments_booking_date (booking_id, payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
