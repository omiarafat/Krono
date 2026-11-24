<?php
/**
 * Dashboard numbers. Every figure comes from the database for the signed-in user; nothing is made up.
 *
 * Scope: the user's workspace. Own projects and own saved time (manager / team scope comes with the Teams stage).
 *  - "my projects" = in my workspace, not archived, AND (created by me OR I lead it OR I am a member)
 *  - Active project  = status planning or in_progress
 *  - Open task       = a task ASSIGNED TO ME (in my projects) that is not completed
 *  - Progress %      = completed tasks / all tasks of the project (no tasks = 0%)
 *  - Saved time      = my task_time_logs (the desktop app writes them on Stop + Save)
 *  - Attendance net  = (sign out or now) - sign in - breaks, for sessions that signed in today
 * "Today / week / month" use the workspace timezone (workspaces.timezone, default Asia/Dhaka); the week starts on the day set in Workspace Settings (Monday by default).
 */
final class Dashboard {
    const ACTIVE = "'planning','in_progress'";

    private static function utc(DateTimeImmutable $d): string { return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private static function local(string $utc, DateTimeZone $tz): DateTimeImmutable { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz); }
    /** SQL for "projects I can see" + its bound values. */
    private static function mine(int $uid, int $wsId): array {
        return ['p.workspace_id = ? AND p.archived_at IS NULL AND (p.created_by = ? OR p.leader_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))', [$wsId, $uid, $uid, $uid]];
    }

