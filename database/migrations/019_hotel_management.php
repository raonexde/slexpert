<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS hotel_room_types (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hotel_id INT UNSIGNED NOT NULL,
        code VARCHAR(80) NOT NULL,
        name_de VARCHAR(190) NOT NULL,
        name_en VARCHAR(190) NOT NULL,
        description_de TEXT NOT NULL,
        description_en TEXT NOT NULL,
        bed_type_de VARCHAR(120) NOT NULL DEFAULT '',
        bed_type_en VARCHAR(120) NOT NULL DEFAULT '',
        amenities_de TEXT NOT NULL,
        amenities_en TEXT NOT NULL,
        base_rate_per_room_night DECIMAL(10,2) NOT NULL DEFAULT 0,
        standard_guests TINYINT UNSIGNED NOT NULL DEFAULT 2,
        max_guests TINYINT UNSIGNED NOT NULL DEFAULT 3,
        extra_bed_allowed TINYINT(1) NOT NULL DEFAULT 1,
        extra_bed_percent DECIMAL(5,2) NOT NULL DEFAULT 30,
        child_percent DECIMAL(5,2) NOT NULL DEFAULT 50,
        room_size_sqm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        inventory SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        image_path VARCHAR(255) NULL,
        featured TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_hotel_room_hotel FOREIGN KEY (hotel_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
        UNIQUE KEY uq_hotel_room_code (hotel_id, code),
        INDEX idx_hotel_room_active (hotel_id, active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hotel_room_rates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        room_type_id INT UNSIGNED NOT NULL,
        label_de VARCHAR(120) NOT NULL,
        label_en VARCHAR(120) NOT NULL,
        valid_from DATE NULL,
        valid_to DATE NULL,
        price_per_room_night DECIMAL(10,2) NOT NULL DEFAULT 0,
        minimum_nights TINYINT UNSIGNED NOT NULL DEFAULT 1,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_hotel_rate_room FOREIGN KEY (room_type_id) REFERENCES hotel_room_types(id) ON DELETE CASCADE,
        INDEX idx_hotel_rate_dates (room_type_id, active, valid_from, valid_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS hotel_related_items (
        hotel_id INT UNSIGNED NOT NULL,
        catalog_item_id INT UNSIGNED NOT NULL,
        included TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (hotel_id, catalog_item_id),
        CONSTRAINT fk_hotel_related_hotel FOREIGN KEY (hotel_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_hotel_related_item FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
        INDEX idx_hotel_related_sort (hotel_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = $pdo->query("SHOW COLUMNS FROM tour_request_items")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('room_type_id', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_request_items ADD COLUMN room_type_id INT UNSIGNED NULL AFTER room_count");
    }
    if (!in_array('room_type_code', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_request_items ADD COLUMN room_type_code VARCHAR(80) NOT NULL DEFAULT '' AFTER room_type_id");
    }
    if (!in_array('room_type_name_de', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_request_items ADD COLUMN room_type_name_de VARCHAR(190) NOT NULL DEFAULT '' AFTER room_type_code");
    }
    if (!in_array('room_type_name_en', $columns, true)) {
        $pdo->exec("ALTER TABLE tour_request_items ADD COLUMN room_type_name_en VARCHAR(190) NOT NULL DEFAULT '' AFTER room_type_name_de");
    }

    $seed = $pdo->prepare("INSERT INTO hotel_room_types
        (hotel_id,code,name_de,name_en,description_de,description_en,bed_type_de,bed_type_en,amenities_de,amenities_en,base_rate_per_room_night,standard_guests,max_guests,extra_bed_allowed,extra_bed_percent,child_percent,featured,active,sort_order)
        SELECT c.id,'double-room','Doppelzimmer','Double room','','','Doppelbett oder zwei Einzelbetten','Double bed or twin beds','','',c.price_per_person,c.standard_room_guests,c.max_guests_with_extra_bed,1,c.extra_bed_percent,c.child_percent,1,1,10
        FROM catalog_items c
        WHERE c.type='accommodation'
          AND NOT EXISTS (SELECT 1 FROM hotel_room_types r WHERE r.hotel_id=c.id)");
    $seed->execute();
};
