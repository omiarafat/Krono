<?php
/**
 * Dashboard numbers. Every figure comes from the database for the signed-in user; nothing is made up.
 *
 * Scope (until Workspaces / Teams are built): the user's own projects and own saved time.
 *  - "my projects" = not archived AND (created by me OR I am a member)
 *  - Active project  = status planning or in_progress
 *  - Open task       = a task of my projects that is not completed
 *  - Progress %      = completed tasks / all tasks of the project (no tasks = 0%)
 *  - Saved time      = my task_time_logs (the desktop app writes them on Stop + Save)
 *  - Attendance net  = (sign out or now) - sign in - breaks, for sessions that signed in today
 * "Today / week / month" use the workspace timezone (APP_TIMEZONE, default Asia/Dhaka); the week starts on Monday.
 */
final class Dashboard {
    const ACTIVE = "'planning','in_progress'";

    private static function utc(DateTimeImmutable $d): string { return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private static function local(string $utc): DateTimeImmutable { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(app_tz()); }
    /** SQL for "projects I can see" + its two bound values. */
    private static function mine(int $uid): array {
        return ['p.archived_at IS NULL AND (p.created_by = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))', [$uid, $uid]];
    }

    static function data(int $uid, string $week = 'this', ?DateTimeImmutable $now = null): array {
        $tz = app_tz();
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz);
        $today = $now->setTime(0, 0);
        $weekStart = $today->modify('monday this week');
        if ($week === 'last') $weekStart = $weekStart->modify('-7 days');
        $weekEnd = $weekStart->modify('+7 days');                      // exclusive
        $realWeekStart = $today->modify('monday this week');
        $monthStart = $today->modify('first day of this month');
        $nextMonth = $monthStart->modify('+1 month');
        $nowUtc = self::utc($now);
        [$vis, $visP] = self::mine($uid);

        // 1 + 2: cards
        $r = Db::q("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN p.created_at >= ? THEN 1 ELSE 0 END), 0) n FROM projects p WHERE $vis AND p.status IN (" . self::ACTIVE . ')', array_merge([self::utc($monthStart)], $visP))->fetch();
        $projects = ['count' => (int)$r['c'], 'new' => (int)$r['n']];
        $r = Db::q("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN t.due_date >= ? AND t.due_date < ? THEN 1 ELSE 0 END), 0) d
                    FROM tasks t JOIN projects p ON p.id = t.project_id WHERE $vis AND t.status <> 'completed'",
            array_merge([$realWeekStart->format('Y-m-d'), $realWeekStart->modify('+7 days')->format('Y-m-d')], $visP))->fetch();
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
            $k = self::local($l['started_at'])->format('Y-m-d'); if (isset($days[$k])) $days[$k]['seconds'] += (int)$l['duration_seconds'];
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
            $logs[] = ['date' => self::local($l['started_at'])->format('M j'), 'project' => $l['pname'], 'task' => $l['ttitle'], 'desc' => $l['work_description'], 'seconds' => (int)$l['duration_seconds']];
        }

        return ['projects' => $projects, 'tasks' => $tasks, 'saved' => $saved, 'attendance' => $attendance, 'days' => array_values($days), 'weekTotal' => $weekTotal, 'week' => $week === 'last' ? 'last' : 'this', 'progress' => $progress, 'logs' => $logs];
    }
}
