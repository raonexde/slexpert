<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $exists = $pdo->prepare("SELECT id FROM destinations WHERE country_code='LK' AND (slug='bandaranaike-international-airport' OR name_en LIKE 'Bandaranaike%Airport%' OR name_de LIKE 'Bandaranaike%Flughafen%') LIMIT 1");
    $exists->execute();
    $airportId = (int)($exists->fetchColumn() ?: 0);
    if ($airportId > 0) {
        $pdo->prepare('UPDATE destinations SET default_nights=0 WHERE id=?')->execute([$airportId]);
    } else {
        $insert = $pdo->prepare("INSERT INTO destinations (country_code,slug,code,name_de,name_en,region_de,region_en,intro_de,intro_en,default_nights,price_from,accent,latitude,longitude,google_place_id,sort_order,active) VALUES ('LK','bandaranaike-international-airport','BIA','Bandaranaike International Flughafen','Bandaranaike International Airport','Westprovinz','Western Province','Ankunfts- und Abflugstopp am internationalen Flughafen. Für diesen Routenpunkt ist keine Übernachtung erforderlich.','Arrival and departure stop at the international airport. No overnight stay is required for this route point.',0,0,'mist',7.1808000,79.8841000,'',8,1)");
        $insert->execute();
    }
};
