<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('price_markup_percent', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN price_markup_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER services_cost');
    }
    $columns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('price_markup_amount', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN price_markup_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_markup_percent');
    }
    $pdo->exec("INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('global_price_markup_percent','10')");
};
