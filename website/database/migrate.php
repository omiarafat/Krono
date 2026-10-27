<?php
// Usage (Laragon terminal, inside website/):  php database/migrate.php
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit('CLI only.');
$db = (string)env('DB_NAME', 'krono');
if (env('APP_ENV') === 'local') {
    Db::pdo(true)->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$pdo = Db::pdo();
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/migrations/*.sql'); sort($files);
foreach ($files as $f) {
    $n = basename($f); if (in_array($n, $done, true)) { echo "skip   $n\n"; continue; }
    $pdo->exec((string)file_get_contents($f));
    Db::q('INSERT INTO schema_migrations (name) VALUES (?)', [$n]);
    echo "applied $n\n";
}
echo "Done.\n";
