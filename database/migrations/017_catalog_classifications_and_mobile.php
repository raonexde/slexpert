<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS catalog_classifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_type VARCHAR(40) NOT NULL,
            code VARCHAR(80) NOT NULL,
            name_de VARCHAR(160) NOT NULL,
            name_en VARCHAR(160) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_catalog_classification (item_type, code),
            INDEX idx_catalog_classification_type (item_type, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec("ALTER TABLE catalog_items MODIFY COLUMN type ENUM('accommodation','sight','activity','restaurant','spice_garden','shop','service') NOT NULL");
    $pdo->exec("ALTER TABLE catalog_items MODIFY COLUMN price_basis ENUM('per_person','per_person_night','per_booking','free','on_request') NOT NULL DEFAULT 'per_person'");

    $columns = $pdo->query('SHOW COLUMNS FROM catalog_items')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [
        'classification_id' => "ALTER TABLE catalog_items ADD COLUMN classification_id INT UNSIGNED NULL AFTER type, ADD INDEX idx_catalog_classification_id (classification_id)",
        'star_rating' => "ALTER TABLE catalog_items ADD COLUMN star_rating TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER classification_id",
        'market_segment' => "ALTER TABLE catalog_items ADD COLUMN market_segment VARCHAR(30) NOT NULL DEFAULT '' AFTER star_rating",
        'sltda_registration_number' => "ALTER TABLE catalog_items ADD COLUMN sltda_registration_number VARCHAR(120) NOT NULL DEFAULT '' AFTER market_segment",
        'sltda_registration_expiry' => "ALTER TABLE catalog_items ADD COLUMN sltda_registration_expiry DATE NULL AFTER sltda_registration_number",
        'facilities_de' => "ALTER TABLE catalog_items ADD COLUMN facilities_de TEXT NOT NULL AFTER meta_en",
        'facilities_en' => "ALTER TABLE catalog_items ADD COLUMN facilities_en TEXT NOT NULL AFTER facilities_de",
        'opening_hours_de' => "ALTER TABLE catalog_items ADD COLUMN opening_hours_de VARCHAR(255) NOT NULL DEFAULT '' AFTER facilities_en",
        'opening_hours_en' => "ALTER TABLE catalog_items ADD COLUMN opening_hours_en VARCHAR(255) NOT NULL DEFAULT '' AFTER opening_hours_de",
        'duration_minutes' => "ALTER TABLE catalog_items ADD COLUMN duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER opening_hours_en",
        'booking_required' => "ALTER TABLE catalog_items ADD COLUMN booking_required TINYINT(1) NOT NULL DEFAULT 0 AFTER duration_minutes",
        'supplier_name' => "ALTER TABLE catalog_items ADD COLUMN supplier_name VARCHAR(190) NOT NULL DEFAULT '' AFTER booking_required",
        'supplier_contact' => "ALTER TABLE catalog_items ADD COLUMN supplier_contact TEXT NOT NULL AFTER supplier_name",
        'contract_price' => "ALTER TABLE catalog_items ADD COLUMN contract_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER supplier_contact",
        'commission_percent' => "ALTER TABLE catalog_items ADD COLUMN commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER contract_price",
        'internal_notes' => "ALTER TABLE catalog_items ADD COLUMN internal_notes TEXT NOT NULL AFTER commission_percent",
    ];
    foreach ($additions as $column => $sql) {
        if (!in_array($column, $columns, true)) $pdo->exec($sql);
    }

    $classifications = [
        ['accommodation','tourist_hotel','Touristenhotel','Tourist hotel',10],
        ['accommodation','classified_hotel','Klassifiziertes Hotel','Classified hotel',20],
        ['accommodation','luxury_resort','Luxusresort','Luxury resort',30],
        ['accommodation','beach_resort','Strandresort','Beach resort',40],
        ['accommodation','boutique_hotel','Boutique-Hotel','Boutique hotel',50],
        ['accommodation','boutique_villa','Boutique-Villa','Boutique villa',60],
        ['accommodation','private_villa','Private Villa','Private villa',70],
        ['accommodation','ayurveda_hotel','Ayurveda-Hotel / Retreat','Ayurveda hotel / retreat',80],
        ['accommodation','eco_lodge','Eco-Lodge','Eco lodge',90],
        ['accommodation','wildlife_lodge','Wildlife-Lodge','Wildlife lodge',100],
        ['accommodation','safari_camp','Safari-Camp','Safari camp',110],
        ['accommodation','heritage_hotel','Heritage-Hotel','Heritage hotel',120],
        ['accommodation','bungalow','Bungalow','Bungalow',130],
        ['accommodation','guest_house','Gästehaus','Guest house',140],
        ['accommodation','homestay','Homestay','Homestay',150],
        ['accommodation','serviced_apartment','Serviced Apartment','Serviced apartment',160],
        ['accommodation','holiday_home','Ferienhaus / Mietwohnung','Holiday home / rented apartment',170],
        ['accommodation','hostel','Hostel / Backpacker','Hostel / backpacker accommodation',180],
        ['accommodation','camping','Campingplatz','Camping site',190],
        ['accommodation','glamping','Glamping / Luxuscamping','Glamping / luxury camping',200],
        ['accommodation','themed_accommodation','Themenunterkunft','Themed accommodation',210],
        ['accommodation','tourist_apartment_hotel','Touristen-Apartmenthotel','Tourist apartment hotel',220],
        ['sight','heritage_site','Kultur- und Welterbestätte','Cultural and heritage site',10],
        ['sight','temple','Tempel / religiöse Stätte','Temple / religious site',20],
        ['sight','museum','Museum','Museum',30],
        ['sight','garden','Garten / botanische Anlage','Garden / botanical site',40],
        ['sight','viewpoint','Aussichtspunkt','Viewpoint',50],
        ['sight','waterfall','Wasserfall','Waterfall',60],
        ['sight','wildlife_park','Nationalpark / Naturgebiet','National park / nature site',70],
        ['activity','safari_center','Safari- und Wildlife-Center','Safari and wildlife centre',10],
        ['activity','hiking_trekking','Wandern und Trekking','Hiking and trekking',20],
        ['activity','water_sports','Wassersportzentrum','Water sports centre',30],
        ['activity','surfing','Surfcenter','Surf centre',40],
        ['activity','diving','Tauch- und Schnorchelzentrum','Diving and snorkelling centre',50],
        ['activity','rafting','Rafting- und Kajakzentrum','Rafting and kayaking centre',60],
        ['activity','cycling','Fahrrad- und Radtourcenter','Cycling centre',70],
        ['activity','whale_watching','Wal- und Delfinbeobachtung','Whale and dolphin watching',80],
        ['activity','cooking_class','Kochkurs / kulinarisches Erlebnis','Cooking class / culinary experience',90],
        ['activity','cultural_activity','Lokales Kultur- und Handwerkszentrum','Local culture and craft centre',100],
        ['restaurant','fine_dining','Fine Dining','Fine dining',10],
        ['restaurant','local_cuisine','Lokale sri-lankische Küche','Local Sri Lankan cuisine',20],
        ['restaurant','seafood','Fisch- und Seafood-Restaurant','Fish and seafood restaurant',30],
        ['restaurant','vegetarian_vegan','Vegetarisch / Vegan','Vegetarian / vegan',40],
        ['restaurant','hotel_restaurant','Hotelrestaurant','Hotel restaurant',50],
        ['restaurant','cafe_tea','Café / Tea Lounge','Café / tea lounge',60],
        ['restaurant','tourist_eating_place','Touristenfreundliches Restaurant','Tourist-friendly eating place',70],
        ['spice_garden','guided_spice_garden','Geführter Gewürzgarten','Guided spice garden',10],
        ['spice_garden','spice_garden_cooking','Gewürzgarten mit Kochvorführung','Spice garden with cooking demonstration',20],
        ['spice_garden','spice_garden_lunch','Gewürzgarten mit Mittagessen','Spice garden with lunch',30],
        ['spice_garden','ayurveda_spice_garden','Gewürz- und Ayurveda-Garten','Spice and Ayurveda garden',40],
        ['shop','spice_tea_shop','Gewürz- und Teeshop','Spice and tea shop',10],
        ['shop','handicraft_batik','Handwerk und Batik','Handicrafts and batik',20],
        ['shop','gem_jewellery','Edelsteine und Schmuck','Gems and jewellery',30],
        ['shop','ayurveda_products','Ayurveda-Produkte','Ayurveda products',40],
        ['shop','souvenir','Souvenirshop','Souvenir shop',50],
        ['shop','community_fair_trade','Community- / Fair-Trade-Shop','Community / fair-trade shop',60],
        ['service','transfer','Transfer','Transfer',10],
        ['service','wellness','Wellness / Spa','Wellness / spa',20],
        ['service','ayurveda_package','Ayurveda-Paket','Ayurveda package',30],
        ['service','guide','Reiseleitung / lokaler Guide','Tour guide / local guide',40],
        ['service','special_service','Weitere Zusatzleistung','Other additional service',50],
    ];
    $insert = $pdo->prepare('INSERT INTO catalog_classifications (item_type,code,name_de,name_en,sort_order,active) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name_de=VALUES(name_de),name_en=VALUES(name_en),sort_order=VALUES(sort_order)');
    foreach ($classifications as $classification) $insert->execute($classification);

    $defaults = [
        'accommodation'=>'tourist_hotel','sight'=>'heritage_site','activity'=>'cultural_activity',
        'restaurant'=>'local_cuisine','spice_garden'=>'guided_spice_garden','shop'=>'souvenir','service'=>'special_service',
    ];
    $assign = $pdo->prepare('UPDATE catalog_items c JOIN catalog_classifications cc ON cc.item_type=c.type AND cc.code=? SET c.classification_id=cc.id WHERE c.type=? AND c.classification_id IS NULL');
    foreach ($defaults as $itemType => $code) $assign->execute([$code,$itemType]);
};
