<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $catalogColumns = $pdo->query('SHOW COLUMNS FROM catalog_items')->fetchAll(PDO::FETCH_COLUMN);
    $catalogAdds = [
        'price_basis' => "ALTER TABLE catalog_items ADD COLUMN price_basis ENUM('per_person','per_person_night','per_booking') NOT NULL DEFAULT 'per_person' AFTER price_per_person",
        'beach_available' => "ALTER TABLE catalog_items ADD COLUMN beach_available TINYINT(1) NOT NULL DEFAULT 0 AFTER price_basis",
        'ayurveda_available' => "ALTER TABLE catalog_items ADD COLUMN ayurveda_available TINYINT(1) NOT NULL DEFAULT 0 AFTER beach_available",
        'standard_room_guests' => "ALTER TABLE catalog_items ADD COLUMN standard_room_guests TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER ayurveda_available",
        'max_guests_with_extra_bed' => "ALTER TABLE catalog_items ADD COLUMN max_guests_with_extra_bed TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER standard_room_guests",
        'extra_bed_percent' => "ALTER TABLE catalog_items ADD COLUMN extra_bed_percent DECIMAL(5,2) NOT NULL DEFAULT 30.00 AFTER max_guests_with_extra_bed",
        'child_percent' => "ALTER TABLE catalog_items ADD COLUMN child_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER extra_bed_percent",
        'child_max_age' => "ALTER TABLE catalog_items ADD COLUMN child_max_age TINYINT UNSIGNED NOT NULL DEFAULT 12 AFTER child_percent",
        'ayurveda_night_options' => "ALTER TABLE catalog_items ADD COLUMN ayurveda_night_options VARCHAR(80) NOT NULL DEFAULT '7,14,21,28' AFTER child_max_age",
    ];
    foreach ($catalogAdds as $column => $sql) {
        if (!in_array($column, $catalogColumns, true)) $pdo->exec($sql);
    }
    $pdo->exec("ALTER TABLE catalog_items MODIFY COLUMN type ENUM('accommodation','sight','activity','shop','service') NOT NULL");
    $pdo->exec("UPDATE catalog_items SET price_basis='per_person' WHERE type<>'accommodation'");

    $beachCodes = "'NEG','KAL','WAD','BEN','HIK','GAL','KOG','MIR','MAT','TAN','TRI','PAS','BAT','ARU','JAF','MAN','CHI','HAM','MUL'";
    $pdo->exec("UPDATE catalog_items c JOIN destinations d ON d.id=c.destination_id SET c.beach_available=1 WHERE c.type='accommodation' AND d.code IN ($beachCodes)");
    $pdo->exec("UPDATE catalog_items c JOIN (SELECT MIN(c2.id) id FROM catalog_items c2 JOIN destinations d2 ON d2.id=c2.destination_id WHERE c2.type='accommodation' AND d2.code IN ('NEG','BEN','SIG','KAN','TAN') GROUP BY c2.destination_id) chosen ON chosen.id=c.id SET c.ayurveda_available=1");
    $pdo->exec("UPDATE catalog_items SET beach_available=1,ayurveda_available=1 WHERE type IN ('sight','activity','shop')");
    $pdo->exec("UPDATE accommodation_meal_plans mp JOIN catalog_items c ON c.id=mp.catalog_item_id SET mp.active=1 WHERE c.ayurveda_available=1 AND mp.code='all_inclusive'");

    $requestColumns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    $requestAdds = [
        'request_type' => "ALTER TABLE tour_requests ADD COLUMN request_type ENUM('tour','beach','ayurveda') NOT NULL DEFAULT 'tour' AFTER language",
        'adults' => "ALTER TABLE tour_requests ADD COLUMN adults TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER travelers",
        'children' => "ALTER TABLE tour_requests ADD COLUMN children TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER adults",
        'rooms' => "ALTER TABLE tour_requests ADD COLUMN rooms TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER children",
        'hotel_name_de' => "ALTER TABLE tour_requests ADD COLUMN hotel_name_de VARCHAR(190) NOT NULL DEFAULT '' AFTER rooms",
        'hotel_name_en' => "ALTER TABLE tour_requests ADD COLUMN hotel_name_en VARCHAR(190) NOT NULL DEFAULT '' AFTER hotel_name_de",
        'room_cost' => "ALTER TABLE tour_requests ADD COLUMN room_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER hotel_name_en",
        'extra_bed_cost' => "ALTER TABLE tour_requests ADD COLUMN extra_bed_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER room_cost",
        'meal_cost' => "ALTER TABLE tour_requests ADD COLUMN meal_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER extra_bed_cost",
        'services_cost' => "ALTER TABLE tour_requests ADD COLUMN services_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER meal_cost",
    ];
    foreach ($requestAdds as $column => $sql) {
        if (!in_array($column, $requestColumns, true)) $pdo->exec($sql);
    }
    $pdo->exec("UPDATE tour_requests SET adults=travelers WHERE request_type='tour' AND adults=2 AND travelers<>2");

    $itemColumns = $pdo->query('SHOW COLUMNS FROM tour_request_items')->fetchAll(PDO::FETCH_COLUMN);
    $itemAdds = [
        'price_basis' => "ALTER TABLE tour_request_items ADD COLUMN price_basis VARCHAR(30) NOT NULL DEFAULT 'per_person' AFTER unit_price",
        'line_total' => "ALTER TABLE tour_request_items ADD COLUMN line_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_basis",
        'adult_quantity' => "ALTER TABLE tour_request_items ADD COLUMN adult_quantity TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER line_total",
        'child_quantity' => "ALTER TABLE tour_request_items ADD COLUMN child_quantity TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER adult_quantity",
        'extra_bed_adults' => "ALTER TABLE tour_request_items ADD COLUMN extra_bed_adults TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER child_quantity",
        'extra_bed_children' => "ALTER TABLE tour_request_items ADD COLUMN extra_bed_children TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER extra_bed_adults",
        'extra_bed_percent' => "ALTER TABLE tour_request_items ADD COLUMN extra_bed_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER extra_bed_children",
        'child_percent' => "ALTER TABLE tour_request_items ADD COLUMN child_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER extra_bed_percent",
    ];
    foreach ($itemAdds as $column => $sql) {
        if (!in_array($column, $itemColumns, true)) $pdo->exec($sql);
    }

    $ayurvedaDestinations = $pdo->query("SELECT DISTINCT d.id,d.name_de,d.name_en FROM destinations d JOIN catalog_items c ON c.destination_id=d.id WHERE c.type='accommodation' AND c.ayurveda_available=1")->fetchAll();
    $exists = $pdo->prepare("SELECT COUNT(*) FROM catalog_items WHERE destination_id=? AND type='service' AND name_en LIKE 'Ayurveda wellness package%'");
    $insert = $pdo->prepare("INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,price_basis,beach_available,ayurveda_available,featured,active,sort_order) VALUES (?,'service',?,?,?,?,?,?,?,'per_person_night',0,1,1,1,85)");
    foreach ($ayurvedaDestinations as $destination) {
        $exists->execute([(int)$destination['id']]);
        if ((int)$exists->fetchColumn() > 0) continue;
        $insert->execute([
            (int)$destination['id'],
            'Ayurveda-Wellnesspaket – ' . $destination['name_de'],
            'Ayurveda wellness package – ' . $destination['name_en'],
            'Editierbares Beispielpaket mit Erstberatung, täglichen Anwendungen, Yoga und persönlicher Betreuung.',
            'Editable sample package with an initial consultation, daily treatments, yoga and personal guidance.',
            'Pro Person / Nacht · Leistungen im Admin anpassen',
            'Per person / night · Edit inclusions in admin',
            72.00,
        ]);
    }

    $stayDestinations = $pdo->query("SELECT d.id,d.name_de,d.name_en,MAX(c.beach_available) beach_available,MAX(c.ayurveda_available) ayurveda_available FROM destinations d JOIN catalog_items c ON c.destination_id=d.id WHERE c.type='accommodation' AND (c.beach_available=1 OR c.ayurveda_available=1) GROUP BY d.id,d.name_de,d.name_en")->fetchAll();
    $transferExists = $pdo->prepare("SELECT COUNT(*) FROM catalog_items WHERE destination_id=? AND type='service' AND name_en LIKE 'Private airport transfer%'");
    $transferInsert = $pdo->prepare("INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,price_basis,beach_available,ayurveda_available,featured,active,sort_order) VALUES (?,'service',?,?,?,?,?,?,?,'per_booking',?,?,0,1,80)");
    foreach ($stayDestinations as $destination) {
        $transferExists->execute([(int)$destination['id']]);
        if ((int)$transferExists->fetchColumn() > 0) continue;
        $transferInsert->execute([
            (int)$destination['id'],
            'Privater Flughafentransfer – ' . $destination['name_de'],
            'Private airport transfer – ' . $destination['name_en'],
            'Editierbarer Beispielpreis für einen privaten Transfer zwischen Flughafen und Hotel.',
            'Editable sample price for a private transfer between the airport and hotel.',
            'Pro Buchung · Strecke und Fahrzeug im Admin anpassen',
            'Per booking · Edit route and vehicle in admin',
            65.00,(int)$destination['beach_available'],(int)$destination['ayurveda_available'],
        ]);
    }
};