    /** The desktop app counts as installed once it has signed in with this account (any login token, even a revoked one). */
    static function appInstalled(int $uid): bool { return (int)Db::q('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$uid])->fetchColumn() > 0; }

    static function data(int $uid, string $week = 'this', ?DateTimeImmutable $now = null): array {
        $ws = Workspace::ensureFor($uid); $tz = Workspace::tz($ws);
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz);
        $today = $now->setTime(0, 0);
        $weekStart = Workspace::weekStart($today);
        if ($week === 'last') $weekStart = $weekStart->modify('-7 days');
        $weekEnd = $weekStart->modify('+7 days');                      // exclusive
        $realWeekStart = Workspace::weekStart($today);
        $monthStart = $today->modify('first day of this month');
        $nextMonth = $monthStart->modify('+1 month');
        $nowUtc = self::utc($now);
        [$vis, $visP] = self::mine($uid, (int)$ws['id']);

        // 1 + 2: cards
        $r = Db::q("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN p.created_at >= ? THEN 1 ELSE 0 END), 0) n FROM projects p WHERE $vis AND p.status IN (" . self::ACTIVE . ')', array_merge([self::utc($monthStart)], $visP))->fetch();
        $projects = ['count' => (int)$r['c'], 'new' => (int)$r['n']];
        $r = Db::q("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN t.due_date >= ? AND t.due_date < ? THEN 1 ELSE 0 END), 0) d
                    FROM tasks t JOIN projects p ON p.id = t.project_id WHERE $vis AND t.status <> 'completed' AND t.assignee_id = ?",
            array_merge([$realWeekStart->format('Y-m-d'), $realWeekStart->modify('+7 days')->format('Y-m-d')], $visP, [$uid]))->fetch();
        $tasks = ['count' => (int)$r['c'], 'due' => (int)$r['d']];

        // 3: saved time this month
        $saved = (int)Db::q('SELECT COALESCE(SUM(duration_seconds), 0) FROM task_time_logs WHERE user_id = ? AND started_at >= ? AND started_at < ?', [$uid, self::utc($monthStart), self::utc($nextMonth)])->fetchColumn();

        // 4: attendance net time for sessions that signed in today
        $sessions = Db::q('SELECT id, sign_in_at, sign_out_at FROM attendance_sessions WHERE user_id = ? AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at', [$uid, self::utc($today), self::utc($today->modify('+1 day'))])->fetchAll();
        $net = 0; $working = false;
        foreach ($sessions as $s) {
            $in = strtotime($s['sign_in_at'] . ' UTC'); $out = $s['sign_out_at'] ? strtotime($s['sign_out_at'] . ' UTC') : strtotime($nowUtc . ' UTC');
            if (!$s['sign_out_at']) $working = true;
            $brk = 0;
            foreach (Db::q('SELECT start_at, end_at FROM attendance_breaks WHERE session_id = ?', [$s['id']])->fetchAll() as $b) {
                $bs = strtotime($b['start_at'] . ' UTC'); $be = $b['end_at'] ? strtotime($b['end_at'] . ' UTC') : $out;
                $brk += max(0, min($be, $out) - max($bs, $in));
            }
            $net += max(0, $out - $in - $brk);
        }
        $attendance = ['seconds' => $net, 'has' => (bool)$sessions, 'working' => $working];

        // Weekly chart: Monday..Sunday of the chosen week, bucketed by workspace-timezone day
        $days = []; for ($i = 0; $i < 7; $i++) { $d = $weekStart->modify("+$i days"); $days[$d->format('Y-m-d')] = ['label' => $d->format('D'), 'seconds' => 0]; }
        foreach (Db::q('SELECT started_at, duration_seconds FROM task_time_logs WHERE user_id = ? AND started_at >= ? AND started_at < ?', [$uid, self::utc($weekStart), self::utc($weekEnd)])->fetchAll() as $l) {
            $k = self::local($l['started_at'], $tz)->format('Y-m-d'); if (isset($days[$k])) $days[$k]['seconds'] += (int)$l['duration_seconds'];
        }
        $weekTotal = array_sum(array_column($days, 'seconds'));

        // Project progress: 3 most recently updated active projects
        $progress = [];
        $rows = Db::q("SELECT p.id, p.name,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) total,
                         (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completed') done,
                         (SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l WHERE l.project_id = p.id AND l.user_id = ?) tracked
                       FROM projects p WHERE $vis AND p.status IN (" . self::ACTIVE . ') ORDER BY p.updated_at DESC, p.id DESC LIMIT 3', array_merge([$uid], $visP))->fetchAll();
        foreach ($rows as $p) $progress[] = ['name' => $p['name'], 'tracked' => (int)$p['tracked'], 'percent' => (int)$p['total'] > 0 ? (int)round(100 * (int)$p['done'] / (int)$p['total']) : 0];

        // Recent saved time logs
        $logs = [];
        foreach (Db::q('SELECT l.started_at, l.duration_seconds, l.work_description, p.name pname, t.title ttitle
                        FROM task_time_logs l JOIN projects p ON p.id = l.project_id JOIN tasks t ON t.id = l.task_id
                        WHERE l.user_id = ? ORDER BY l.started_at DESC, l.id DESC LIMIT 5', [$uid])->fetchAll() as $l) {
            $logs[] = ['date' => self::local($l['started_at'], $tz)->format('M j'), 'project' => $l['pname'], 'task' => $l['ttitle'], 'desc' => $l['work_description'], 'seconds' => (int)$l['duration_seconds']];
        }

        return ['projects' => $projects, 'tasks' => $tasks, 'saved' => $saved, 'attendance' => $attendance, 'days' => array_values($days), 'weekTotal' => $weekTotal, 'week' => $week === 'last' ? 'last' : 'this', 'progress' => $progress, 'logs' => $logs];
    }

    /**
     * Team overview for people whose data scope is team or all (Owner / Admin / Manager). Everything is limited to the people TimeLog::people() allows.
     * Cards and lists follow the same permissions as the pages they link to. Null when the scope is "own" (a plain member).
     */
    static function team(int $uid, array $ws, ?DateTimeImmutable $now = null): ?array {
        if (Perm::scope($ws) === 'own') return null;
        $tz = Workspace::tz($ws); $wid = (int)$ws['id'];
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz); $today = $now->setTime(0, 0); $ymd = $today->format('Y-m-d');
        $people = TimeLog::people($uid, $ws); $ids = array_map('intval', array_keys($people));
        $out = ['alone' => count($ids) < 2, 'size' => count($ids), 'canAttend' => Perm::can($ws, 'attendance.view'), 'canTime' => Perm::can($ws, 'time.view'), 'canTasks' => Perm::can($ws, 'tasks.view'),
                'canInvite' => Perm::can($ws, 'team.invite'), 'canTeam' => Perm::can($ws, 'team.view'), 'rows' => [], 'working' => 0, 'absent' => 0, 'onLeave' => 0, 'requests' => 0, 'hours' => [], 'hoursTotal' => 0, 'overdue' => [], 'overdueCount' => 0];
        try { $out['requests'] = array_sum(array_map('count', DaysOff::pendingFor($ws, $uid, $tz))); } catch (Throwable $e) { error_log((string)$e); }
        if ($out['alone']) return $out;
        $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')';

        if ($out['canAttend']) {
            $a = self::utc($today); $b = self::utc($today->modify('+1 day')); $nowUtc = self::utc($now);
            $sess = Db::q("SELECT id, user_id, sign_in_at, sign_out_at FROM attendance_sessions WHERE user_id IN $in AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at", array_merge($ids, [$a, $b]))->fetchAll();
            $br = []; if ($sess) { $sid = array_map(fn($s) => (int)$s['id'], $sess);
                foreach (Db::q('SELECT session_id, start_at, end_at FROM attendance_breaks WHERE session_id IN (' . implode(',', array_fill(0, count($sid), '?')) . ')', $sid)->fetchAll() as $x) $br[(int)$x['session_id']][] = $x; }
            $by = []; foreach ($sess as $s) {
                $c = Attendance::calc($s, $br[(int)$s['id']] ?? [], strtotime($nowUtc . ' UTC')); $u = (int)$s['user_id']; $r = &$by[$u];
                $r['net'] = ($r['net'] ?? 0) + $c['net']; $r['first'] ??= $s['sign_in_at'];
                if ($c['active']) { $r['live'] = true; foreach ($br[(int)$s['id']] ?? [] as $x) if (!$x['end_at']) $r['break'] = true; } unset($r);
            }
            $leave = DaysOff::leaveMap($wid, $ids, $ymd, $ymd);
            foreach ($ids as $id) {
                $off = DaysOff::isOff(DaysOff::listOf(DaysOff::effective($wid, $id)), $today); $r = $by[$id] ?? null; $lv = ($leave[$id][$ymd]['status'] ?? '') === 'approved';
                if ($r && !empty($r['live'])) { $st = !empty($r['break']) ? 'On break' : 'Working'; $out['working']++; }
                elseif ($r) $st = 'Signed out';
                elseif ($lv) { $st = 'Leave'; $out['onLeave']++; }
                elseif ($off) $st = 'Days off';
                else { $st = 'Absent'; $out['absent']++; }
                $out['rows'][] = ['id' => $id, 'name' => $people[$id], 'status' => $st, 'net' => (int)($r['net'] ?? 0), 'since' => $r ? self::local($r['first'], $tz)->format('g:i A') : ''];
            }
            $rank = ['Working' => 0, 'On break' => 1, 'Signed out' => 2, 'Absent' => 3, 'Leave' => 4, 'Days off' => 5]; usort($out['rows'], fn($x, $y) => [$rank[$x['status']], $x['name']] <=> [$rank[$y['status']], $y['name']]);
        }

        if ($out['canTime']) {
            $ws0 = Workspace::weekStart($today); $per = [];
            foreach (Db::q("SELECT l.user_id, SUM(l.duration_seconds) s FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE p.workspace_id = ? AND l.user_id IN $in AND l.started_at >= ? AND l.started_at < ? GROUP BY l.user_id",
                array_merge([$wid], $ids, [self::utc($ws0), self::utc($ws0->modify('+7 days'))]))->fetchAll() as $x) $per[(int)$x['user_id']] = (int)$x['s'];
            arsort($per); foreach (array_slice($per, 0, 6, true) as $id => $sec) $out['hours'][] = ['name' => $people[$id] ?? 'Member', 'seconds' => $sec]; $out['hoursTotal'] = array_sum($per);
        }

        if ($out['canTasks']) {
            $w = "p.workspace_id = ? AND p.archived_at IS NULL AND t.status <> 'completed' AND t.due_date < ? AND t.assignee_id IN $in"; $args = array_merge([$wid, $ymd], $ids);
            $out['overdueCount'] = (int)Db::q("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id = t.project_id WHERE $w", $args)->fetchColumn();
            foreach (Db::q("SELECT t.title, t.due_date, p.name project, u.name assignee FROM tasks t JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.assignee_id WHERE $w ORDER BY t.due_date, t.id LIMIT 5", $args)->fetchAll() as $t)
                $out['overdue'][] = ['title' => $t['title'], 'project' => $t['project'], 'assignee' => $t['assignee'], 'due' => (new DateTimeImmutable($t['due_date']))->format('M j')];
        }
        return $out;
    }
}
