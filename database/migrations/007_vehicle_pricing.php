<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $vehicleColumns = $pdo->query('SHOW COLUMNS FROM vehicles')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('price_per_day_with_driver', $vehicleColumns, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN price_per_day_with_driver DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER capacity');
    }
    if (!in_array('self_drive_allowed', $vehicleColumns, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN self_drive_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER price_per_day_with_driver');
    }
    if (!in_array('price_per_day_without_driver', $vehicleColumns, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN price_per_day_without_driver DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER self_drive_allowed');
    }
    if (!in_array('driver_price_per_km', $vehicleColumns, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN driver_price_per_km DECIMAL(10,2) NOT NULL DEFAULT 0.30 AFTER price_per_day_without_driver');
    }
    $pdo->exec("UPDATE vehicles SET self_drive_allowed=1 WHERE code='car' AND capacity<=3");
    $pdo->exec('UPDATE vehicles SET self_drive_allowed=0 WHERE capacity>3');

    $requestColumns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('vehicle_service_type', $requestColumns, true)) {
        $pdo->exec("ALTER TABLE tour_requests ADD COLUMN vehicle_service_type VARCHAR(30) NOT NULL DEFAULT 'with_driver' AFTER vehicle_capacity");
    }
    if (!in_array('vehicle_days', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_days SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER vehicle_service_type');
    }
    if (!in_array('vehicle_daily_rate', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER vehicle_days');
    }
    if (!in_array('vehicle_km_rate', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_km_rate DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER vehicle_daily_rate');
    }
    if (!in_array('vehicle_cost', $requestColumns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER vehicle_km_rate');
    }
    $pdo->exec('UPDATE tour_requests SET vehicle_days=duration+1 WHERE vehicle_days=0');
};
