<?php
declare(strict_types=1);

session_start();
$root = __DIR__;
$alreadyInstalled = is_file($root . '/config.local.php') && is_file($root . '/storage/install.lock');
$errors = [];
$values = [
    'db_host' => $_POST['db_host'] ?? '127.0.0.1',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? 'srilanka_tours',
    'db_user' => $_POST['db_user'] ?? 'root',
    'base_url' => $_POST['base_url'] ?? (
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/')
    ),
    'site_name' => $_POST['site_name'] ?? 'Sri Lanka Expert',
    'admin_name' => $_POST['admin_name'] ?? 'Administrator',
    'admin_email' => $_POST['admin_email'] ?? '',
];

if (!$alreadyInstalled && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !isset($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) {
        $errors[] = 'The security token expired. Please reload the page.';
    }

    $dbName = trim((string)$values['db_name']);
    $dbPort = (int)$values['db_port'];
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $dbPassword = (string)($_POST['db_pass'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
        $errors[] = 'The database name may only contain letters, numbers and underscores.';
    }
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid administrator email.';
    }
    if (strlen($adminPassword) < 10) {
        $errors[] = 'The administrator password must contain at least 10 characters.';
    }
    if (!filter_var($values['base_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid website URL.';
    }

    if (!$errors) {
        try {
            $pdoOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $databaseDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $values['db_host'], $dbPort, $dbName);
            try {
                $pdo = new PDO($databaseDsn, $values['db_user'], $dbPassword, $pdoOptions);
            } catch (PDOException $databaseException) {
                $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $values['db_host'], $dbPort);
                $pdo = new PDO($serverDsn, $values['db_user'], $dbPassword, $pdoOptions);
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo->exec('USE `' . $dbName . '`');
            }

            $schema = file_get_contents($root . '/database/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Database schema file is missing.');
            }
            foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [])) as $statement) {
                $pdo->exec($statement);
            }
            $migrationMarker = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (?)');
            foreach (['002_google_maps.sql', '003_destination_catalog.php', '004_accommodation_meal_plans.php'] as $migration) {
                $migrationMarker->execute([$migration]);
            }

            $pdo->beginTransaction();
            $admin = $pdo->prepare('INSERT INTO admin_users (name, email, password_hash, role, is_super_admin) VALUES (?, ?, ?, \'admin\', 1)');
            $admin->execute([
                trim((string)$values['admin_name']),
                strtolower(trim((string)$values['admin_email'])),
                password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            $destinationInsert = $pdo->prepare(
                'INSERT INTO destinations (slug, code, name_de, name_en, region_de, region_en, intro_de, intro_en, default_nights, price_from, accent, latitude, longitude, google_place_id, sort_order, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $destinations = require $root . '/database/seed-destinations.php';
            $destinationIds = [];
            foreach ($destinations as $row) {
                $destinationInsert->execute($row);
                $destinationIds[$row[0]] = (int)$pdo->lastInsertId();
            }

            $itemInsert = $pdo->prepare(
                'INSERT INTO catalog_items (destination_id, type, name_de, name_en, description_de, description_en, meta_de, meta_en, price_per_person, featured, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $mealPlanInsert = $pdo->prepare(
                'INSERT INTO accommodation_meal_plans (catalog_item_id, code, name_de, name_en, supplement_per_person_night, active, sort_order)
                 VALUES (?, ?, ?, ?, ?, 1, ?)'
            );
            $defaultMealPlans = [
                ['breakfast', 'Frühstück', 'Breakfast', 0.00, 10],
                ['room_only', 'Nur Übernachtung', 'Room only', 0.00, 20],
                ['half_board', 'Halbpension', 'Half board', 28.00, 30],
                ['full_board', 'Vollpension', 'Full board', 46.00, 40],
                ['all_inclusive', 'All-inclusive', 'All inclusive', 68.00, 50],
            ];
            $items = [
                ['negombo','accommodation','Lagoon Arrival Villa','Lagoon Arrival Villa','Gartenzimmer mit Frühstück','Garden room with breakfast','Flughafentransfer · Pool','Airport transfer · Pool',98,1,10],
                ['negombo','activity','Lagunenfahrt am Morgen','Morning lagoon boat','Fischerleben und Vogelwelt','Fishing life and birdlife','2 Stunden','2 hours',29,0,20],
                ['sigiriya','accommodation','Water Garden Retreat','Water Garden Retreat','Boutique-Villa mit Frühstück','Boutique villa with breakfast','Ruhig · Pool · Naturblick','Quiet · Pool · Nature view',132,1,10],
                ['sigiriya','accommodation','Village Eco Lodge','Village Eco Lodge','Authentische Eco-Cabana','Authentic eco cabana','Lokal · Garten · Frühstück','Local · Garden · Breakfast',76,0,20],
                ['sigiriya','sight','Sigiriya bei Sonnenaufgang','Sigiriya at sunrise','Privater Aufstieg mit Guide','Private climb with a guide','3 Stunden · Eintritt inklusive','3 hours · Admission included',58,1,30],
                ['sigiriya','activity','Dorfküche & Reisfelder','Village kitchen & rice fields','Kochen mit einer Gastfamilie','Cooking with a host family','4 Stunden · Mittagessen','4 hours · Lunch',42,0,40],
                ['sigiriya','shop','Habarana Handwerkshaus','Habarana Craft House','Weberei und fairer Souvenir-Stopp','Weaving and fair souvenir stop','Kostenloser Besuch','Complimentary visit',0,0,50],
                ['kandy','accommodation','Kandy Hills Bungalow','Kandy Hills Bungalow','Heritage-Zimmer mit Frühstück','Heritage room with breakfast','Aussicht · Garten · Boutique','View · Garden · Boutique',118,1,10],
                ['kandy','sight','Zahntempel & Altstadt','Temple of the Tooth & old town','Besichtigung mit Kulturguide','Visit with a culture guide','3 Stunden','3 hours',39,0,20],
                ['kandy','activity','Gewürzgarten mit Ayurveda','Spice garden & Ayurveda','Private Einführung in Heilpflanzen','Private introduction to medicinal plants','2,5 Stunden','2.5 hours',28,0,30],
                ['ella','accommodation','Tea Ridge Hideaway','Tea Ridge Hideaway','Panorama-Chalet mit Frühstück','Panorama chalet with breakfast','Bergblick · Kamin · Ruhig','Mountain view · Fireplace · Quiet',144,1,10],
                ['ella','sight','Nine Arch Bridge & Little Adam’s Peak','Nine Arch Bridge & Little Adam’s Peak','Frühe geführte Wanderung','Early guided hike','4 Stunden','4 hours',34,0,20],
                ['ella','activity','Tee von Blatt bis Tasse','Tea from leaf to cup','Besuch einer kleinen Bio-Plantage','Visit to a small organic estate','3 Stunden · Verkostung','3 hours · Tasting',31,0,30],
                ['yala','accommodation','Wild Coast Tented Lodge','Wild Coast Tented Lodge','Safari-Zelt mit Halbpension','Safari tent with half board','Natur · Pool · Ranger','Nature · Pool · Ranger',198,1,10],
                ['yala','activity','Private Morgen-Safari','Private morning safari','Jeepfahrt mit Naturkundler','Jeep drive with a naturalist','5 Stunden · Eintritt','5 hours · Admission',96,1,20],
                ['galle','accommodation','Fort House No. 12','Fort House No. 12','Designhotel mit Frühstück','Design hotel with breakfast','Im Fort · Innenhof','Inside the fort · Courtyard',156,1,10],
                ['galle','sight','Fort-Spaziergang mit Historiker','Fort walk with a historian','Private Tour bei Sonnenuntergang','Private sunset tour','2,5 Stunden','2.5 hours',36,0,20],
                ['galle','shop','Galle Design Walk','Galle Design Walk','Ateliers, Textilien und Gewürze','Studios, textiles and spices','Kuratiert · Flexibel','Curated · Flexible',15,0,30],
            ];
            foreach ($items as $row) {
                $slug = array_shift($row);
                $itemInsert->execute(array_merge([$destinationIds[$slug]], $row));
                if ($row[0] === 'accommodation') {
                    $accommodationId = (int)$pdo->lastInsertId();
                    foreach ($defaultMealPlans as $plan) {
                        $mealPlanInsert->execute(array_merge([$accommodationId], $plan));
                    }
                }
            }
            $settings = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $settings->execute(['site_name', trim((string)$values['site_name'])]);
            $settings->execute(['default_language', 'de']);
            $settings->execute(['google_maps_api_key', '']);
            $settings->execute(['google_maps_map_id', 'DEMO_MAP_ID']);
            $pdo->commit();

            $config = [
                'app' => [
                    'name' => trim((string)$values['site_name']),
                    'base_url' => rtrim((string)$values['base_url'], '/'),
                    'timezone' => 'Europe/Berlin',
                    'debug' => false,
                ],
                'db' => [
                    'host' => trim((string)$values['db_host']),
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => trim((string)$values['db_user']),
                    'pass' => $dbPassword,
                    'charset' => 'utf8mb4',
                ],
            ];
            $configPhp = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($root . '/config.local.php', $configPhp, LOCK_EX) === false) {
                throw new RuntimeException('config.local.php could not be written. Check folder permissions.');
            }
            if (!is_dir($root . '/storage')) {
                mkdir($root . '/storage', 0775, true);
            }
            file_put_contents($root . '/storage/install.lock', date(DATE_ATOM), LOCK_EX);
            header('Location: ' . rtrim((string)$values['base_url'], '/') . '/admin/login.php?installed=1');
            exit;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Installation failed: ' . $exception->getMessage();
        }
    }
}

