<?php
/**
 * Time Tracking page: the READ-ONLY history of saved time logs (written by the desktop app on Stop + Save).
 * The website never starts, pauses or stops a timer, and logs are never edited or deleted here.
 *  - Whose logs: Owner / Admin = everyone in the workspace; Manager = self + direct reports; Member / Viewer = self only.
 *  - Dates are filtered and shown in the workspace timezone; times are stored in UTC.
 */
final class TimeLog {
    const PER_PAGE = 10;

    /** People whose logs the viewer may see: id => name (self first). */
    static function people(int $uid, array $ws): array {
        $wid = (int)$ws['id'];
        if (Workspace::atLeast($ws['role'], 'admin')) {
            $rows = Db::q("SELECT u.id, u.name FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.status = 'active' ORDER BY u.name, u.id", [$wid])->fetchAll();
        } elseif ($ws['role'] === 'manager') {
            $rows = Db::q("SELECT u.id, u.name FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.status = 'active' AND (m.user_id = ? OR m.manager_id = ?) ORDER BY u.name, u.id", [$wid, $uid, $uid])->fetchAll();
        } else {
            $rows = Db::q('SELECT id, name FROM users WHERE id = ?', [$uid])->fetchAll();
        }
        $out = [];
        foreach ($rows as $r) $out[(int)$r['id']] = $r['name'];
        if (!isset($out[$uid])) $out = [$uid => (string)Db::q('SELECT name FROM users WHERE id = ?', [$uid])->fetchColumn()] + $out;
        return $out;
    }

    private static function validDate(?string $s): ?string {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$s);
        return $d && $d->format('Y-m-d') === $s ? $s : null;
    }

    /** Default range = last 30 days (today included), in the workspace timezone. @return array{0:string,1:string} [from, to] */
    static function defaultRange(DateTimeZone $tz): array {
        $today = new DateTimeImmutable('today', $tz);
        return [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')];
    }

    /** Cleans the filters from the address bar. Anything invalid falls back to the default. */
    static function cleanFilters(array $q, DateTimeZone $tz, array $people): array {
        [$df, $dt] = self::defaultRange($tz);
        $from = self::validDate($q['from'] ?? null) ?? $df; $to = self::validDate($q['to'] ?? null) ?? $dt;
        $today = $dt; if ($to > $today) $to = $today; if ($from > $to) $from = $to;
        $member = (int)($q['member'] ?? 0); if (!isset($people[$member]) || count($people) < 2) $member = 0;
        return ['from' => $from, 'to' => $to, 'project' => max(0, (int)($q['project'] ?? 0)), 'task' => max(0, (int)($q['task'] ?? 0)), 'member' => $member,
            'q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 100), 'page' => max(1, (int)($q['page'] ?? 1)), 'default' => [$df, $dt]];
    }

    /** "/time-tracking?from=…": only the filters that differ from the defaults. $over replaces some of them. */
    static function path(array $f, array $over = []): string {
        $f = array_merge($f, $over); $q = [];
        if ($f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1]) { $q['from'] = $f['from']; $q['to'] = $f['to']; }
        foreach (['project', 'task', 'member'] as $k) if ($f[$k]) $q[$k] = $f[$k];
        if ($f['q'] !== '') $q['q'] = $f['q'];
        if ((int)$f['page'] > 1) $q['page'] = (int)$f['page'];
        return '/time-tracking' . ($q ? '?' . http_build_query($q) : '');
    }

    /** True when something other than the default range is chosen (decides "No results" vs the default list). */
    static function filtered(array $f): bool { return $f['project'] || $f['task'] || $f['member'] || $f['q'] !== '' || $f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1]; }

    /** Local date range -> UTC bounds [start, end) for started_at. */
    private static function bounds(string $from, string $to, DateTimeZone $tz): array {
        $utc = new DateTimeZone('UTC');
        $a = (new DateTimeImmutable($from . ' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s');
        $b = (new DateTimeImmutable($to . ' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        return [$a, $b];
    }

    /** SQL + values for "logs this person may see in this workspace" (alias l, p). */
    private static function scope(array $ws, array $people): array {
        $ids = array_map('intval', array_keys($people));
        return ['p.workspace_id = ? AND l.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([(int)$ws['id']], $ids)];
    }

    /** Does the person have ANY saved log at all (no filters)? Decides between the list and the "Track with Krono Desktop" empty state. */
    static function hasAny(array $ws, array $people): bool {
        [$sc, $v] = self::scope($ws, $people);
        return (bool)Db::q("SELECT 1 FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE $sc LIMIT 1", $v)->fetchColumn();
    }

    /** Projects and tasks that have saved logs (for the filters): [projects id => [name, logs], tasks id => [title, project_id]]. */
    static function options(array $ws, array $people): array {
        [$sc, $v] = self::scope($ws, $people);
        $pr = $tk = [];
        foreach (Db::q("SELECT p.id, p.name, COUNT(*) n FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE $sc GROUP BY p.id, p.name ORDER BY p.name, p.id", $v)->fetchAll() as $r) $pr[(int)$r['id']] = ['name' => $r['name'], 'logs' => (int)$r['n']];
        foreach (Db::q("SELECT t.id, t.title, t.project_id FROM tasks t WHERE t.id IN (SELECT l.task_id FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE $sc) ORDER BY t.title, t.id LIMIT 500", $v)->fetchAll() as $r) $tk[(int)$r['id']] = ['title' => $r['title'], 'project' => (int)$r['project_id']];
        return ['projects' => $pr, 'tasks' => $tk];
    }

    /** One page of logs, newest first. @return array{items:array,total:int,seconds:int,pages:int,page:int} */
    static function listing(array $ws, array $people, array $f, DateTimeZone $tz): array {
        [$sc, $args] = self::scope($ws, $people); [$a, $b] = self::bounds($f['from'], $f['to'], $tz);
        $where = "$sc AND l.started_at >= ? AND l.started_at < ?"; array_push($args, $a, $b);
        if ($f['project']) { $where .= ' AND l.project_id = ?'; $args[] = $f['project']; }
        if ($f['task']) { $where .= ' AND l.task_id = ?'; $args[] = $f['task']; }
        if ($f['member']) { $where .= ' AND l.user_id = ?'; $args[] = $f['member']; }
        if ($f['q'] !== '') { $where .= " AND l.work_description LIKE ? ESCAPE '!'"; $args[] = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'; }
        $from = 'FROM task_time_logs l JOIN projects p ON p.id = l.project_id JOIN tasks t ON t.id = l.task_id JOIN users u ON u.id = l.user_id';
        $c = Db::q("SELECT COUNT(*) n, COALESCE(SUM(l.duration_seconds), 0) s $from WHERE $where", $args)->fetch();
        $total = (int)$c['n']; $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        $rows = Db::q("SELECT l.id, l.user_id, l.task_id, l.project_id, l.started_at, l.duration_seconds, l.work_description, t.title task_title, p.name project_name, u.name member
            $from WHERE $where ORDER BY l.started_at DESC, l.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $args)->fetchAll();
        return ['items' => $rows, 'total' => $total, 'seconds' => (int)$c['s'], 'pages' => $pages, 'page' => $page];
    }

    /** "Oct 20" for a UTC DATETIME, in the workspace timezone (year added when it is not this year). */
    static function dateLabel(string $utc, DateTimeZone $tz): string {
        $t = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz);
        return $t->format($t->format('Y') === (new DateTimeImmutable('now', $tz))->format('Y') ? 'M d' : 'M d, Y');
    }
}
