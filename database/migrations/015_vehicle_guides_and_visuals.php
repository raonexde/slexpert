<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $vehicleColumns = $pdo->query('SHOW COLUMNS FROM vehicles')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('image_path', $vehicleColumns, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN image_path VARCHAR(255) NULL AFTER driver_price_per_km');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS guides (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            guide_code VARCHAR(40) NOT NULL UNIQUE,
            status ENUM('pending','approved','suspended','rejected') NOT NULL DEFAULT 'pending',
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            display_name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(80) NOT NULL DEFAULT '',
            whatsapp VARCHAR(80) NOT NULL DEFAULT '',
            date_of_birth DATE NULL,
            gender VARCHAR(40) NOT NULL DEFAULT '',
            nationality VARCHAR(100) NOT NULL DEFAULT '',
            address TEXT NOT NULL,
            city VARCHAR(120) NOT NULL DEFAULT '',
            district VARCHAR(120) NOT NULL DEFAULT '',
            country VARCHAR(120) NOT NULL DEFAULT 'Sri Lanka',
            nic_passport VARCHAR(120) NOT NULL DEFAULT '',
            license_number VARCHAR(120) NOT NULL DEFAULT '',
            license_type VARCHAR(120) NOT NULL DEFAULT '',
            license_expiry DATE NULL,
            tourism_registration VARCHAR(120) NOT NULL DEFAULT '',
            years_experience TINYINT UNSIGNED NOT NULL DEFAULT 0,
            languages VARCHAR(500) NOT NULL DEFAULT '',
            service_regions VARCHAR(500) NOT NULL DEFAULT '',
            specializations VARCHAR(500) NOT NULL DEFAULT '',
            driver_guide TINYINT(1) NOT NULL DEFAULT 0,
            daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
            bio_de TEXT NOT NULL,
            bio_en TEXT NOT NULL,
            emergency_name VARCHAR(150) NOT NULL DEFAULT '',
            emergency_phone VARCHAR(80) NOT NULL DEFAULT '',
            photo_path VARCHAR(255) NULL,
            license_document_path VARCHAR(255) NULL,
            identity_document_path VARCHAR(255) NULL,
            insurance_document_path VARCHAR(255) NULL,
            admin_notes TEXT NOT NULL,
            consent_at DATETIME NULL,
            active TINYINT(1) NOT NULL DEFAULT 0,
            featured TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_guides_status_active (status, active, sort_order),
            INDEX idx_guides_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $requestColumns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    $requestAdds = [
        'vehicle_image_path' => "VARCHAR(255) NULL AFTER vehicle_cost",
        'guide_id' => "INT UNSIGNED NULL AFTER vehicle_image_path",
        'guide_name' => "VARCHAR(190) NOT NULL DEFAULT '' AFTER guide_id",
        'guide_photo_path' => "VARCHAR(255) NULL AFTER guide_name",
        'guide_languages' => "VARCHAR(500) NOT NULL DEFAULT '' AFTER guide_photo_path",
        'guide_days' => "SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER guide_languages",
        'guide_daily_rate' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER guide_days",
        'guide_cost' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER guide_daily_rate",
    ];
    foreach ($requestAdds as $column => $definition) {
        if (!in_array($column, $requestColumns, true)) {
            $pdo->exec("ALTER TABLE tour_requests ADD COLUMN $column $definition");
            $requestColumns[] = $column;
        }
    }
    $foreignKey = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tour_requests' AND CONSTRAINT_NAME='fk_request_guide'");
    $foreignKey->execute();
    if ((int)$foreignKey->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE tour_requests ADD CONSTRAINT fk_request_guide FOREIGN KEY (guide_id) REFERENCES guides(id) ON DELETE SET NULL');
    }

    // Apply original, local starter visuals only where the administrator has not supplied an image.
    $atollVisuals = [
        'maldives-north-male' => 'assets/images/maldives/atoll-lagoon.svg',
        'maldives-south-male' => 'assets/images/maldives/atoll-sandbank.svg',
        'maldives-north-ari' => 'assets/images/maldives/atoll-reef.svg',
        'maldives-south-ari' => 'assets/images/maldives/atoll-lagoon.svg',
        'maldives-baa' => 'assets/images/maldives/atoll-reef.svg',
        'maldives-raa' => 'assets/images/maldives/atoll-sandbank.svg',
        'maldives-noonu' => 'assets/images/maldives/atoll-lagoon.svg',
        'maldives-lhaviyani' => 'assets/images/maldives/atoll-reef.svg',
        'maldives-dhaalu' => 'assets/images/maldives/atoll-sandbank.svg',
    ];
    $atollUpdate = $pdo->prepare("UPDATE destinations SET image_path=? WHERE slug=? AND (image_path IS NULL OR image_path='')");
    foreach ($atollVisuals as $slug => $path) $atollUpdate->execute([$path, $slug]);

    $resortPaths = [
        'assets/images/maldives/resort-water-villa.svg',
        'assets/images/maldives/resort-beach-villa.svg',
        'assets/images/maldives/resort-sunset.svg',
    ];
    $resorts = $pdo->query("SELECT c.id FROM catalog_items c JOIN destinations d ON d.id=c.destination_id WHERE d.country_code='MV' AND c.type='accommodation' AND (c.image_path IS NULL OR c.image_path='') ORDER BY c.id")->fetchAll(PDO::FETCH_COLUMN);
    $resortUpdate = $pdo->prepare('UPDATE catalog_items SET image_path=? WHERE id=?');
    foreach ($resorts as $index => $resortId) $resortUpdate->execute([$resortPaths[$index % count($resortPaths)], $resortId]);
};