$_SESSION['install_csrf'] = bin2hex(random_bytes(32));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Install · Sri Lanka Tailor-Made Tours</title>
    <style>
        :root{--ink:#15382e;--gold:#e7a838;--paper:#f4f1e9;--line:#d7d5ce}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;grid-template-columns:1fr minmax(520px,680px)}.visual{background:linear-gradient(135deg,rgba(11,40,32,.96),rgba(21,56,46,.7)),url('assets/images/hero.jpg') center/cover;color:#fff;padding:70px;display:flex;flex-direction:column;justify-content:space-between}.brand{font-family:Georgia,serif;letter-spacing:.15em}.visual h1{font:400 58px/1.03 Georgia,serif;max-width:560px;margin:0}.visual p{max-width:500px;line-height:1.7;color:#d1d8d3}.panel{padding:60px 70px;background:#fff;overflow:auto}.eyebrow{font-size:10px;letter-spacing:.18em;color:#a47625;font-weight:700}.panel h2{font:400 36px Georgia,serif;margin:12px 0 8px}.lead{font-size:13px;color:#777;line-height:1.6;margin-bottom:28px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.field{margin:13px 0}.field.full{grid-column:1/-1}label{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.1em;font-weight:700;margin-bottom:7px}input{width:100%;padding:12px;border:1px solid var(--line);background:#faf9f6}.section{font:400 17px Georgia,serif;margin:29px 0 6px;border-bottom:1px solid var(--line);padding-bottom:9px}.button{width:100%;margin-top:22px;padding:15px;border:0;background:var(--gold);color:var(--ink);text-transform:uppercase;letter-spacing:.1em;font-size:10px;font-weight:700;cursor:pointer}.error{background:#f9e3dd;color:#8c392c;padding:12px;margin:8px 0;font-size:12px}.done{padding:30px;background:#eef5ef}.note{font-size:10px;color:#777;line-height:1.5}@media(max-width:900px){.wrap{grid-template-columns:1fr}.visual{min-height:320px;padding:35px}.visual h1{font-size:40px}.panel{padding:40px 25px}}@media(max-width:560px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="visual">
        <div class="brand">SRI LANKA · EXPERT <small>BY RAONEX GMBH</small></div>
        <div><p>PHP + MySQL · German + English</p><h1>Your tailor-made Sri Lanka tour platform.</h1><p>Customer journey builder, accommodation and activity selection, enquiry management and a complete admin catalogue.</p></div>
        <small>Local installation · XAMPP / MAMP / Linux</small>
    </section>
    <section class="panel">
        <p class="eyebrow">INSTALLATION WIZARD</p>
        <?php if ($alreadyInstalled): ?>
            <div class="done"><h2>Already installed</h2><p>This installation is locked.</p><p><a href="admin/login.php">Open administration →</a></p></div>
        <?php else: ?>
            <h2>Set up the website.</h2>
            <p class="lead">For XAMPP, the standard values are host <strong>127.0.0.1</strong>, user <strong>root</strong> and an empty database password.</p>
            <?php foreach ($errors as $error): ?><div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['install_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <h3 class="section">Website</h3>
                <div class="field"><label>Website name</label><input name="site_name" required value="<?= htmlspecialchars((string)$values['site_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label>Complete local URL</label><input name="base_url" required value="<?= htmlspecialchars((string)$values['base_url'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <h3 class="section">MySQL connection</h3>
                <div class="grid">
                    <div class="field"><label>Database host</label><input name="db_host" required value="<?= htmlspecialchars((string)$values['db_host'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="field"><label>Port</label><input name="db_port" type="number" required value="<?= (int)$values['db_port'] ?>"></div>
                    <div class="field"><label>Database name</label><input name="db_name" required value="<?= htmlspecialchars((string)$values['db_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="field"><label>Database user</label><input name="db_user" required value="<?= htmlspecialchars((string)$values['db_user'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="field full"><label>Database password</label><input name="db_pass" type="password" autocomplete="new-password"><p class="note">Usually empty for a new local XAMPP installation.</p></div>
                </div>
                <h3 class="section">Administrator</h3>
                <div class="field"><label>Your name</label><input name="admin_name" required value="<?= htmlspecialchars((string)$values['admin_name'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label>Email address</label><input name="admin_email" type="email" required value="<?= htmlspecialchars((string)$values['admin_email'], ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label>Password · minimum 10 characters</label><input name="admin_password" type="password" minlength="10" required autocomplete="new-password"></div>
                <button class="button" type="submit">Install website →</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
