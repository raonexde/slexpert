<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vehicles (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL UNIQUE,
            name_de VARCHAR(120) NOT NULL,
            name_en VARCHAR(120) NOT NULL,
            capacity SMALLINT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_vehicles_active_capacity (active, capacity, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $defaults = [
        ['car', 'Auto', 'Car', 3, 10],
        ['mini_van', 'Mini Van', 'Mini Van', 4, 20],
        ['van', 'Van', 'Van', 7, 30],
        ['mini_bus', 'Mini Bus', 'Mini Bus', 20, 40],
        ['bus', 'Bus', 'Bus', 50, 50],
    ];
    $insertVehicle = $pdo->prepare(
        'INSERT IGNORE INTO vehicles (code,name_de,name_en,capacity,active,sort_order) VALUES (?,?,?,?,1,?)'
    );
    foreach ($defaults as $vehicle) $insertVehicle->execute($vehicle);

    $columns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('vehicle_id', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_id INT UNSIGNED NULL AFTER travelers');
    }
    if (!in_array('vehicle_name_de', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_requests ADD COLUMN vehicle_name_de VARCHAR(120) NOT NULL DEFAULT '' AFTER vehicle_id");
    }
    if (!in_array('vehicle_name_en', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_requests ADD COLUMN vehicle_name_en VARCHAR(120) NOT NULL DEFAULT '' AFTER vehicle_name_de");
    }
    if (!in_array('vehicle_capacity', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD COLUMN vehicle_capacity SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER vehicle_name_en');
    }
    $pdo->exec('ALTER TABLE tour_requests MODIFY COLUMN duration SMALLINT UNSIGNED NOT NULL DEFAULT 1');

    $nightTotals = $pdo->query(
        'SELECT r.id, COALESCE(SUM(rd.nights),0) AS total_nights
         FROM tour_requests r
         LEFT JOIN tour_request_destinations rd ON rd.request_id=r.id
         GROUP BY r.id'
    )->fetchAll();
    $updateNights = $pdo->prepare('UPDATE tour_requests SET duration=? WHERE id=?');
    foreach ($nightTotals as $request) {
        $updateNights->execute([max(1, (int)$request['total_nights']), (int)$request['id']]);
    }

    $fleet = $pdo->query('SELECT id,name_de,name_en,capacity FROM vehicles WHERE active=1 ORDER BY capacity,sort_order,id')->fetchAll();
    $requests = $pdo->query('SELECT id,travelers,vehicle_id FROM tour_requests')->fetchAll();
    $updateVehicle = $pdo->prepare(
        'UPDATE tour_requests SET vehicle_id=?,vehicle_name_de=?,vehicle_name_en=?,vehicle_capacity=? WHERE id=?'
    );
    foreach ($requests as $request) {
        if ((int)$request['vehicle_id'] > 0) continue;
        foreach ($fleet as $vehicle) {
            if ((int)$vehicle['capacity'] < (int)$request['travelers']) continue;
            $updateVehicle->execute([
                (int)$vehicle['id'], $vehicle['name_de'], $vehicle['name_en'],
                (int)$vehicle['capacity'], (int)$request['id'],
            ]);
            break;
        }
    }
};
