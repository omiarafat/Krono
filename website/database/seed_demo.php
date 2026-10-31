<?php
// Local-only demo data so the Dashboard shows real numbers before Projects / Tasks / Attendance pages and the desktop timer exist.
// Usage (inside website/):   php database/seed_demo.php you@example.com          add demo data for that account
//                            php database/seed_demo.php you@example.com --clear  remove ONLY the demo data again
// Demo projects are tagged with the description prefix "[demo]" and demo attendance with source "demo"; nothing else is touched.
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit('CLI only.');
if (env('APP_ENV') !== 'local') exit("Refused: demo data is only allowed when APP_ENV=local.\n");
$email = Auth::normEmail((string)($argv[1] ?? ''));
$u = $email ? Db::q('SELECT id FROM users WHERE email = ?', [$email])->fetch() : null;
if (!$u) exit("Usage: php database/seed_demo.php <registered-email> [--clear]\n");
$uid = (int)$u['id'];

// remove old demo data first (so running twice never duplicates)
Db::q("DELETE FROM projects WHERE created_by = ? AND description LIKE '[demo]%'", [$uid]); // tasks + time logs cascade
Db::q("DELETE FROM attendance_sessions WHERE user_id = ? AND source = 'demo'", [$uid]);      // breaks cascade
if (in_array('--clear', $argv, true)) exit("Demo data removed.\n");

$utc = new DateTimeZone('UTC'); $now = new DateTimeImmutable('now', $utc); $f = 'Y-m-d H:i:s';
$today = $now->setTimezone(app_tz())->setTime(0, 0);
$mk = function (string $name, string $status, int $monthsAgoDays) use ($uid, $now, $f): int {
    Db::q('INSERT INTO projects (name, description, status, created_by, created_at) VALUES (?,?,?,?,?)', [$name, '[demo] sample project', $status, $uid, $now->modify("-$monthsAgoDays days")->format($f)]);
    return (int)Db::pdo()->lastInsertId();
};
$task = function (int $pid, string $title, string $status, ?string $due) use ($uid, $f, $now): int {
    Db::q('INSERT INTO tasks (project_id, title, status, due_date, created_by, completed_at) VALUES (?,?,?,?,?,?)', [$pid, $title, $status, $due, $uid, $status === 'completed' ? $now->format($f) : null]);
    return (int)Db::pdo()->lastInsertId();
};
$wk = $today->modify('monday this week');
$p1 = $mk('Website Redesign', 'in_progress', 40); $p2 = $mk('Client Portal', 'in_progress', 25); $p3 = $mk('Marketing Website', 'planning', 3); $p4 = $mk('Old Landing Page', 'completed', 90);
$t = [];
foreach ([[$p1, 'Design dashboard cards', 'completed'], [$p1, 'Review responsive layout', 'in_progress'], [$p1, 'Build settings page', 'todo'], [$p1, 'Write handoff notes', 'completed'],
          [$p2, 'Implement auth API', 'in_progress'], [$p2, 'Add token refresh', 'todo'], [$p2, 'Project list screen', 'completed'],
          [$p3, 'Collect brand assets', 'todo'], [$p3, 'Draft homepage copy', 'todo'], [$p4, 'Final QA', 'completed']] as $i => [$pid, $title, $st]) {
    $due = in_array($st, ['todo', 'in_progress'], true) ? $wk->modify('+' . ($i % 7) . ' days')->format('Y-m-d') : null;
    $t[$title] = [$task($pid, $title, $st, $due), $pid];
}
$names = array_keys($t); $descs = ['Reviewed spacing and refined responsive card states', 'Added endpoint validation and error handling', 'Tested desktop and tablet widths', 'Wrote copy and checked layout', 'Fixed small bugs found in review'];
foreach (range(0, 13) as $back) {
    $day = $today->modify("-$back days"); if ((int)$day->format('N') === 7 && $back > 0) continue; // quiet Sundays
    foreach (range(0, $back % 2) as $n) {
        $start = $day->setTime(10 + 3 * $n, 0); if ($start > $now) $start = $now->modify('-2 hours')->setTimezone(app_tz());
        $dur = (45 + (($back * 37 + $n * 53) % 100)) * 60;
        [$tid, $pid] = $t[$names[($back + $n * 3) % 9]];
        Db::q('INSERT INTO task_time_logs (user_id, project_id, task_id, started_at, ended_at, duration_seconds, work_description) VALUES (?,?,?,?,?,?,?)',
            [$uid, $pid, $tid, $start->setTimezone($utc)->format($f), $start->modify("+$dur seconds")->setTimezone($utc)->format($f), $dur, $descs[($back + $n) % 5]]);
    }
}
// attendance today: a finished morning session with a 30 min break (inside today's local day)
$in = max($today->setTimezone($utc), $now->modify('-5 hours')); $out = $now->modify('-1 hour'); if ($out <= $in) $out = $in->modify('+30 minutes');
Db::q("INSERT INTO attendance_sessions (user_id, sign_in_at, sign_out_at, source) VALUES (?,?,?, 'demo')", [$uid, $in->format($f), $out->format($f)]);
$sid = (int)Db::pdo()->lastInsertId(); $bs = $in->modify('+1 hour');
if ($bs->modify('+30 minutes') < $out) Db::q('INSERT INTO attendance_breaks (session_id, start_at, end_at) VALUES (?,?,?)', [$sid, $bs->format($f), $bs->modify('+30 minutes')->format($f)]);
echo "Demo data added for $email. Open /dashboard. Remove later with: php database/seed_demo.php $email --clear\n";
