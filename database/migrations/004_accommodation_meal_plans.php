<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS accommodation_meal_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            catalog_item_id INT UNSIGNED NOT NULL,
            code VARCHAR(40) NOT NULL,
            name_de VARCHAR(120) NOT NULL,
            name_en VARCHAR(120) NOT NULL,
            supplement_per_person_night DECIMAL(10,2) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_meal_plan_accommodation FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
            UNIQUE KEY uq_accommodation_meal_plan (catalog_item_id, code),
            INDEX idx_meal_plan_item_active_sort (catalog_item_id, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $columns = $pdo->query('SHOW COLUMNS FROM tour_request_items')->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = [
        'meal_plan_code' => "VARCHAR(40) NOT NULL DEFAULT '' AFTER unit_price",
        'meal_plan_name_de' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER meal_plan_code",
        'meal_plan_name_en' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER meal_plan_name_de",
        'meal_plan_supplement' => 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER meal_plan_name_en',
    ];
    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE tour_request_items ADD COLUMN {$column} {$definition}");
        }
    }

    $defaults = [
        ['breakfast', 'Frühstück', 'Breakfast', 0.00, 10],
        ['room_only', 'Nur Übernachtung', 'Room only', 0.00, 20],
        ['half_board', 'Halbpension', 'Half board', 28.00, 30],
        ['full_board', 'Vollpension', 'Full board', 46.00, 40],
        ['all_inclusive', 'All-inclusive', 'All inclusive', 68.00, 50],
    ];
    $insert = $pdo->prepare(
        "INSERT IGNORE INTO accommodation_meal_plans
         (catalog_item_id, code, name_de, name_en, supplement_per_person_night, active, sort_order)
         SELECT id, ?, ?, ?, ?, 1, ? FROM catalog_items WHERE type='accommodation'"
    );
    foreach ($defaults as $plan) {
        $insert->execute($plan);
    }
};
