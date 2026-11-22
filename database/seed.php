<?php
/**
 * Krono demo data (LOCAL ONLY, APP_ENV=local). Fills the whole site with sample content so every page can be checked.
 *
 *   php database/seed.php                       add the demo data (running it again replaces the old demo data, never duplicates)
 *   php database/seed.php --join=you@mail.com   same, and also adds YOUR existing account to the demo workspace as Admin
 *   php database/seed.php --remove              remove ONLY the demo data (your own accounts and workspaces are never touched)
 *   php database/seed.php --fresh --yes         DROP every table, rebuild them (schema.sql + migrate) and add the demo data
 *
 * Demo people use e-mails ending in @demo.krono.test and the password below. That e-mail ending is how demo data is recognised.
 * Demo attendance uses source "demo", demo projects start with "[demo]".
 */
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit('CLI only.');
if (env('APP_ENV') !== 'local') exit("Refused: demo data is only allowed when APP_ENV=local.\n");

const DEMO_DOMAIN = '@demo.krono.test';
const DEMO_PASSWORD = 'Demo@12345';
const DAYS_BACK = 13;

$opt = fn(string $n) => in_array($n, $argv, true);
$join = ''; foreach ($argv as $a) if (str_starts_with($a, '--join=')) $join = strtolower(trim(substr($a, 7)));
$f = 'Y-m-d H:i:s';

