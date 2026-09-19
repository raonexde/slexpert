<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $destinations = require dirname(__DIR__) . '/seed-destinations.php';
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO destinations (slug,code,name_de,name_en,region_de,region_en,intro_de,intro_en,default_nights,price_from,accent,latitude,longitude,google_place_id,sort_order,active)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    foreach ($destinations as $destination) {
        $insert->execute($destination);
    }
};
