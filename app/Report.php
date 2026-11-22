<?php
/**
 * Reports page: live-filtered summary of SAVED time (task_time_logs) and attendance sessions. Read-only.
 *  - Whose data: same rule as Time Tracking (Perm scope: all = everyone, team = self + direct reports, own = self).
 *  - All numbers on the page (cards, charts, counter, table) come from ONE filtered list, so they always agree.
 *  - Dates are workspace-timezone calendar days; times are stored in UTC.
 *  - Work location of a log = the location of that person's attendance day: only Office -> office, only Remote -> remote, both -> hybrid
 *    (a day without a sign-in counts as Office).
 */
final class Report {
    const PER_PAGE = 10;
    const MAX_ROWS = 20000;
    const LOCATIONS = ['office' => 'Office', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];

    private static function validDate(?string $s): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$s);
        return $d && $d->format('Y-m-d') === $s ? $s : null;
    }

    /** The three quick presets as [key => [label, from, to]] (week uses the workspace's "week starts on"). */
    static function presets(DateTimeZone $tz): array {
        $t = new DateTimeImmutable('today', $tz); $w = Workspace::weekStart($t);
        return ['today' => ['Today', $t->format('Y-m-d'), $t->format('Y-m-d')],
                'week' => ['This Week', $w->format('Y-m-d'), $w->modify('+6 days')->format('Y-m-d')],
                'month' => ['This Month', $t->format('Y-m-01'), $t->format('Y-m-t')]];
    }

    /** Cleans the filters from the address bar; anything invalid falls back to the default (Today). */
    static function cleanFilters(array $q, DateTimeZone $tz, array $people): array {
        $pre = self::presets($tz); $from = self::validDate($q['from'] ?? null) ?? $pre['today'][1]; $to = self::validDate($q['to'] ?? null) ?? $pre['today'][2];
        if ($from > $to) [$from, $to] = [$to, $from];
        if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) $from = (new DateTimeImmutable($to))->modify('-366 days')->format('Y-m-d');
        $member = (int)($q['member'] ?? 0); if (!isset($people[$member]) || count($people) < 2) $member = 0;
        $status = (string)($q['status'] ?? ''); $priority = (string)($q['priority'] ?? ''); $loc = (string)($q['location'] ?? '');
        $active = ''; foreach ($pre as $k => $p) if ($p[1] === $from && $p[2] === $to) { $active = $k; break; }
        return ['from' => $from, 'to' => $to, 'project' => max(0, (int)($q['project'] ?? 0)), 'task' => max(0, (int)($q['task'] ?? 0)), 'member' => $member,
            'q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 100), 'status' => isset(Task::STATUS[$status]) ? $status : '', 'priority' => isset(Project::PRIORITY[$priority]) ? $priority : '',
            'location' => isset(self::LOCATIONS[$loc]) ? $loc : '', 'page' => max(1, (int)($q['page'] ?? 1)), 'default' => [$pre['today'][1], $pre['today'][2]], 'preset' => $active];
    }

    /** "/reports?…" with only the filters that differ from the defaults; $over replaces some. */
    static function path(array $f, array $over = []): string {
        $f = array_merge($f, $over); $q = [];
        if ($f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1]) { $q['from'] = $f['from']; $q['to'] = $f['to']; }
        foreach (['project', 'task', 'member'] as $k) if ($f[$k]) $q[$k] = $f[$k];
        foreach (['q', 'status', 'priority', 'location'] as $k) if ($f[$k] !== '') $q[$k] = $f[$k];
        if ((int)$f['page'] > 1) $q['page'] = (int)$f['page'];
        return '/reports' . ($q ? '?' . http_build_query($q) : '');
    }

    /** True when anything other than the default (today) is chosen. */
    static function filtered(array $f): bool {
        return $f['project'] || $f['task'] || $f['member'] || $f['q'] !== '' || $f['status'] !== '' || $f['priority'] !== '' || $f['location'] !== '' || $f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1];
    }

    private static function bounds(string $from, string $to, DateTimeZone $tz): array {
        $utc = new DateTimeZone('UTC');
        return [(new DateTimeImmutable($from . ' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s'), (new DateTimeImmutable($to . ' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
    }

    private static function day(string $utc, DateTimeZone $tz): string { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d'); }

    /** Attendance sessions of the people in the range, with their breaks. @return array<int,array> */
    private static function sessions(array $ids, array $range, DateTimeZone $tz): array {
        [$a, $b] = self::bounds($range[0], $range[1], $tz); $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')';
        try { $rows = Db::q("SELECT id, user_id, sign_in_at, sign_out_at, work_location FROM attendance_sessions WHERE user_id IN $in AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at LIMIT 5000", array_merge($ids, [$a, $b]))->fetchAll(); }
        catch (Throwable $e) { $rows = Db::q("SELECT id, user_id, sign_in_at, sign_out_at, 'office' work_location FROM attendance_sessions WHERE user_id IN $in AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at LIMIT 5000", array_merge($ids, [$a, $b]))->fetchAll(); }
        return $rows;
    }

    /** The whole result for one filter set: cards, charts and the table page. */
    static function build(array $ws, array $people, array $f, DateTimeZone $tz, bool $all = false): array {
        $wid = (int)$ws['id']; $ids = $f['member'] ? [$f['member']] : array_map('intval', array_keys($people));
        [$a, $b] = self::bounds($f['from'], $f['to'], $tz);
        $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $where = "p.workspace_id = ? AND l.user_id IN $in AND l.started_at >= ? AND l.started_at < ?"; $args = array_merge([$wid], $ids, [$a, $b]);
        if ($f['project']) { $where .= ' AND l.project_id = ?'; $args[] = $f['project']; }
        if ($f['task']) { $where .= ' AND l.task_id = ?'; $args[] = $f['task']; }
        if ($f['status'] !== '') { $where .= ' AND t.status = ?'; $args[] = $f['status']; }
        if ($f['priority'] !== '') { $where .= ' AND t.priority = ?'; $args[] = $f['priority']; }
        if ($f['q'] !== '') { $where .= " AND l.work_description LIKE ? ESCAPE '!'"; $args[] = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'; }
        $rows = Db::q("SELECT l.id, l.user_id, l.task_id, l.project_id, l.started_at, l.duration_seconds, l.work_description, t.title task_title, t.status, t.priority, p.name project_name, u.name `member`
            FROM task_time_logs l JOIN projects p ON p.id = l.project_id JOIN tasks t ON t.id = l.task_id JOIN users u ON u.id = l.user_id WHERE $where
            ORDER BY l.started_at DESC, l.id DESC LIMIT " . (self::MAX_ROWS + 1), $args)->fetchAll();
        $truncated = count($rows) > self::MAX_ROWS; if ($truncated) array_pop($rows);

        // attendance days (and their location) of the same people and range
        $sess = self::sessions($ids, [$f['from'], $f['to']], $tz); $br = []; $days = [];
        foreach ($sess as $s) { $k = $s['user_id'] . '|' . self::day($s['sign_in_at'], $tz); $days[$k][$s['work_location'] === 'remote' ? 'remote' : 'office'] = true; }
        $locOf = fn(string $key): string => !isset($days[$key]) ? 'office' : (count($days[$key]) > 1 ? 'hybrid' : array_key_first($days[$key]));
        if ($f['location'] !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $locOf($r['user_id'] . '|' . self::day($r['started_at'], $tz)) === $f['location']));
            $days = array_filter($days, fn($v, $k) => $locOf($k) === $f['location'], ARRAY_FILTER_USE_BOTH);
            $sess = array_values(array_filter($sess, fn($s) => isset($days[$s['user_id'] . '|' . self::day($s['sign_in_at'], $tz)])));
        }
        $net = 0;
        if ($sess) {
            $sid = array_map(fn($s) => (int)$s['id'], $sess);
            foreach (Db::q('SELECT session_id, start_at, end_at FROM attendance_breaks WHERE session_id IN (' . implode(',', array_fill(0, count($sid), '?')) . ')', $sid)->fetchAll() as $x) $br[(int)$x['session_id']][] = $x;
            $now = time(); foreach ($sess as $s) $net += Attendance::calc($s, $br[(int)$s['id']] ?? [], $now)['net'];
        }

        // totals, per-day and per-project sums from the same rows
        $sec = 0; $tasks = []; $perDay = []; $perProject = [];
        foreach ($rows as &$r) {
            $d = (int)$r['duration_seconds']; $sec += $d; $tasks[$r['task_id']] = 1; $day = self::day($r['started_at'], $tz); $r['day'] = $day;
            $perDay[$day] = ($perDay[$day] ?? 0) + $d; $perProject[$r['project_id']] = [$r['project_name'], ($perProject[$r['project_id']][1] ?? 0) + $d];
        } unset($r);
        uasort($perProject, fn($x, $y) => $y[1] <=> $x[1]);
        $total = count($rows); $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        return ['items' => $all ? $rows : array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), 'dayKeys' => array_keys($days), 'total' => $total, 'pages' => $pages, 'page' => $page, 'seconds' => $sec, 'tasks' => count($tasks),
            'attDays' => count($days), 'net' => $net, 'bars' => self::bars($f, $perDay, $tz), 'projects' => array_values($perProject), 'truncated' => $truncated];
    }

    /** Bars for "Time by day": a range of up to 7 days shows the whole week it falls in; up to 62 days one bar per day; longer ranges one bar per week.
     *  @return array{bars: array<int,array{label:string,title:string,seconds:int}>, max:int} */
    private static function bars(array $f, array $perDay, DateTimeZone $tz): array {
        $from = new DateTimeImmutable($f['from']); $to = new DateTimeImmutable($f['to']); $span = $from->diff($to)->days + 1; $bars = [];
        if ($span <= 7) { $from = Workspace::weekStart($from); $to = $from->modify('+6 days'); }
        if ($span <= 62) {
            for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) { $k = $d->format('Y-m-d'); $bars[] = ['label' => $span <= 7 ? $d->format('D') : $d->format('j'), 'title' => $d->format('D, ' . Account::df()), 'seconds' => $perDay[$k] ?? 0]; }
        } else {
            for ($d = Workspace::weekStart($from); $d <= $to; $d = $d->modify('+7 days')) {
                $s = 0; for ($i = 0; $i < 7; $i++) $s += $perDay[$d->modify("+$i days")->format('Y-m-d')] ?? 0;
                $bars[] = ['label' => $d->format('M j'), 'title' => 'Week of ' . $d->format(Account::df()), 'seconds' => $s];
            }
        }
        return ['bars' => $bars, 'max' => max(array_column($bars, 'seconds') ?: [0])];
    }

    /** Headline under the preset tabs: "Showing today's saved time entries" / "Showing Oct 1 – Oct 31". */
    static function caption(array $f, DateTimeZone $tz): string {
        $pre = self::presets($tz); $p = $f['preset'];
        if ($p === 'today') return "Showing today's saved time entries";
        if ($p === 'week') return "Showing this week's saved time entries";
        if ($p === 'month') return "Showing this month's saved time entries";
        return 'Showing saved time entries from ' . Ui::date($f['from']) . ($f['from'] === $f['to'] ? '' : ' to ' . Ui::date($f['to']));
    }
}
