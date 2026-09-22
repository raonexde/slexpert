<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_hiking_details (
        tour_template_id INT UNSIGNED PRIMARY KEY,
        distance_km DECIMAL(7,2) NOT NULL DEFAULT 0,
        elevation_gain_m SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        elevation_loss_m SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        min_elevation_m SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        max_elevation_m SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        moving_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        total_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        route_type_de VARCHAR(120) NOT NULL DEFAULT '',
        route_type_en VARCHAR(120) NOT NULL DEFAULT '',
        start_location_de VARCHAR(190) NOT NULL DEFAULT '',
        start_location_en VARCHAR(190) NOT NULL DEFAULT '',
        end_location_de VARCHAR(190) NOT NULL DEFAULT '',
        end_location_en VARCHAR(190) NOT NULL DEFAULT '',
        guide_required TINYINT(1) NOT NULL DEFAULT 1,
        source_url VARCHAR(500) NOT NULL DEFAULT '',
        notes_de TEXT NOT NULL,
        notes_en TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_hiking_details_tour FOREIGN KEY (tour_template_id) REFERENCES tour_templates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $slug = 'knuckles-hunnas-sembuwatta-one-tree-hill';
    $findTour = $pdo->prepare('SELECT id FROM tour_templates WHERE slug=?');
    $findTour->execute([$slug]);
    $tourId = (int)($findTour->fetchColumn() ?: 0);

    if ($tourId === 0) {
        $insertTour = $pdo->prepare('INSERT INTO tour_templates
            (slug,category,title_de,title_en,eyebrow_de,eyebrow_en,intro_de,intro_en,description_de,description_en,duration_nights,min_travelers,max_travelers,price_from,season_de,season_en,difficulty_de,difficulty_en,includes_de,includes_en,excludes_de,excludes_en,featured,active,sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insertTour->execute([
            $slug,
            'sport',
            'Knuckles Hiking: Hunnas Falls, Sembuwatta Lake & One Tree Hill',
            'Knuckles Hiking: Hunnas Falls, Sembuwatta Lake & One Tree Hill',
            '26,5 km · Wasserfall · See · Gipfel',
            '26.5 km · waterfall · lake · summit',
            'Eine anspruchsvolle Streckenwanderung durch die Knuckles-Region mit Hunnas Falls, Sembuwatta Lake und One Tree Hill.',
            'A demanding one-way hike through the Knuckles region featuring Hunnas Falls, Sembuwatta Lake and One Tree Hill.',
            "Diese aktive Kurzreise kombiniert zwei Übernachtungen in der Knuckles-Region mit einer langen geführten Wanderung. Die aufgezeichnete Route verläuft über Straßen und Wanderwege und verbindet Wasserfall, Bergsee und Gipfel. Transferpunkte, Zugänglichkeit und Wegzustand werden vor jeder Buchung aktuell geprüft. Gute Kondition, feste Wanderschuhe und Regenschutz sind erforderlich.",
            "This active short break combines two nights in the Knuckles region with a long guided hike. The recorded route uses roads and trails to connect a waterfall, mountain lake and summit. Transfer points, access and trail conditions are checked before every booking. Good fitness, proper hiking shoes and rain protection are required.",
            2,
            2,
            8,
            690.00,
            'Nur bei geeigneter Wetter- und Weglage',
            'Only when weather and trail conditions are suitable',
            'Lang · technisch moderat · sehr gute Kondition',
            'Long · technically moderate · very good fitness',
            "2 Übernachtungen in der Knuckles-Region\nLokaler Wanderführer\nPrivater Transfer zum Start und Abholung am Ziel\nGeführte Tageswanderung\nLunchpaket und Trinkwasser\nPersönliche Reisebetreuung",
            "2 nights in the Knuckles region\nLocal hiking guide\nPrivate transfer to the start and collection at the finish\nGuided full-day hike\nPacked lunch and drinking water\nPersonal travel assistance",
            "Internationale Flüge\nVisum und Reiseversicherung\nPersönliche Wanderausrüstung\nNicht ausdrücklich genannte Mahlzeiten\nTrinkgelder und persönliche Ausgaben",
            "International flights\nVisa and travel insurance\nPersonal hiking equipment\nMeals not expressly listed\nGratuities and personal expenses",
            0,
            1,
            25,
        ]);
        $tourId = (int)$pdo->lastInsertId();
    }

    $destinationStmt = $pdo->prepare("SELECT id,name_de,name_en,intro_de,intro_en FROM destinations WHERE slug='matale-knuckles' LIMIT 1");
    $destinationStmt->execute();
    $destination = $destinationStmt->fetch();

    if ($tourId > 0 && $destination) {
        $activityNameDe = 'Knuckles: Hunnas Falls, Sembuwatta Lake & One Tree Hill';
        $activityNameEn = 'Knuckles: Hunnas Falls, Sembuwatta Lake & One Tree Hill';
        $findActivity = $pdo->prepare("SELECT id FROM catalog_items WHERE destination_id=? AND type='activity' AND name_en=? LIMIT 1");
        $findActivity->execute([(int)$destination['id'],$activityNameEn]);
        $activityId = (int)($findActivity->fetchColumn() ?: 0);
        if ($activityId === 0) {
            $insertActivity = $pdo->prepare("INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,facilities_de,facilities_en,supplier_contact,internal_notes,price_per_person,price_basis,featured,active,sort_order) VALUES (?,'activity',?,?,?,?,?,?,?,?,?,?,?,'per_person',1,1,15)");
            $insertActivity->execute([
                (int)$destination['id'],
                $activityNameDe,
                $activityNameEn,
                'Geführte, lange Streckenwanderung: 26,53 km, 704 m Aufstieg, etwa 6 Std. 27 Min. Gesamtzeit und technisch moderat. Durchführung nur nach aktueller Prüfung von Wetter, Weg und Transfers.',
                'Guided long one-way hike: 26.53 km, 704 m ascent, about 6 hr 27 min total time and technically moderate. Operated only after a current check of weather, trail and transfers.',
                '26,53 km · 704 m Aufstieg · technisch moderat',
                '26.53 km · 704 m ascent · technically moderate',
                '',
                '',
                '',
                'Wikiloc-Referenz 156502660; Wegzustand und Zugänglichkeit vor Verkauf prüfen.',
                145.00,
            ]);
            $activityId = (int)$pdo->lastInsertId();
        }

        $stopStmt = $pdo->prepare('SELECT id FROM tour_template_stops WHERE tour_template_id=? ORDER BY sort_order,id LIMIT 1');
        $stopStmt->execute([$tourId]);
        $stopId = (int)($stopStmt->fetchColumn() ?: 0);
        if ($stopId === 0) {
            $insertStop = $pdo->prepare('INSERT INTO tour_template_stops (tour_template_id,destination_id,day_start,day_end,nights,title_de,title_en,description_de,description_en,sort_order) VALUES (?,?,?,?,?,?,?,?,?,10)');
            $insertStop->execute([
                $tourId,
                (int)$destination['id'],
                1,
                2,
                2,
                'Knuckles Hiking Base',
                'Knuckles Hiking Base',
                'Anreise und Einweisung am ersten Tag. Am zweiten Tag startet früh die geführte Streckenwanderung zu Hunnas Falls, Sembuwatta Lake und One Tree Hill. Abholung am vereinbarten Zielpunkt.',
                'Arrival and briefing on day one. The guided one-way hike to Hunnas Falls, Sembuwatta Lake and One Tree Hill starts early on day two, with collection at the agreed finish point.',
            ]);
            $stopId = (int)$pdo->lastInsertId();
        }

        if ($activityId > 0 && $stopId > 0) {
            $pdo->prepare('INSERT IGNORE INTO tour_template_stop_items (tour_template_stop_id,catalog_item_id,included,sort_order) VALUES (?,?,1,10)')->execute([$stopId,$activityId]);
        }

        $findHotel = $pdo->prepare("SELECT id FROM catalog_items WHERE destination_id=? AND type='accommodation' AND active=1 ORDER BY featured DESC,sort_order,id LIMIT 1");
        $findHotel->execute([(int)$destination['id']]);
        $hotelId = (int)($findHotel->fetchColumn() ?: 0);
        if ($hotelId > 0 && $stopId > 0) {
            $pdo->prepare('INSERT IGNORE INTO tour_template_stop_items (tour_template_stop_id,catalog_item_id,included,sort_order) VALUES (?,?,1,0)')->execute([$stopId,$hotelId]);
        }
    }

    if ($tourId > 0) {
        $details = $pdo->prepare('INSERT INTO tour_hiking_details
            (tour_template_id,distance_km,elevation_gain_m,elevation_loss_m,min_elevation_m,max_elevation_m,moving_minutes,total_minutes,route_type_de,route_type_en,start_location_de,start_location_en,end_location_de,end_location_en,guide_required,source_url,notes_de,notes_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE tour_template_id=VALUES(tour_template_id)');
        $details->execute([
            $tourId,
            26.53,
            704,
            520,
            683,
            1086,
            182,
            387,
            'Strecke · kein Rundweg',
            'One way · not a loop',
            'Wehigala East / vereinbarter Startpunkt',
            'Wehigala East / agreed starting point',
            'One Tree Hill / vereinbarter Abholpunkt',
            'One Tree Hill / agreed collection point',
            1,
            'https://de.wikiloc.com/routen-wandern/sri-lanka-knuckles-hunnas-falls-sembuwatta-lake-one-tree-hill-156502660',
            'Die Referenzaufzeichnung kombiniert Straßen- und Wegabschnitte sowie Transfers zwischen den besuchten Bereichen. Sie ist eine Planungsgrundlage, keine Garantie für die aktuelle Begehbarkeit. Route, Eintritt, Genehmigungen und Abholung werden vor Abreise bestätigt.',
            'The reference recording combines road and trail sections as well as transfers between the visited areas. It is a planning reference, not a guarantee of current access. Route, entry, permits and collection are confirmed before departure.',
        ]);

        $priceExists = $pdo->prepare('SELECT id FROM tour_template_prices WHERE tour_template_id=? LIMIT 1');
        $priceExists->execute([$tourId]);
        if (!$priceExists->fetchColumn()) {
            $pdo->prepare('INSERT INTO tour_template_prices (tour_template_id,label_de,label_en,min_travelers,max_travelers,price_per_person,active,sort_order) VALUES (?,?,?,?,?,?,1,10)')
                ->execute([$tourId,'Geführtes Paket ab','Guided package from',2,8,690.00]);
        }
    }
};
