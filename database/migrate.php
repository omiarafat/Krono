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
// Upgrades for tables that already exist (CREATE TABLE IF NOT EXISTS cannot add columns). Each step runs only when needed; no data is removed.
$has = fn(string $t, string $c) => (int)Db::q('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$t, $c])->fetchColumn() > 0;
if (!$has('projects', 'workspace_id')) {
    $pdo->exec('ALTER TABLE projects ADD COLUMN workspace_id BIGINT UNSIGNED NULL AFTER id, ADD KEY idx_projects_ws (workspace_id), ADD CONSTRAINT fk_projects_ws FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE');
    echo str_pad('projects.workspace_id', 28), "added\n";
}
// Every account gets a personal workspace (owner). Existing projects join the workspace of the user who created them.
foreach (Db::q('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN) as $uid) Workspace::ensureFor((int)$uid);
$n = Db::q('UPDATE projects p SET p.workspace_id = (SELECT m.workspace_id FROM workspace_members m WHERE m.user_id = p.created_by ORDER BY m.created_at LIMIT 1) WHERE p.workspace_id IS NULL')->rowCount();
if ($n) echo "projects linked to a workspace: $n\n";
echo "Done.\n";
