<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $db = $config['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function run_pending_migrations(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(190) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $applied = array_flip($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    $files = array_merge(
        glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [],
        glob(dirname(__DIR__) . '/database/migrations/*.php') ?: []
    );
    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $migration = basename($file);
        if (isset($applied[$migration])) {
            continue;
        }
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            $runner = require $file;
            if (!is_callable($runner)) {
                throw new RuntimeException('PHP migration must return a callable: ' . $migration);
            }
            $runner($pdo);
        } else {
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Migration could not be read: ' . $migration);
            }
            $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
        }
        $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$migration]);
    }
}
