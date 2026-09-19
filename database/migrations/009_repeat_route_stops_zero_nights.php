<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $routeIndexes = $pdo->query('SHOW INDEX FROM tour_request_destinations')->fetchAll();
    $routeIndexNames = array_values(array_unique(array_column($routeIndexes, 'Key_name')));
    if (!in_array('idx_request_destination_route', $routeIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_request_destinations ADD INDEX idx_request_destination_route (request_id, sort_order)');
    }
    if (in_array('uq_request_destination', $routeIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_request_destinations DROP INDEX uq_request_destination');
    }
    $pdo->exec('ALTER TABLE tour_request_destinations MODIFY COLUMN nights TINYINT UNSIGNED NOT NULL DEFAULT 0');
    $pdo->exec('ALTER TABLE tour_requests MODIFY COLUMN duration SMALLINT UNSIGNED NOT NULL DEFAULT 0');

    $itemColumns = $pdo->query('SHOW COLUMNS FROM tour_request_items')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('request_destination_id', $itemColumns, true)) {
        $pdo->exec('ALTER TABLE tour_request_items ADD COLUMN request_destination_id INT UNSIGNED NULL AFTER request_id');
    }

    $itemIndexes = $pdo->query('SHOW INDEX FROM tour_request_items')->fetchAll();
    $itemIndexNames = array_values(array_unique(array_column($itemIndexes, 'Key_name')));
    if (!in_array('idx_request_item_request', $itemIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_request_items ADD INDEX idx_request_item_request (request_id)');
    }
    if (!in_array('idx_request_item_stop', $itemIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_request_items ADD INDEX idx_request_item_stop (request_destination_id)');
    }
    if (in_array('uq_request_item', $itemIndexNames, true)) {
        $pdo->exec('ALTER TABLE tour_request_items DROP INDEX uq_request_item');
    }

    $foreignKey = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tour_request_items'
           AND CONSTRAINT_NAME='fk_request_item_stop'"
    );
    $foreignKey->execute();
    if ((int)$foreignKey->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE tour_request_items
             ADD CONSTRAINT fk_request_item_stop
             FOREIGN KEY (request_destination_id) REFERENCES tour_request_destinations(id) ON DELETE CASCADE'
        );
    }
};