/** Insert one row from an array; returns the new id. */
function ins(string $table, array $row): int {
    Db::q("INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
    return (int)Db::pdo()->lastInsertId();
}
function in_list(array $ids): string { return $ids ? implode(',', array_map('intval', $ids)) : '0'; }
function col(string $sql): array { return array_map('intval', Db::q($sql)->fetchAll(PDO::FETCH_COLUMN)); }

/** Removes the demo data in an order that respects the foreign keys. Returns the number of demo people removed. */
function remove_demo(): int {
    $u = col("SELECT id FROM users WHERE email LIKE '%" . DEMO_DOMAIN . "'");
    $w = $u ? col('SELECT id FROM workspaces WHERE created_by IN (' . in_list($u) . ')') : [];
    $U = in_list($u); $W = in_list($w);
    if ($w) {
        $p = col("SELECT id FROM projects WHERE workspace_id IN ($W)"); $P = in_list($p);
        $t = $p ? col("SELECT id FROM tasks WHERE project_id IN ($P)") : []; $T = in_list($t);
        Db::q("DELETE FROM task_time_logs WHERE project_id IN ($P)");
        Db::q("DELETE FROM task_activity WHERE task_id IN ($T)");
        Db::q("DELETE FROM task_notes WHERE task_id IN ($T)");
        Db::q("DELETE FROM tasks WHERE project_id IN ($P)");
        Db::q("DELETE FROM activity_log WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM project_members WHERE project_id IN ($P)");
        Db::q("DELETE FROM projects WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM day_requests WHERE workspace_id IN ($W)");
        Db::q('DELETE FROM team_members WHERE team_id IN (SELECT id FROM teams WHERE workspace_id IN (' . $W . '))');
        Db::q("DELETE FROM teams WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM workspace_invitations WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM audit_log WHERE workspace_id IN ($W)");
        Db::q("UPDATE users SET current_workspace_id = NULL WHERE current_workspace_id IN ($W)");
        Db::q("DELETE FROM workspace_members WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM workspace_roles WHERE workspace_id IN ($W)");
        Db::q("DELETE FROM workspaces WHERE id IN ($W)");
    }
    if ($u) {
        Db::q("DELETE FROM task_time_logs WHERE user_id IN ($U)");
        Db::q('DELETE FROM attendance_breaks WHERE session_id IN (SELECT id FROM attendance_sessions WHERE user_id IN (' . $U . "))");
        Db::q("DELETE FROM attendance_sessions WHERE user_id IN ($U)");
        foreach (['web_sessions', 'api_tokens', 'login_handoffs', 'email_verification_codes', 'password_reset_codes', 'email_changes'] as $t) Db::q("DELETE FROM `$t` WHERE user_id IN ($U)");
        Db::q("DELETE FROM login_attempts WHERE email LIKE '%" . DEMO_DOMAIN . "'");
        Db::q("DELETE FROM users WHERE id IN ($U)");
    }
    return count($u);
}

/** Drops every table (used only by --fresh). */
function drop_everything(): void {
    $pdo = Db::pdo(); $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (Db::q('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN) as $t) $pdo->exec('DROP TABLE IF EXISTS `' . $t . '`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ---------------------------------------------------------------- modes

if ($opt('--remove')) { echo 'Demo data removed (' . remove_demo() . " demo people).\n"; exit; }
if ($opt('--fresh')) {
    $db = (string)env('DB_NAME', 'krono');
    if (!$opt('--yes')) exit("--fresh DROPS every table of the database \"$db\" (all accounts, workspaces and data), then rebuilds it with demo data.\nRun again with --yes to confirm:  php database/seed.php --fresh --yes\n");
    drop_everything(); echo "All tables of \"$db\" dropped. Rebuilding...\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/migrate.php'), $code); if ($code !== 0) exit("migrate.php failed.\n");
} else {
    $n = remove_demo(); if ($n) echo "Old demo data replaced ($n demo people removed first).\n";
}

// ---------------------------------------------------------------- data

mt_srand(2026);                                   // same demo data every time
$utc = new DateTimeZone('UTC'); $tz = new DateTimeZone('Asia/Dhaka');
$now = new DateTimeImmutable('now', $utc); $today = $now->setTimezone($tz)->setTime(0, 0);
$at = fn(DateTimeImmutable $local) => $local->setTimezone($utc)->format($f);           // local time -> UTC text for the database
$ago = fn(int $days, int $h = 10, int $m = 0) => $today->modify("-$days days")->setTime($h, $m);
$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
$pdo = Db::pdo(); $pdo->beginTransaction();
try {
    // ----- people (role, manager key, own days off, team)
    $people = [
        'owner'   => ['Amina Rahman',    'owner',   null,      null],
        'admin'   => ['Tanvir Hossain',  'admin',   'owner',   null],
        'mgr1'    => ['Nusrat Jahan',    'manager', 'admin',   null],
        'mgr2'    => ['Rafiq Ahmed',     'manager', 'admin',   null],
        'm1'      => ['Sadia Karim',     'member',  'mgr1',    null],
        'm2'      => ['Imran Chowdhury', 'manager', 'mgr1',    null],   // gets the custom role "Team Coordinator"
        'm3'      => ['Farhana Akter',   'member',  'mgr1',    'fri,sat'],
        'm4'      => ['Mahmudul Hasan',  'member',  'mgr1',    null],
        'm5'      => ['Jannat Ferdous',  'member',  'mgr2',    null],
        'm6'      => ['Shakib Al Amin',  'member',  'mgr2',    'sat,sun'],
        'm7'      => ['Lamia Sultana',   'member',  'mgr2',    null],
        'm8'      => ['Rakib Mondol',    'member',  'mgr2',    null],
        'viewer'  => ['Auditor Viewer',  'viewer',  'admin',   null],
        'gone'    => ['Former Employee', 'member',  'mgr2',    null],
    ];
    $uid = []; foreach ($people as $k => [$name]) {
        $uid[$k] = ins('users', ['name' => $name, 'email' => ($k === 'm1' ? 'm1' : $k) . DEMO_DOMAIN, 'password_hash' => $hash, 'email_verified_at' => $now->format($f), 'status' => 'active', 'created_at' => $at($ago(60))]);
    }
    $owner = $uid['owner'];

    // ----- workspaces: one company, one personal
    $ws = ins('workspaces', ['name' => 'Demo · Northstar Studio', 'timezone' => 'Asia/Dhaka', 'type' => 'company', 'country' => 'BD', 'company_name' => 'Northstar Studio Ltd', 'industry' => 'agency', 'created_by' => $owner, 'created_at' => $at($ago(60))]);
    $wsPersonal = ins('workspaces', ['name' => 'Demo · Amina\'s Workspace', 'timezone' => 'Asia/Dhaka', 'type' => 'personal', 'country' => 'BD', 'created_by' => $owner, 'created_at' => $at($ago(70))]);
    ins('workspace_members', ['workspace_id' => $wsPersonal, 'user_id' => $owner, 'role' => 'owner', 'status' => 'active', 'created_at' => $at($ago(70))]);
    Db::q('UPDATE users SET current_workspace_id = ? WHERE id = ?', [$ws, $owner]);

    // custom role
    $perms = ['projects.view', 'tasks.view', 'tasks.edit', 'time.view', 'attendance.view', 'team.view', 'team.groups'];
    $roleId = ins('workspace_roles', ['workspace_id' => $ws, 'name' => 'Team Coordinator', 'description' => 'Coordinates a team and its tasks', 'scope' => 'team', 'permissions' => json_encode($perms), 'created_by' => $owner, 'created_at' => $at($ago(30)), 'updated_at' => $at($ago(30))]);

    foreach ($people as $k => [$name, $role, $mgr, $off]) {
        $row = ['workspace_id' => $ws, 'user_id' => $uid[$k], 'role' => $role, 'manager_id' => $mgr ? $uid[$mgr] : null, 'status' => $k === 'gone' ? 'deactivated' : 'active', 'created_at' => $at($ago(58))];
        if ($off !== null) $row['days_off'] = $off;
        if ($k === 'm2') $row['custom_role_id'] = $roleId;
        ins('workspace_members', $row);
    }
    $offOf = fn(string $k) => explode(',', $people[$k][3] ?? 'fri');   // days off of a person (workspace default: Friday)

    // optional: add the person running this script, so they can look around with their own login
    if ($join !== '') {
        $me = Db::q('SELECT id FROM users WHERE email = ?', [$join])->fetchColumn();
        if (!$me) throw new RuntimeException("No account with the e-mail $join. Register it first, then run the command again.");
        ins('workspace_members', ['workspace_id' => $ws, 'user_id' => (int)$me, 'role' => 'admin', 'status' => 'active', 'created_at' => $at($ago(1))]);
        Db::q('UPDATE users SET current_workspace_id = ? WHERE id = ?', [$ws, (int)$me]);
    }

    // ----- teams (groups) and Team Leaders
    $teams = ['Design' => ['mgr1', ['m1', 'm2', 'm3', 'm4']], 'Development' => ['mgr2', ['m5', 'm6', 'm7', 'm8']], 'Support' => ['m8', ['m3']]];
    $teamId = [];
    foreach ($teams as $name => [$lead, $members]) {
        $teamId[$name] = ins('teams', ['workspace_id' => $ws, 'name' => $name, 'created_by' => $owner, 'created_at' => $at($ago(40))]);
        ins('team_members', ['team_id' => $teamId[$name], 'user_id' => $uid[$lead], 'role' => 'leader', 'created_at' => $at($ago(40))]);
        foreach ($members as $m) ins('team_members', ['team_id' => $teamId[$name], 'user_id' => $uid[$m], 'role' => 'member', 'created_at' => $at($ago(40))]);
    }

    // ----- projects, members, tasks
    $projects = [
        ['Website Redesign', 'in_progress', 'high', 'mgr1', ['mgr1', 'm1', 'm2', 'm3', 'm4'], 45, 20, [
            ['Design dashboard cards', 'completed', 'high', 'm1', -12, 240], ['Review responsive layout', 'in_progress', 'medium', 'm2', 2, 180], ['Build settings page', 'todo', 'normal', 'm3', 5, 360],
            ['Write handoff notes', 'completed', 'low', 'm4', -8, 60], ['Fix dark mode contrast', 'review', 'high', 'm1', 1, 120], ['Icon set cleanup', 'on_hold', 'low', 'm4', 9, 90]]],
        ['Client Portal', 'in_progress', 'medium', 'mgr2', ['mgr2', 'm5', 'm6', 'm7', 'm8'], 30, 35, [
            ['Implement auth API', 'in_progress', 'high', 'm5', 3, 480], ['Add token refresh', 'todo', 'medium', 'm6', 6, 240], ['Project list screen', 'completed', 'normal', 'm7', -5, 300],
            ['Invoice export', 'todo', 'normal', 'm8', 12, 180], ['Load testing', 'review', 'medium', 'm6', 0, 150], ['Write API docs', 'in_progress', 'low', 'm7', 7, 200]]],
        ['Mobile App', 'planning', 'medium', 'mgr2', ['mgr2', 'm5', 'm8'], 10, 90, [
            ['Choose framework', 'completed', 'high', 'm5', -3, 120], ['Wireframes', 'in_progress', 'medium', 'm8', 4, 300], ['Push notification plan', 'todo', 'normal', 'm5', 14, 150]]],
        ['Marketing Site', 'upcoming', 'normal', 'mgr1', ['mgr1', 'm1', 'm3'], 5, 120, [
            ['Collect brand assets', 'todo', 'normal', 'm1', 10, 120], ['Draft homepage copy', 'todo', 'low', 'm3', 15, 180]]],
        ['Old Landing Page', 'completed', 'low', 'mgr1', ['mgr1', 'm4'], 90, -20, [
            ['Final QA', 'completed', 'normal', 'm4', -25, 90], ['Archive assets', 'completed', 'low', 'mgr1', -22, 45]]],
    ];
    $taskRows = []; $projIds = [];     // $taskRows: [task id, project id, assignee key]
    foreach ($projects as [$pname, $pstatus, $prio, $lead, $mem, $startedAgo, $dueIn, $tasks]) {
        $pid = ins('projects', ['workspace_id' => $ws, 'name' => $pname, 'description' => '[demo] ' . $pname . ' sample project', 'status' => $pstatus, 'priority' => $prio, 'start_date' => $today->modify("-$startedAgo days")->format('Y-m-d'),
            'due_date' => $today->modify("+$dueIn days")->format('Y-m-d'), 'leader_id' => $uid[$lead], 'created_by' => $owner, 'created_at' => $at($ago($startedAgo))]);
        $projIds[$pname] = $pid;
        foreach ($mem as $m) ins('project_members', ['project_id' => $pid, 'user_id' => $uid[$m], 'added_at' => $at($ago($startedAgo))]);
        ins('activity_log', ['workspace_id' => $ws, 'project_id' => $pid, 'user_id' => $uid[$lead], 'type' => 'project_created', 'subject' => null, 'created_at' => $at($ago($startedAgo))]);
        foreach ($tasks as $i => [$title, $st, $tp, $who, $dueOff, $est]) {
            $created = $ago(max(1, $startedAgo - 2 - $i));
            $tid = ins('tasks', ['project_id' => $pid, 'title' => $title, 'description' => 'Sample task for checking the design: ' . strtolower($title) . '.', 'status' => $st, 'priority' => $tp, 'assignee_id' => $uid[$who],
                'due_date' => $today->modify(($dueOff >= 0 ? '+' : '') . $dueOff . ' days')->format('Y-m-d'), 'estimate_minutes' => $est, 'created_by' => $uid[$lead], 'created_at' => $at($created),
                'completed_at' => $st === 'completed' ? $at($today->modify(($dueOff - 1) . ' days')->setTime(16, 0)) : null]);
            $taskRows[] = [$tid, $pid, $who];
            ins('activity_log', ['workspace_id' => $ws, 'project_id' => $pid, 'user_id' => $uid[$lead], 'type' => 'task_created', 'subject' => $title, 'created_at' => $at($created)]);
            if ($st === 'completed') ins('activity_log', ['workspace_id' => $ws, 'project_id' => $pid, 'user_id' => $uid[$who], 'type' => 'task_completed', 'subject' => $title, 'created_at' => $at($today->modify(($dueOff - 1) . ' days')->setTime(16, 0))]);
            if ($st !== 'todo') ins('task_activity', ['task_id' => $tid, 'user_id' => $uid[$who], 'type' => 'status', 'old_value' => 'To Do', 'new_value' => ['in_progress' => 'In Progress', 'review' => 'Review', 'on_hold' => 'On Hold', 'completed' => 'Completed'][$st], 'created_at' => $at($created->modify('+1 day'))]);
            if ($i % 2 === 0) ins('task_notes', ['task_id' => $tid, 'body' => "Notes for \"$title\":\n- agreed scope with the team\n- waiting for review", 'updated_by' => $uid[$who], 'updated_at' => $at($created->modify('+1 day'))]);
        }
    }

    // ----- attendance (Office / Remote / Hybrid), breaks, time logs; leave days stay free of sessions
    $leave = [   // person => [offset days from today, status, comment]
        ['m1', -4, 'approved', 'Family function'], ['m2', 3, 'pending', 'Doctor appointment'], ['m3', -3, 'rejected', 'Personal work'], ['m5', 4, 'pending', 'Travel to hometown'],
    ];
    foreach ($leave as &$l) { while (in_array(strtolower($today->modify($l[1] . ' days')->format('D')), $offOf($l[0]), true)) $l[1] += $l[1] < 0 ? -1 : 1; } unset($l);   // never on the person's weekly day off
    $leaveDay = []; foreach ($leave as [$k, $off, $status, $c]) $leaveDay[$k][$today->modify("$off days")->format('Y-m-d')] = true;
    $workers = ['mgr1', 'mgr2', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8'];
    $loc = ['mgr1' => 'office', 'mgr2' => 'office', 'm1' => 'office', 'm2' => 'remote', 'm3' => 'office', 'm4' => 'office', 'm5' => 'hybrid', 'm6' => 'remote', 'm7' => 'office', 'm8' => 'office'];
    $descs = ['Reviewed spacing and refined responsive states', 'Added validation and error handling', 'Tested on desktop and tablet widths', 'Wrote copy and checked the layout', 'Fixed small visual bugs', 'Prepared the handoff notes'];
    $myTasks = []; foreach ($taskRows as [$tid, $pid, $who]) if ($people[$who][1] !== 'viewer') $myTasks[$who][] = [$tid, $pid];
    $nowLocal = $now->setTimezone($tz); $sessions = 0; $logs = 0;
    for ($back = DAYS_BACK; $back >= 0; $back--) {
        $day = $today->modify("-$back days"); $ymd = $day->format('Y-m-d'); $dow = strtolower($day->format('D'));
        foreach ($workers as $k) {
            if (in_array($dow, $offOf($k), true) || isset($leaveDay[$k][$ymd])) continue;
            if (mt_rand(1, 100) <= 8 && $back > 0) continue;               // an occasional absence
            $in = $day->setTime(9, 0)->modify('+' . mt_rand(-20, 45) . ' minutes');
            if ($back === 0 && $in > $nowLocal) continue;                  // not yet arrived today
            $place = $loc[$k] === 'hybrid' ? (((int)$day->format('N')) % 2 ? 'office' : 'remote') : $loc[$k];
            $out = $day->setTime(17, 30)->modify('+' . mt_rand(-25, 50) . ' minutes');
            if ($back === 0) { if ($in > $nowLocal->modify('-5 minutes')) continue; if (in_array($k, ['mgr1', 'm1', 'm5', 'm8'], true) || $out > $nowLocal) $out = null; }   // still working today
            $sid = ins('attendance_sessions', ['user_id' => $uid[$k], 'sign_in_at' => $at($in), 'sign_out_at' => $out ? $at($out) : null, 'auto_closed' => 0, 'source' => 'demo', 'work_location' => $place, 'created_at' => $at($in)]);
            $sessions++;
            $bs = $day->setTime(13, 0)->modify('+' . mt_rand(0, 30) . ' minutes');
            if ($bs > $in && ($out === null ? $bs < $nowLocal : $bs < $out)) ins('attendance_breaks', ['session_id' => $sid, 'start_at' => $at($bs), 'end_at' => $at($bs->modify('+' . mt_rand(30, 50) . ' minutes'))]);
            // time logs inside the working day
            $mine = $myTasks[$k] ?? []; if (!$mine) continue;
            $cursor = $in->modify('+15 minutes');
            for ($n = mt_rand(1, 3); $n > 0; $n--) {
                [$tid, $pid] = $mine[array_rand($mine)]; $mins = mt_rand(3, 12) * 15; $end = $cursor->modify("+$mins minutes");
                if (($out && $end > $out) || (!$out && $end > $nowLocal->modify('-10 minutes'))) break;
                ins('task_time_logs', ['user_id' => $uid[$k], 'project_id' => $pid, 'task_id' => $tid, 'started_at' => $at($cursor), 'ended_at' => $at($end), 'duration_seconds' => $mins * 60, 'work_description' => $descs[array_rand($descs)], 'created_at' => $at($end)]);
                $logs++; $cursor = $end->modify('+' . mt_rand(10, 40) . ' minutes');
            }
        }
    }

    // ----- requests waiting for approval and decided ones
    foreach ($leave as [$k, $off, $status, $c]) {
        $row = ['workspace_id' => $ws, 'user_id' => $uid[$k], 'kind' => 'leave', 'leave_date' => $today->modify("$off days")->format('Y-m-d'), 'comment' => $c, 'status' => $status, 'created_at' => $at($ago(abs($off) + 2))];
        if ($status !== 'pending') { $row['decided_by'] = $uid[$people[$k][2]]; $row['decided_at'] = $at($ago(abs($off) + 1)); }
        ins('day_requests', $row);
    }
    ins('day_requests', ['workspace_id' => $ws, 'user_id' => $uid['m7'], 'kind' => 'days_off', 'days_off' => 'sat,sun', 'status' => 'pending', 'created_at' => $at($ago(1))]);

    // ----- invitations (the links cannot be opened: only a random hash is stored, which is also how real ones are kept)
    $inv = [['invitee1', 'member', 'pending', 5, $teamId['Design']], ['invitee2', 'viewer', 'pending', 3, null], ['invitee3', 'member', 'cancelled', 4, null], ['invitee4', 'member', 'pending', -2, null]];
    foreach ($inv as [$name, $role, $status, $daysLeft, $team]) {
        ins('workspace_invitations', ['workspace_id' => $ws, 'email' => $name . DEMO_DOMAIN, 'role' => $role, 'manager_id' => $uid['admin'], 'team_id' => $team, 'token_hash' => hash('sha256', random_bytes(16)),
            'status' => $status, 'invited_by' => $uid['admin'], 'expires_at' => $at($today->modify("+$daysLeft days")->setTime(12, 0)), 'created_at' => $at($ago(2))]);
    }

    // ----- audit log
    $audit = [['workspace_created', $owner, null, 'Company workspace: Demo · Northstar Studio', 60], ['invite_sent', $uid['admin'], null, 'invitee1' . DEMO_DOMAIN . ' as member', 2], ['team_created', $owner, null, 'Design', 40], ['team_created', $owner, null, 'Development', 40],
        ['team_leader_set', $owner, $uid['mgr1'], 'Design', 40], ['team_leader_set', $owner, $uid['mgr2'], 'Development', 40], ['role_changed', $uid['admin'], $uid['m2'], 'Member → Team Coordinator', 30],
        ['request_approved', $uid['mgr1'], $uid['m1'], 'Leave on ' . $today->modify('-4 days')->format('Y-m-d'), 3], ['request_rejected', $uid['mgr1'], $uid['m3'], 'Leave on ' . $today->modify('-2 days')->format('Y-m-d'), 1],
        ['daysoff_requested', $uid['m7'], $uid['m7'], 'Days off → Sat, Sun', 1], ['deactivated', $uid['admin'], $uid['gone'], 'Access turned off', 15]];
    foreach ($audit as [$action, $actor, $target, $detail, $d]) ins('audit_log', ['workspace_id' => $ws, 'actor_id' => $actor, 'target_user_id' => $target, 'action' => $action, 'detail' => $detail, 'created_at' => $at($ago($d, 11))]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Demo data failed, nothing was saved: ' . $e->getMessage() . "\n"); exit(1);
}

echo "Demo data added.\n";
echo "  Company workspace  : Demo · Northstar Studio (Owner, Admin, 3 Managers, members, Viewer, a deactivated person)\n";
echo "  Personal workspace : Demo · Amina's Workspace\n";
echo "  Projects 5, tasks " . count($taskRows) . ", attendance sessions $sessions, time logs $logs, teams 3, requests 5, invitations 4\n";
echo "  Log in with one of these (password for all: " . DEMO_PASSWORD . "):\n";
foreach (['owner' => 'Owner', 'admin' => 'Admin', 'mgr1' => 'Manager', 'm1' => 'Member', 'viewer' => 'Viewer'] as $k => $r) echo '    ' . str_pad($k . DEMO_DOMAIN, 30) . $r . "\n";
if ($join !== '') echo "  Your account $join was added to the company workspace as Admin; open Switch workspace to see it.\n";
echo "Remove it again:  php database/seed.php --remove\n";
