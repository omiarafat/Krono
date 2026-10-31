<?php
// Usage (Laragon terminal, inside website/):  php database/migrate.php
// Runs database/schema.sql. Tables that already exist are kept as they are (no data is touched); only missing tables are created.
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit('CLI only.');
$db = (string)env('DB_NAME', 'krono');
if (env('APP_ENV') === 'local') {
    Db::pdo(true)->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
$pdo = Db::pdo();
$sql = preg_replace('/--[^\n]*/', '', (string)file_get_contents(__DIR__ . '/schema.sql'));
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if (!preg_match('/^CREATE TABLE IF NOT EXISTS `?(\w+)`?/i', $stmt, $m)) { $pdo->exec($stmt); continue; }
    $had = Db::q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$m[1]])->fetchColumn() > 0;
    $pdo->exec($stmt);
    echo str_pad($m[1], 28), $had ? "exists (kept, data untouched)\n" : "created\n";
}
echo "Done.\n";
