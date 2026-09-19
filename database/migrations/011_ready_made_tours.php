<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(160) NOT NULL UNIQUE,
        category VARCHAR(40) NOT NULL DEFAULT 'discovery',
        title_de VARCHAR(190) NOT NULL,
        title_en VARCHAR(190) NOT NULL,
        eyebrow_de VARCHAR(190) NOT NULL DEFAULT '',
        eyebrow_en VARCHAR(190) NOT NULL DEFAULT '',
        intro_de TEXT NOT NULL,
        intro_en TEXT NOT NULL,
        description_de MEDIUMTEXT NOT NULL,
        description_en MEDIUMTEXT NOT NULL,
        duration_nights SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        min_travelers TINYINT UNSIGNED NOT NULL DEFAULT 1,
        max_travelers TINYINT UNSIGNED NOT NULL DEFAULT 20,
        price_from DECIMAL(10,2) NOT NULL DEFAULT 0,
        season_de VARCHAR(190) NOT NULL DEFAULT '',
        season_en VARCHAR(190) NOT NULL DEFAULT '',
        difficulty_de VARCHAR(120) NOT NULL DEFAULT '',
        difficulty_en VARCHAR(120) NOT NULL DEFAULT '',
        includes_de TEXT NOT NULL,
        includes_en TEXT NOT NULL,
        excludes_de TEXT NOT NULL,
        excludes_en TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        featured TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tour_templates_public (active, featured, sort_order),
        INDEX idx_tour_templates_category (category, active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_template_stops (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_template_id INT UNSIGNED NOT NULL,
        destination_id INT UNSIGNED NOT NULL,
        day_start SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        day_end SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        nights TINYINT UNSIGNED NOT NULL DEFAULT 1,
        title_de VARCHAR(190) NOT NULL DEFAULT '',
        title_en VARCHAR(190) NOT NULL DEFAULT '',
        description_de TEXT NOT NULL,
        description_en TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_template_stop_tour FOREIGN KEY (tour_template_id) REFERENCES tour_templates(id) ON DELETE CASCADE,
        CONSTRAINT fk_template_stop_destination FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE RESTRICT,
        INDEX idx_template_stops_route (tour_template_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_template_stop_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_template_stop_id INT UNSIGNED NOT NULL,
        catalog_item_id INT UNSIGNED NOT NULL,
        included TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_template_item_stop FOREIGN KEY (tour_template_stop_id) REFERENCES tour_template_stops(id) ON DELETE CASCADE,
        CONSTRAINT fk_template_item_catalog FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
        UNIQUE KEY uq_template_stop_item (tour_template_stop_id, catalog_item_id),
        INDEX idx_template_stop_items (tour_template_stop_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tour_template_prices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_template_id INT UNSIGNED NOT NULL,
        label_de VARCHAR(160) NOT NULL DEFAULT '',
        label_en VARCHAR(160) NOT NULL DEFAULT '',
        valid_from DATE NULL,
        valid_to DATE NULL,
        min_travelers TINYINT UNSIGNED NOT NULL DEFAULT 1,
        max_travelers TINYINT UNSIGNED NOT NULL DEFAULT 20,
        price_per_person DECIMAL(10,2) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        CONSTRAINT fk_template_price_tour FOREIGN KEY (tour_template_id) REFERENCES tour_templates(id) ON DELETE CASCADE,
        INDEX idx_template_prices (tour_template_id, active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = $pdo->query('SHOW COLUMNS FROM tour_requests')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('source_tour_template_id', $columns, true)) {
        $pdo->exec('ALTER TABLE tour_requests ADD source_tour_template_id INT UNSIGNED NULL AFTER request_type, ADD INDEX idx_request_source_template (source_tour_template_id)');
    }

    $destinations = [];
    foreach ($pdo->query('SELECT id,slug,name_de,name_en,intro_de,intro_en FROM destinations')->fetchAll() as $destination) {
        $destinations[$destination['slug']] = $destination;
    }

    $commonIncludesDe = "Private Rundreise nach Reiseverlauf\nUnterkünfte und Verpflegung wie ausgewählt\nPassendes Fahrzeug mit Fahrer\nIm Planer ausgewählte Erlebnisse\nPersönliche Reisebetreuung";
    $commonIncludesEn = "Private tour according to the itinerary\nAccommodation and meals as selected\nSuitable vehicle with driver\nExperiences selected in the planner\nPersonal travel assistance";
    $commonExcludesDe = "Internationale Flüge\nVisum und Reiseversicherung\nNicht ausdrücklich genannte Mahlzeiten und Leistungen\nPersönliche Ausgaben und Trinkgelder";
    $commonExcludesEn = "International flights\nVisa and travel insurance\nMeals and services not expressly listed\nPersonal expenses and gratuities";

    $templates = [
        ['sri-lanka-winter','winter','Sri Lanka im Winter','Sri Lanka in Winter','Wintersonne · Kultur · Südküste','Winter sun · culture · south coast','Die klassische Winterroute verbindet Kulturdreieck, Bergland, Safari und warme Strände.','A classic winter route combining the Cultural Triangle, hill country, safari and warm southern beaches.','November bis April','November to April','Angenehm','Easy',1890,1,10,['negombo'=>1,'sigiriya'=>3,'kandy'=>2,'ella'=>2,'yala'=>2,'weligama-mirissa'=>3,'colombo'=>1]],
        ['sri-lanka-sommer','summer','Sri Lanka im Sommer','Sri Lanka in Summer','Sommerroute · Ostküste · Kultur','Summer route · east coast · culture','Eine saisonal abgestimmte Route über alte Königsstädte zu den ruhigen Stränden der Ostküste.','A seasonally designed route through ancient capitals to the calm beaches of the east coast.','Mai bis Oktober','May to October','Angenehm','Easy',1790,1,20,['negombo'=>1,'anuradhapura'=>2,'sigiriya'=>3,'polonnaruwa'=>1,'pasikudah-kalkudah'=>3,'trincomalee-nilaveli'=>3,'colombo'=>1]],
        ['sport-abenteuer','sport','Sport & Abenteuer','Sport & Adventure','Aktiv · Natur · Adrenalin','Active · nature · adrenaline','Rafting, Wanderungen, Surfen und Küstenabenteuer für aktive Reisende.','Rafting, hiking, surfing and coastal adventures for active travellers.','Ganzjährig · Route saisonal anpassen','Year-round · route adjusted by season','Aktiv','Active',1690,1,20,['negombo'=>1,'kitulgala'=>2,'ella'=>3,'arugam-bay-pottuvil'=>4,'koggala-ahangama'=>2]],
        ['kulturreise-sri-lanka','culture','Kulturreise Sri Lanka','Sri Lanka Cultural Journey','UNESCO · Tempel · Königsstädte','UNESCO · temples · ancient capitals','Sri Lankas Geschichte, Religion, Architektur und lebendige Kultur auf einer konzentrierten Route.','Sri Lanka’s history, religion, architecture and living culture on a focused route.','Ganzjährig','Year-round','Angenehm','Easy',1590,1,20,['negombo'=>1,'anuradhapura'=>2,'sigiriya'=>3,'polonnaruwa'=>2,'kandy'=>3,'colombo'=>1]],
        ['sri-lanka-kennenlernen','discovery','Sri Lanka kennenlernen','Discover Sri Lanka','Die ideale erste Sri-Lanka-Reise','The ideal first Sri Lanka journey','Kultur, Teeberge, Tiere und Strand – ausgewogen geplant für die erste Reise auf die Insel.','Culture, tea country, wildlife and beach – a balanced first journey around the island.','Ganzjährig · Strand saisonal wählen','Year-round · beach chosen by season','Angenehm','Easy',1850,1,20,['negombo'=>1,'sigiriya'=>3,'kandy'=>2,'nuwara-eliya'=>2,'ella'=>2,'yala'=>2,'galle'=>2]],
        ['sri-lanka-intensiv','discovery','Sri Lanka intensiv','Sri Lanka In Depth','21 Nächte · Insel intensiv','21 nights · the island in depth','Eine ausführliche Reise mit Zeit für historische Städte, Bergland, Wildnis und mehrere Küstenregionen.','An in-depth journey with time for ancient cities, hill country, wilderness and several coastal regions.','Ganzjährig · saisonal optimiert','Year-round · seasonally optimised','Angenehm','Easy',2790,1,20,['negombo'=>1,'anuradhapura'=>2,'sigiriya'=>3,'polonnaruwa'=>2,'kandy'=>2,'nuwara-eliya'=>2,'ella'=>2,'yala'=>2,'weligama-mirissa'=>3,'galle'=>2]],
        ['backpacker-sri-lanka','backpacker','Backpacker Sri Lanka','Sri Lanka Backpacker Tour','Flexibel · budgetbewusst · authentisch','Flexible · budget-conscious · authentic','Eine flexible Route mit einfachen Unterkünften, lokalen Erlebnissen und genügend freier Zeit.','A flexible route with simple accommodation, local experiences and plenty of free time.','Ganzjährig','Year-round','Aktiv','Active',1090,1,12,['negombo'=>1,'anuradhapura'=>2,'sigiriya'=>2,'kandy'=>2,'ella'=>3,'weligama-mirissa'=>3,'galle'=>2,'colombo'=>1]],
        ['ayurveda-plus-sri-lanka','ayurveda','Ayurveda plus Sri Lanka','Ayurveda plus Sri Lanka','Rundreise & Regeneration','Touring & regeneration','Kulturelle und landschaftliche Höhepunkte, gefolgt von einem längeren Ayurveda-Aufenthalt.','Cultural and scenic highlights followed by an extended Ayurveda stay.','Ganzjährig','Year-round','Erholsam','Relaxed',2890,1,12,['negombo'=>1,'sigiriya'=>3,'kandy'=>2,'ella'=>2,'yala'=>2,'galle'=>2,'wadduwa-kalutara'=>9]],
        ['familienreise-sri-lanka','family','Familienreise Sri Lanka','Sri Lanka Family Journey','Familienfreundlich · kurze Etappen','Family-friendly · shorter drives','Tierbeobachtungen, Natur und Strand mit familienfreundlichen Etappen und Hotels.','Wildlife, nature and beach with family-friendly stages and hotels.','Ganzjährig · Strand saisonal wählen','Year-round · beach chosen by season','Familienfreundlich','Family-friendly',1990,2,20,['negombo'=>2,'sigiriya'=>3,'kandy'=>2,'ella'=>2,'udawalawe'=>2,'bentota-beruwala'=>3]],
        ['luxusreise-sri-lanka','luxury','Luxusreise Sri Lanka','Sri Lanka Luxury Journey','Boutique · privat · besonders','Boutique · private · exceptional','Ausgewählte Boutique-Hotels, private Erlebnisse und großzügig geplante Etappen.','Selected boutique hotels, private experiences and generously paced stages.','Ganzjährig','Year-round','Komfortabel','Comfortable',3290,1,12,['colombo'=>1,'sigiriya'=>3,'kandy'=>2,'hatton-adams-peak'=>2,'yala'=>2,'galle'=>3]],
    ];

    $insertTour = $pdo->prepare('INSERT INTO tour_templates (slug,category,title_de,title_en,eyebrow_de,eyebrow_en,intro_de,intro_en,description_de,description_en,duration_nights,min_travelers,max_travelers,price_from,season_de,season_en,difficulty_de,difficulty_en,includes_de,includes_en,excludes_de,excludes_en,featured,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insertStop = $pdo->prepare('INSERT INTO tour_template_stops (tour_template_id,destination_id,day_start,day_end,nights,title_de,title_en,description_de,description_en,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $findDefaultItems = $pdo->prepare("SELECT id,type FROM catalog_items WHERE destination_id=? AND active=1 ORDER BY FIELD(type,'accommodation','sight','activity','shop','service'),featured DESC,sort_order,id");
    $insertStopItem = $pdo->prepare('INSERT IGNORE INTO tour_template_stop_items (tour_template_stop_id,catalog_item_id,included,sort_order) VALUES (?,?,1,?)');
    $insertPrice = $pdo->prepare('INSERT INTO tour_template_prices (tour_template_id,label_de,label_en,min_travelers,max_travelers,price_per_person,active,sort_order) VALUES (?,?,?,?,?,?,1,10)');
    $exists = $pdo->prepare('SELECT id FROM tour_templates WHERE slug=?');

    foreach ($templates as $sort => $template) {
        [$slug,$category,$titleDe,$titleEn,$eyebrowDe,$eyebrowEn,$introDe,$introEn,$seasonDe,$seasonEn,$difficultyDe,$difficultyEn,$price,$minTravelers,$maxTravelers,$route] = $template;
        $exists->execute([$slug]);
        if ($exists->fetchColumn()) continue;
        $nights = array_sum($route);
        $descriptionDe = $introDe . "\n\nAlle Stationen, Nächte, Hotels, Verpflegungen und Erlebnisse können vor der Anfrage individuell verändert werden.";
        $descriptionEn = $introEn . "\n\nEvery stop, number of nights, hotel, meal plan and experience can be changed before sending the enquiry.";
        $insertTour->execute([$slug,$category,$titleDe,$titleEn,$eyebrowDe,$eyebrowEn,$introDe,$introEn,$descriptionDe,$descriptionEn,$nights,$minTravelers,$maxTravelers,$price,$seasonDe,$seasonEn,$difficultyDe,$difficultyEn,$commonIncludesDe,$commonIncludesEn,$commonExcludesDe,$commonExcludesEn,$sort < 6 ? 1 : 0,1,($sort+1)*10]);
        $tourId = (int)$pdo->lastInsertId();
        $day = 1;
        $position = 0;
        foreach ($route as $destinationSlug => $stopNights) {
            if (!isset($destinations[$destinationSlug])) continue;
            $destination = $destinations[$destinationSlug];
            $dayEnd = $day + max(1, (int)$stopNights) - 1;
            $insertStop->execute([$tourId,$destination['id'],$day,$dayEnd,$stopNights,$destination['name_de'],$destination['name_en'],$destination['intro_de'],$destination['intro_en'],$position*10]);
            $stopId = (int)$pdo->lastInsertId();
            $findDefaultItems->execute([$destination['id']]);
            $chosenTypes = [];
            $itemPosition = 0;
            foreach ($findDefaultItems->fetchAll() as $catalogItem) {
                $type = (string)$catalogItem['type'];
                if ($type === 'accommodation' && isset($chosenTypes['accommodation'])) continue;
                if ($type !== 'accommodation' && isset($chosenTypes[$type])) continue;
                $insertStopItem->execute([$stopId,$catalogItem['id'],$itemPosition*10]);
                $chosenTypes[$type] = true;
                $itemPosition++;
                if ($itemPosition >= 3) break;
            }
            $day = $dayEnd + 1;
            $position++;
        }
        $insertPrice->execute([$tourId,'Richtpreis ab','Guide price from',$minTravelers,$maxTravelers,$price]);
    }
};
