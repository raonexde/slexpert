<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM tour_request_items')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('room_count', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_request_items ADD COLUMN room_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity');
    }
    $pdo->exec('ALTER TABLE tour_requests MODIFY COLUMN duration SMALLINT UNSIGNED NOT NULL DEFAULT 12');

    $destinations = $pdo->query(
        'SELECT id, name_de, name_en, price_from FROM destinations ORDER BY sort_order, id'
    )->fetchAll();

    $countStatement = $pdo->prepare(
        'SELECT type, COUNT(*) AS item_count FROM catalog_items WHERE destination_id=? AND active=1 GROUP BY type'
    );
    $insertItem = $pdo->prepare(
        'INSERT INTO catalog_items
         (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,featured,active,sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,1,?)'
    );

    foreach ($destinations as $destination) {
        $destinationId = (int)$destination['id'];
        $countStatement->execute([$destinationId]);
        $counts = ['accommodation' => 0, 'sight' => 0, 'activity' => 0, 'shop' => 0];
        foreach ($countStatement->fetchAll() as $row) {
            $counts[$row['type']] = (int)$row['item_count'];
        }

        $nameDe = (string)$destination['name_de'];
        $nameEn = (string)$destination['name_en'];
        $guidePrice = max(80.0, (float)$destination['price_from']);
        $templates = [
            'accommodation' => [
                [
                    'Beispielhotel – ' . $nameDe . ' Boutique Stay',
                    'Sample hotel – ' . $nameEn . ' Boutique Stay',
                    'Editierbarer Testeintrag für ein Boutique-Hotel oder einen lokalen Unterkunftspartner.',
                    'Editable test entry for a boutique hotel or local accommodation partner.',
                    'Doppelzimmer · Preis pro Zimmer/Nacht',
                    'Double room · Rate per room/night',
                    round($guidePrice * 0.72), 1, 10,
                ],
                [
                    'Beispielhotel – ' . $nameDe . ' Comfort Stay',
                    'Sample hotel – ' . $nameEn . ' Comfort Stay',
                    'Editierbarer Testeintrag für ein komfortables Hotel; bitte vor Veröffentlichung ersetzen.',
                    'Editable test entry for a comfortable hotel; replace before publishing.',
                    'Doppelzimmer · Preis pro Zimmer/Nacht',
                    'Double room · Rate per room/night',
                    round($guidePrice * 0.54), 0, 20,
                ],
            ],
            'sight' => [
                [
                    'Kulturhöhepunkte von ' . $nameDe,
                    'Cultural highlights of ' . $nameEn,
                    'Editierbarer Testeintrag für die wichtigsten Kultur- und Geschichtsorte dieser Region.',
                    'Editable test entry for the region’s principal cultural and historic places.',
                    'Geführter Besuch · Beispielinhalt',
                    'Guided visit · Sample content',
                    38, 1, 30,
                ],
                [
                    'Natur & Aussicht in ' . $nameDe,
                    'Nature & viewpoints in ' . $nameEn,
                    'Editierbarer Testeintrag für Landschaften, Aussichtspunkte oder Natursehenswürdigkeiten.',
                    'Editable test entry for landscapes, viewpoints or natural sights.',
                    'Halbtägig · Beispielinhalt',
                    'Half day · Sample content',
                    29, 0, 40,
                ],
            ],
            'activity' => [
                [
                    'Private Entdeckungstour – ' . $nameDe,
                    'Private discovery tour – ' . $nameEn,
                    'Editierbarer Testeintrag für eine private Tour mit lokalem Guide.',
                    'Editable test entry for a private tour with a local guide.',
                    '3 Stunden · Beispielinhalt',
                    '3 hours · Sample content',
                    45, 1, 50,
                ],
                [
                    'Lokales Erlebnis – ' . $nameDe,
                    'Local experience – ' . $nameEn,
                    'Editierbarer Testeintrag für Kochen, Natur, Handwerk oder Begegnungen vor Ort.',
                    'Editable test entry for cooking, nature, crafts or local encounters.',
                    'Flexibel · Beispielinhalt',
                    'Flexible · Sample content',
                    32, 0, 60,
                ],
            ],
            'shop' => [
                [
                    'Lokaler Markt & Handwerk – ' . $nameDe,
                    'Local market & crafts – ' . $nameEn,
                    'Editierbarer Testeintrag für einen lokalen Markt, Produzenten oder fairen Einkaufsstopp.',
                    'Editable test entry for a local market, producer or fair shopping stop.',
                    'Besuch kostenlos · Beispielinhalt',
                    'Complimentary visit · Sample content',
                    0, 0, 70,
                ],
            ],
        ];
        $targets = ['accommodation' => 2, 'sight' => 2, 'activity' => 2, 'shop' => 1];

        foreach ($targets as $type => $target) {
            for ($index = $counts[$type]; $index < $target; $index++) {
                $template = $templates[$type][$index] ?? $templates[$type][array_key_last($templates[$type])];
                $insertItem->execute(array_merge([$destinationId, $type], $template));
            }
        }
    }

    $defaultMealPlans = [
        ['breakfast', 'Frühstück', 'Breakfast', 0.00, 10],
        ['room_only', 'Nur Übernachtung', 'Room only', 0.00, 20],
        ['half_board', 'Halbpension', 'Half board', 28.00, 30],
        ['full_board', 'Vollpension', 'Full board', 46.00, 40],
        ['all_inclusive', 'All-inclusive', 'All inclusive', 68.00, 50],
    ];
    $insertMealPlan = $pdo->prepare(
        "INSERT IGNORE INTO accommodation_meal_plans
         (catalog_item_id,code,name_de,name_en,supplement_per_person_night,active,sort_order)
         SELECT id,?,?,?,?,1,? FROM catalog_items WHERE type='accommodation'"
    );
    foreach ($defaultMealPlans as $mealPlan) {
        $insertMealPlan->execute($mealPlan);
    }
};
