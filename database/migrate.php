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
// The workspace a person is currently working in (set when they accept an invitation). NULL = their first workspace.
if (!$has('users', 'current_workspace_id')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN current_workspace_id BIGINT UNSIGNED NULL');
    echo str_pad('users.current_workspace_id', 28), "added\n";
}
// Profile settings: the person's own timezone and date format (NULL / default = follow the workspace and the old look).
if (!$has('users', 'timezone')) {
    $pdo->exec('ALTER TABLE users ADD COLUMN timezone VARCHAR(64) NULL');
    echo str_pad('users.timezone', 28), "added\n";
}
if (!$has('users', 'date_format')) {
    $pdo->exec("ALTER TABLE users ADD COLUMN date_format VARCHAR(20) NOT NULL DEFAULT ''");
    echo str_pad('users.date_format', 28), "added\n";
}
// New accounts follow the workspace date format until they choose their own (the column default only; existing rows are not touched).
$pdo->exec("ALTER TABLE users ALTER COLUMN date_format SET DEFAULT ''");
// Settings: profile photo, theme and notification choices (per person).
$add = [
    ['users', 'avatar', "VARCHAR(80) NULL"], ['users', 'theme', "VARCHAR(6) NOT NULL DEFAULT 'system'"], ['users', 'notify_email', 'TINYINT(1) NOT NULL DEFAULT 1'],
    ['users', 'notify_weekly', 'TINYINT(1) NOT NULL DEFAULT 1'], ['users', 'notify_tips', 'TINYINT(1) NOT NULL DEFAULT 0'],
    // Workspace defaults (Settings > Workspace > General)
    ['workspaces', 'week_start', "VARCHAR(3) NOT NULL DEFAULT 'mon'"], ['workspaces', 'date_format', "VARCHAR(20) NOT NULL DEFAULT 'DD MMM YYYY'"],
    ['workspaces', 'time_format', "VARCHAR(2) NOT NULL DEFAULT '12'"], ['workspaces', 'language', "VARCHAR(8) NOT NULL DEFAULT 'en'"],
    // Audit Log shows whether an action worked
    ['audit_log', 'result', "VARCHAR(8) NOT NULL DEFAULT 'success'"],
    // Custom roles: which custom role a member / invitation has (their built-in 'role' column then holds the base role of the custom role's data scope)
    ['workspace_members', 'custom_role_id', 'BIGINT UNSIGNED NULL'], ['workspace_invitations', 'custom_role_id', 'BIGINT UNSIGNED NULL'],
    // Where the person worked on that sign-in (chosen in Krono Desktop at Sign In; a day with both kinds is shown as Hybrid)
    ['attendance_sessions', 'work_location', "VARCHAR(10) NOT NULL DEFAULT 'office'"],
    // Weekly days off: the workspace default (Friday) and a person's own (NULL = follow the workspace)
    ['workspace_invitations', 'team_id', 'BIGINT UNSIGNED NULL'], ['workspaces', 'plan', "VARCHAR(20) NOT NULL DEFAULT 'free'"], ['workspaces', 'type', "VARCHAR(10) NOT NULL DEFAULT 'personal'"], ['workspaces', 'country', 'VARCHAR(2) NULL'], ['workspaces', 'company_name', 'VARCHAR(160) NULL'], ['workspaces', 'industry', 'VARCHAR(30) NULL'], ['workspaces', 'days_off', "VARCHAR(30) NOT NULL DEFAULT 'fri'"], ['workspace_members', 'days_off', 'VARCHAR(30) NULL'],
];
foreach ($add as [$t, $c, $def]) if (!$has($t, $c)) { $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def"); echo str_pad("$t.$c", 28), "added\n"; }
// Every account gets a personal workspace (owner). Existing projects join the workspace of the user who created them.
foreach (Db::q('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN) as $uid) Workspace::ensureFor((int)$uid);
$n = Db::q('UPDATE projects p SET p.workspace_id = (SELECT m.workspace_id FROM workspace_members m WHERE m.user_id = p.created_by ORDER BY m.created_at LIMIT 1) WHERE p.workspace_id IS NULL')->rowCount();
if ($n) echo "projects linked to a workspace: $n\n";
echo "Done.\n";
