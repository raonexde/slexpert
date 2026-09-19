<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $destinationColumns = $pdo->query('SHOW COLUMNS FROM destinations')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('country_code', $destinationColumns, true)) {
        $pdo->exec("ALTER TABLE destinations ADD COLUMN country_code CHAR(2) NOT NULL DEFAULT 'LK' AFTER id, ADD INDEX idx_destinations_country (country_code,active,sort_order)");
    }
    $pdo->exec("UPDATE destinations SET country_code='LK' WHERE country_code='' OR country_code IS NULL");

    $catalogColumns = $pdo->query('SHOW COLUMNS FROM catalog_items')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('maldives_available', $catalogColumns, true)) {
        $pdo->exec('ALTER TABLE catalog_items ADD COLUMN maldives_available TINYINT(1) NOT NULL DEFAULT 0 AFTER ayurveda_available, ADD INDEX idx_catalog_maldives (maldives_available,active,type)');
    }
    $pdo->exec("ALTER TABLE tour_requests MODIFY COLUMN request_type ENUM('tour','beach','ayurveda','maldives') NOT NULL DEFAULT 'tour'");

    $atolls = [
        ['maldives-north-male','NMV','Nord-Malé-Atoll','North Malé Atoll','Malediven','Maldives','Kurze Transfers, bekannte Hausriffe und eine große Auswahl an Inselresorts nahe Velana International Airport.','Short transfers, celebrated house reefs and a broad choice of island resorts near Velana International Airport.',7,420,'ocean',4.4167000,73.5000000,510],
        ['maldives-south-male','SMV','Süd-Malé-Atoll','South Malé Atoll','Malediven','Maldives','Lagunen, Surfspots und komfortable Schnellbootverbindungen südlich der Hauptstadt Malé.','Lagoons, surf breaks and convenient speedboat connections south of Malé.',7,390,'ocean',3.9500000,73.4300000,520],
        ['maldives-north-ari','NAM','Nord-Ari-Atoll','North Ari Atoll','Malediven','Maldives','Kleine Resortinseln, hervorragende Tauchplätze und ruhige Lagunen im westlichen Inselbogen.','Small resort islands, excellent dive sites and peaceful lagoons in the western island chain.',7,520,'ocean',4.1000000,72.8300000,530],
        ['maldives-south-ari','SAM','Süd-Ari-Atoll','South Ari Atoll','Malediven','Maldives','Walhai-Gebiet, weite Lagunen und hochwertige Resorts für Tauchen, Schnorcheln und Erholung.','Whale-shark waters, broad lagoons and refined resorts for diving, snorkelling and relaxation.',7,560,'ocean',3.5500000,72.8500000,540],
        ['maldives-baa','BAA','Baa-Atoll','Baa Atoll','Malediven','Maldives','UNESCO-Biosphärenregion mit reichen Riffen, Mantasaison und exklusiven Naturresorts.','A UNESCO biosphere region with rich reefs, manta season and exclusive nature resorts.',7,610,'leaf',5.0900000,72.9500000,550],
        ['maldives-raa','RAA','Raa-Atoll','Raa Atoll','Malediven','Maldives','Große Lagunen, elegante Villen und abgelegene Inselwelten im nördlichen Archipel.','Expansive lagoons, elegant villas and remote island settings in the northern archipelago.',7,570,'ocean',5.6000000,72.9500000,560],
        ['maldives-noonu','NOO','Noonu-Atoll','Noonu Atoll','Malediven','Maldives','Weitläufige Resortinseln, Familienangebote und luxuriöse Wasser-Villen im Norden.','Spacious resort islands, family experiences and luxury overwater villas in the north.',7,650,'ocean',5.8200000,73.3000000,570],
        ['maldives-lhaviyani','LHV','Lhaviyani-Atoll','Lhaviyani Atoll','Malediven','Maldives','Lebendige Riffe, Sandbänke und bekannte Resorts für Taucher, Paare und Familien.','Vibrant reefs, sandbanks and celebrated resorts for divers, couples and families.',7,540,'ocean',5.3600000,73.5000000,580],
        ['maldives-dhaalu','DHA','Dhaalu-Atoll','Dhaalu Atoll','Malediven','Maldives','Moderne Lifestyle-Resorts und ruhige Lagunen mit Inlandsflug- oder Wasserflugzeugtransfer.','Contemporary lifestyle resorts and calm lagoons reached by domestic flight or seaplane.',7,520,'sand',2.9000000,72.9000000,590],
    ];
    $destinationExists = $pdo->prepare('SELECT id FROM destinations WHERE slug=?');
    $destinationInsert = $pdo->prepare("INSERT INTO destinations (country_code,slug,code,name_de,name_en,region_de,region_en,intro_de,intro_en,default_nights,price_from,accent,latitude,longitude,google_place_id,sort_order,active) VALUES ('MV',?,?,?,?,?,?,?,?,?,?,?,?,?,'',?,1)");
    foreach ($atolls as $atoll) {
        [$slug,$code,$nameDe,$nameEn,$regionDe,$regionEn,$introDe,$introEn,$nights,$price,$accent,$lat,$lng,$sort] = $atoll;
        $destinationExists->execute([$slug]);
        if (!$destinationExists->fetchColumn()) {
            $destinationInsert->execute([$slug,$code,$nameDe,$nameEn,$regionDe,$regionEn,$introDe,$introEn,$nights,$price,$accent,$lat,$lng,$sort]);
        }
    }

    $destinations = [];
    foreach ($pdo->query("SELECT id,slug,name_de,name_en FROM destinations WHERE country_code='MV'")->fetchAll() as $destination) {
        $destinations[$destination['slug']] = $destination;
    }

    // Editable starter catalogue. Rates are deliberately guide values per double room/night.
    $resorts = [
        ['maldives-north-male','Kurumba Maldives','Kurumba Maldives','Klassisches Inselresort nahe Malé mit tropischem Garten, mehreren Restaurants und guten Familienangeboten.','A classic island resort near Malé with tropical gardens, varied dining and strong family facilities.','Schnellboot · Garten- & Strandzimmer','Speedboat · Garden & beach rooms',360,1],
        ['maldives-north-male','Bandos Maldives','Bandos Maldives','Große grüne Insel mit Hausriff, Tauchbasis, Spa und vielseitigen Zimmerkategorien.','A large green island with a house reef, dive centre, spa and a broad range of room categories.','Schnellboot · Hausriff · Familien','Speedboat · House reef · Families',340,1],
        ['maldives-north-male','Baros Maldives','Baros Maldives','Elegantes Boutique-Resort für Paare mit markantem Hausriff und privaten Villen.','An elegant couples-focused boutique resort with a renowned house reef and private villas.','Schnellboot · Boutique · Paare','Speedboat · Boutique · Couples',790,1],
        ['maldives-north-male','Villa Nautica Paradise Island','Villa Nautica Paradise Island','Lebendiges Inselresort mit langem Strand, Wasservillen und zahlreichen Sportangeboten.','A lively island resort with a long beach, overwater villas and plentiful sports facilities.','Schnellboot · Wasservillen · Sport','Speedboat · Water villas · Sports',410,0],
        ['maldives-north-male','Meeru Maldives Resort Island','Meeru Maldives Resort Island','Weitläufige Insel mit Strand- und Wasservillen, Golfbereich und vielen Freizeitmöglichkeiten.','A spacious island with beach and water villas, golf facilities and many leisure options.','Schnellboot · Große Insel · Aktiv','Speedboat · Large island · Active',430,0],
        ['maldives-south-male','Velassaru Maldives','Velassaru Maldives','Modernes Lifestyle-Resort mit heller Lagune, stilvollen Villen und kurzer Schnellbootfahrt.','A modern lifestyle resort with a bright lagoon, stylish villas and a short speedboat ride.','Schnellboot · Design · Lagune','Speedboat · Design · Lagoon',590,1],
        ['maldives-south-male','Hard Rock Hotel Maldives','Hard Rock Hotel Maldives','Familienfreundliches Lifestyle-Hotel mit Marina, Restaurants und Unterhaltungsangeboten.','A family-friendly lifestyle hotel with a marina, restaurants and entertainment experiences.','Schnellboot · Marina · Familien','Speedboat · Marina · Families',510,0],
        ['maldives-south-male','SAii Lagoon Maldives','SAii Lagoon Maldives','Entspanntes Resort im CROSSROADS-Komplex mit Strand, Marina und vielseitiger Gastronomie.','A relaxed resort in the CROSSROADS complex with beach, marina and varied dining.','Schnellboot · Marina · Lifestyle','Speedboat · Marina · Lifestyle',470,0],
        ['maldives-south-male','Adaaran Club Rannalhi','Adaaran Club Rannalhi','Kompakte Insel mit Strand- und Wasservillen sowie unkompliziertem All-inclusive-Angebot.','A compact island with beach and water villas and an uncomplicated all-inclusive concept.','Schnellboot · All-inclusive möglich','Speedboat · All inclusive available',330,0],
        ['maldives-north-ari','Kuramathi Maldives','Kuramathi Maldives','Große Resortinsel mit Sandbank, vielen Restaurants und Unterkünften für unterschiedliche Budgets.','A large resort island with a sandbank, many restaurants and accommodation across several price levels.','Wasserflugzeug · Sandbank · Familien','Seaplane · Sandbank · Families',480,1],
        ['maldives-north-ari','Kandolhu Maldives','Kandolhu Maldives','Kleine exklusive Insel mit ausgezeichnetem Hausriff und persönlicher Atmosphäre.','A small exclusive island with an excellent house reef and a highly personal atmosphere.','Wasserflugzeug · Hausriff · Boutique','Seaplane · House reef · Boutique',780,1],
        ['maldives-south-ari','Conrad Maldives Rangali Island','Conrad Maldives Rangali Island','Luxusresort auf zwei Inseln mit außergewöhnlicher Gastronomie und großzügigen Villen.','A luxury resort across two islands with exceptional dining and spacious villas.','Wasserflugzeug · Luxus · Gastronomie','Seaplane · Luxury · Dining',1050,1],
        ['maldives-south-ari','Vilamendhoo Island Resort & Spa','Vilamendhoo Island Resort & Spa','Beliebtes Taucherresort mit langem Hausriff, Strandvillen und Erwachsenenbereich.','A popular divers’ resort with an extensive house reef, beach villas and an adults-only area.','Wasserflugzeug · Tauchen · Hausriff','Seaplane · Diving · House reef',460,0],
        ['maldives-south-ari','LUX* South Ari Atoll','LUX* South Ari Atoll','Lebendiges Luxusresort mit langem Strand, großem Freizeitangebot und Walhai-Ausflügen.','A vibrant luxury resort with a long beach, extensive leisure facilities and whale-shark excursions.','Wasserflugzeug · Walhaie · Luxus','Seaplane · Whale sharks · Luxury',830,1],
        ['maldives-baa','Soneva Fushi','Soneva Fushi','Barfuß-Luxus in dichter Natur mit großzügigen Villen, nachhaltigem Konzept und persönlichem Service.','Barefoot luxury in dense nature with spacious villas, a sustainability focus and personal service.','Wasserflugzeug · Barfuß-Luxus','Seaplane · Barefoot luxury',1450,1],
        ['maldives-baa','Dusit Thani Maldives','Dusit Thani Maldives','Luxusresort mit großer Poollandschaft, Hausriff und Villen im UNESCO-Biosphärengebiet.','A luxury resort with a large pool, house reef and villas in the UNESCO biosphere region.','Inlandsflug/Wasserflugzeug · Hausriff','Domestic flight/seaplane · House reef',760,0],
        ['maldives-baa','Reethi Beach Resort','Reethi Beach Resort','Entspanntes Inselresort mit viel Natur, Wassersport und gutem Preis-Leistungs-Verhältnis.','A relaxed nature-rich island resort with water sports and attractive value.','Inlandsflug + Boot · Natürlich','Domestic flight + boat · Natural',350,0],
        ['maldives-raa','Heritance Aarah','Heritance Aarah','Premium-All-inclusive-Resort mit Strand- und Wasservillen, Spa und vielfältiger Gastronomie.','A premium all-inclusive resort with beach and water villas, spa and varied dining.','Wasserflugzeug · Premium All-inclusive','Seaplane · Premium all inclusive',880,1],
        ['maldives-raa','Furaveri Maldives','Furaveri Maldives','Große natürliche Insel mit breitem Strand, Spa, Familienbereich und mehreren Restaurants.','A large natural island with a broad beach, spa, family facilities and several restaurants.','Wasserflugzeug · Familien · Spa','Seaplane · Families · Spa',440,0],
        ['maldives-noonu','Sun Siyam Iru Fushi','Sun Siyam Iru Fushi','Großzügiges Resort mit vielen Restaurants, Familienangeboten und Strand- sowie Wasservillen.','A spacious resort with many restaurants, family facilities and beach and water villas.','Wasserflugzeug · Familien · Auswahl','Seaplane · Families · Choice',590,0],
        ['maldives-noonu','Siyam World Maldives','Siyam World Maldives','Sehr großes All-inclusive-Resort mit Wasserpark, Sport und breiter Auswahl für Familien.','A very large all-inclusive resort with water park, sports and extensive family options.','Inlandsflug + Boot · All-inclusive','Domestic flight + boat · All inclusive',620,1],
        ['maldives-lhaviyani','Kuredu Island Resort & Spa','Kuredu Island Resort & Spa','Große Insel mit Tauchen, Golf, Erwachsenenbereichen und vielen Zimmerkategorien.','A large island with diving, golf, adults-only areas and many room categories.','Wasserflugzeug · Tauchen · Golf','Seaplane · Diving · Golf',420,0],
        ['maldives-lhaviyani','Hurawalhi Island Resort','Hurawalhi Island Resort','Adults-only-Luxusresort mit Wasservillen und spektakulärem Unterwasserrestaurant.','An adults-only luxury resort with overwater villas and a spectacular underwater restaurant.','Wasserflugzeug · Adults-only · Luxus','Seaplane · Adults only · Luxury',930,1],
        ['maldives-dhaalu','Kandima Maldives','Kandima Maldives','Modernes Lifestyle-Resort mit langem Strand, Studios, Villen und großem Aktivangebot.','A contemporary lifestyle resort with a long beach, studios, villas and an extensive activity programme.','Inlandsflug + Boot · Lifestyle','Domestic flight + boat · Lifestyle',450,1],
        ['maldives-dhaalu','Sun Siyam Vilu Reef','Sun Siyam Vilu Reef','Kompakte tropische Insel mit Hausriff, Wasservillen und All-inclusive-Optionen.','A compact tropical island with a house reef, overwater villas and all-inclusive options.','Wasserflugzeug · Hausriff · Erholung','Seaplane · House reef · Relaxation',520,0],
    ];
    $resortExists = $pdo->prepare("SELECT id FROM catalog_items WHERE destination_id=? AND type='accommodation' AND name_en=?");
    $resortInsert = $pdo->prepare("INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,price_basis,beach_available,ayurveda_available,maldives_available,standard_room_guests,max_guests_with_extra_bed,extra_bed_percent,child_percent,child_max_age,ayurveda_night_options,featured,active,sort_order) VALUES (?,'accommodation',?,?,?,?,?,?,?,'per_person_night',0,0,1,2,3,30,50,12,'7,14,21,28',?,1,?)");
    $mealInsert = $pdo->prepare('INSERT IGNORE INTO accommodation_meal_plans (catalog_item_id,code,name_de,name_en,supplement_per_person_night,active,sort_order) VALUES (?,?,?,?,?,1,?)');
    $resortPosition = 0;
    foreach ($resorts as $resort) {
        [$destinationSlug,$nameDe,$nameEn,$descriptionDe,$descriptionEn,$metaDe,$metaEn,$price,$featured] = $resort;
        if (!isset($destinations[$destinationSlug])) continue;
        $destinationId = (int)$destinations[$destinationSlug]['id'];
        $resortExists->execute([$destinationId,$nameEn]);
        $resortId = (int)($resortExists->fetchColumn() ?: 0);
        if (!$resortId) {
            $resortInsert->execute([$destinationId,$nameDe,$nameEn,$descriptionDe,$descriptionEn,$metaDe,$metaEn,$price,$featured,($resortPosition+1)*10]);
            $resortId = (int)$pdo->lastInsertId();
        }
        $plans = [
            ['breakfast','Frühstück','Breakfast',0,10],
            ['room_only','Nur Übernachtung','Room only',0,20],
            ['half_board','Halbpension','Half board',65,30],
            ['full_board','Vollpension','Full board',115,40],
            ['all_inclusive','All-inclusive','All inclusive',185,50],
        ];
        foreach ($plans as $plan) $mealInsert->execute([$resortId,$plan[0],$plan[1],$plan[2],$plan[3],$plan[4]]);
        $resortPosition++;
    }

    $serviceExists = $pdo->prepare('SELECT id FROM catalog_items WHERE destination_id=? AND type=? AND name_en=?');
    $serviceInsert = $pdo->prepare("INSERT INTO catalog_items (destination_id,type,name_de,name_en,description_de,description_en,meta_de,meta_en,price_per_person,price_basis,beach_available,ayurveda_available,maldives_available,featured,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,0,0,1,?,1,?)");
    $serviceSets = [
        'maldives-north-male'=>['Schnellboot-Transfer Flughafen–Resort','Airport–resort speedboat transfer',180],
        'maldives-south-male'=>['Schnellboot-Transfer Flughafen–Resort','Airport–resort speedboat transfer',240],
        'maldives-north-ari'=>['Wasserflugzeug-Transfer hin & zurück','Return seaplane transfer',580],
        'maldives-south-ari'=>['Wasserflugzeug-Transfer hin & zurück','Return seaplane transfer',650],
        'maldives-baa'=>['Inlandsflug/Wasserflugzeug-Transfer hin & zurück','Return domestic flight/seaplane transfer',620],
        'maldives-raa'=>['Wasserflugzeug-Transfer hin & zurück','Return seaplane transfer',650],
        'maldives-noonu'=>['Inlandsflug mit Bootstransfer hin & zurück','Return domestic flight and speedboat transfer',590],
        'maldives-lhaviyani'=>['Wasserflugzeug-Transfer hin & zurück','Return seaplane transfer',620],
        'maldives-dhaalu'=>['Inlandsflug mit Bootstransfer hin & zurück','Return domestic flight and speedboat transfer',560],
    ];
    foreach ($serviceSets as $destinationSlug=>$transfer) {
        if (!isset($destinations[$destinationSlug])) continue;
        $destinationId = (int)$destinations[$destinationSlug]['id'];
        $services = [
            ['service',$transfer[0],$transfer[1],'Editierbarer Richtpreis für den gemeinsamen Transfer ab/bis Velana International Airport.','Editable guide price for the shared return transfer from/to Velana International Airport.','Pro Buchung · genaue Transferart prüfen','Per booking · confirm exact transfer type',(float)$transfer[2],'per_booking',1,10],
            ['activity','Geführtes Hausriff-Schnorcheln','Guided house-reef snorkelling','Geführtes Schnorchelerlebnis mit Ausrüstung am Hausriff des Resorts.','A guided snorkelling experience with equipment at the resort house reef.','Pro Person','Per person',65,'per_person',0,20],
            ['sight','Delfin- & Sonnenuntergangsfahrt','Dolphin and sunset cruise','Gemeinsame Bootsfahrt am späten Nachmittag mit Suche nach Delfinen.','A shared late-afternoon cruise in search of dolphins.','Pro Person','Per person',85,'per_person',0,30],
        ];
        foreach ($services as $service) {
            $serviceExists->execute([$destinationId,$service[0],$service[2]]);
            if ($serviceExists->fetchColumn()) continue;
            $serviceInsert->execute([$destinationId,$service[0],$service[1],$service[2],$service[3],$service[4],$service[5],$service[6],$service[7],$service[8],$service[9],$service[10]]);
        }
    }
};
