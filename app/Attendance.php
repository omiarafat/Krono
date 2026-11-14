<?php
/**
 * Attendance pages: the READ-ONLY history of attendance sessions (sign in / breaks / sign out are done in Krono Desktop).
 *  - Whose sessions: same people rule as Time Tracking (Owner / Admin = everyone, Manager = self + direct reports, others = self).
 *  - Gross time = sign out - sign in (an open session counts until now, server time).
 *  - Net work time = gross - the sum of break durations, always from exact seconds (an open break counts until now / sign out).
 *  - Each break is shown as "2m 40s"; the TOTAL break time is rounded to the nearest minute (fmt_break / fmt_break_total).
 *  - Times are stored in UTC and shown in the workspace timezone. A session belongs to the day it was signed in on.
 */
final class Attendance {
    const PER_PAGE = 10;
    const MAX_ROWS = 500;

    private static function ts(?string $utc): ?int { return $utc ? (int)strtotime($utc . ' UTC') : null; }

    /** Default range = this month so far, in the workspace timezone. @return array{0:string,1:string} */
    static function defaultRange(DateTimeZone $tz): array {
        $t = new DateTimeImmutable('today', $tz);
        return [$t->format('Y-m-01'), $t->format('Y-m-d')];
    }

    /** Cleans the address-bar filters. "member": a person's id (default = the viewer), or 0 for everyone (only when the viewer may see several people). */
    static function cleanFilters(array $q, DateTimeZone $tz, array $people, int $uid): array {
        [$df, $dt] = self::defaultRange($tz);
        $ok = fn($s) => ($d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$s)) && $d->format('Y-m-d') === $s ? $s : null;
        $from = $ok($q['from'] ?? null) ?? $df; $to = $ok($q['to'] ?? null) ?? $dt;
        if ($to > $dt) $to = $dt; if ($from > $to) $from = $to;
        $m = (string)($q['member'] ?? ''); $member = $uid;
        if ($m === 'everyone' && count($people) > 1) $member = 0;
        elseif (ctype_digit($m) && isset($people[(int)$m])) $member = (int)$m;
        return ['from' => $from, 'to' => $to, 'member' => $member, 'q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 40), 'page' => max(1, (int)($q['page'] ?? 1)), 'default' => [$df, $dt], 'uid' => $uid];
    }

    static function path(array $f, array $over = []): string {
        $f = array_merge($f, $over); $q = [];
        if ($f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1]) { $q['from'] = $f['from']; $q['to'] = $f['to']; }
        if ($f['member'] === 0) $q['member'] = 'everyone'; elseif ($f['member'] !== $f['uid']) $q['member'] = $f['member'];
        if ($f['q'] !== '') $q['q'] = $f['q'];
        if ((int)$f['page'] > 1) $q['page'] = (int)$f['page'];
        return '/attendance' . ($q ? '?' . http_build_query($q) : '');
    }

    static function filtered(array $f): bool { return $f['member'] !== $f['uid'] || $f['q'] !== '' || $f['from'] !== $f['default'][0] || $f['to'] !== $f['default'][1]; }

    private static function in(array $ids): string { return '(' . implode(',', array_fill(0, count($ids), '?')) . ')'; }

    static function hasAny(array $people): bool {
        $ids = array_map('intval', array_keys($people));
        return (bool)Db::q('SELECT 1 FROM attendance_sessions WHERE user_id IN ' . self::in($ids) . ' LIMIT 1', $ids)->fetchColumn();
    }

    /** Gross / break / net seconds of one session. $breaks = its attendance_breaks rows. @return array{gross:int,break:int,net:int,active:bool,count:int} */
    static function calc(array $s, array $breaks, ?int $now = null): array {
        $now ??= time(); $in = self::ts($s['sign_in_at']); $out = self::ts($s['sign_out_at']); $end = $out ?? $now;
        $gross = max(0, $end - $in); $brk = 0;
        foreach ($breaks as $b) {
            $a = max($in, self::ts($b['start_at'])); $z = min($end, self::ts($b['end_at']) ?? $end);
            if ($z > $a) $brk += $z - $a;
        }
        return ['gross' => $gross, 'break' => min($brk, $gross), 'net' => max(0, $gross - $brk), 'active' => $out === null, 'count' => count($breaks)];
    }

    /** Breaks of the given sessions: session id => rows (oldest first). */
    private static function breaksOf(array $sessionIds): array {
        if (!$sessionIds) return [];
        $out = [];
        foreach (Db::q('SELECT id, session_id, start_at, end_at FROM attendance_breaks WHERE session_id IN ' . self::in($sessionIds) . ' ORDER BY start_at, id', $sessionIds)->fetchAll() as $b) $out[(int)$b['session_id']][] = $b;
        return $out;
    }

    /** One page of sessions, newest first. @return array{items:array,total:int,pages:int,page:int} */
    static function listing(array $people, array $f, DateTimeZone $tz): array {
        $utc = new DateTimeZone('UTC');
        $a = (new DateTimeImmutable($f['from'] . ' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s');
        $b = (new DateTimeImmutable($f['to'] . ' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        $ids = $f['member'] ? [$f['member']] : array_map('intval', array_keys($people));
        $rows = Db::q('SELECT s.id, s.user_id, s.sign_in_at, s.sign_out_at, s.auto_closed, u.name member FROM attendance_sessions s JOIN users u ON u.id = s.user_id
            WHERE s.user_id IN ' . self::in($ids) . ' AND s.sign_in_at >= ? AND s.sign_in_at < ? ORDER BY s.sign_in_at DESC, s.id DESC LIMIT ' . self::MAX_ROWS, array_merge($ids, [$a, $b]))->fetchAll();
        $q = mb_strtolower($f['q']);
        if ($q !== '') $rows = array_values(array_filter($rows, function ($r) use ($q, $tz) {
            $d = (new DateTimeImmutable($r['sign_in_at'], new DateTimeZone('UTC')))->setTimezone($tz);
            return str_contains(mb_strtolower($d->format('M d, Y')), $q) || str_contains($d->format('Y-m-d'), $q) || str_contains(mb_strtolower($d->format('l')), $q);
        }));
        $total = count($rows); $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        $rows = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $br = self::breaksOf(array_map(fn($r) => (int)$r['id'], $rows)); $now = time();
        foreach ($rows as &$r) $r += self::calc($r, $br[(int)$r['id']] ?? [], $now);
        return ['items' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    // ---------- details ----------

    /** One session the viewer may see (its owner is in $people), or null. */
    static function find(int $id, array $people): ?array {
        $r = Db::q('SELECT s.id, s.user_id, s.sign_in_at, s.sign_out_at, s.auto_closed FROM attendance_sessions s WHERE s.id = ?', [$id])->fetch();
        return $r && isset($people[(int)$r['user_id']]) ? $r : null;
    }

    /** The first session the person signed in on that local day (or null). */
    static function onDate(int $uid, string $ymd, DateTimeZone $tz): ?array {
        $utc = new DateTimeZone('UTC');
        $a = (new DateTimeImmutable($ymd . ' 00:00:00', $tz))->setTimezone($utc)->format('Y-m-d H:i:s');
        $b = (new DateTimeImmutable($ymd . ' 00:00:00', $tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        return Db::q('SELECT id, user_id, sign_in_at, sign_out_at, auto_closed FROM attendance_sessions WHERE user_id = ? AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at, id LIMIT 1', [$uid, $a, $b])->fetch() ?: null;
    }

    /** Id of the previous / next session of the same person (null when none). */
    static function neighbour(array $s, string $dir): ?int {
        $prev = $dir === 'prev';
        $r = Db::q('SELECT id FROM attendance_sessions WHERE user_id = ? AND (sign_in_at ' . ($prev ? '<' : '>') . ' ? OR (sign_in_at = ? AND id ' . ($prev ? '<' : '>') . ' ?)) ORDER BY sign_in_at ' . ($prev ? 'DESC' : 'ASC') . ', id ' . ($prev ? 'DESC' : 'ASC') . ' LIMIT 1',
            [(int)$s['user_id'], $s['sign_in_at'], $s['sign_in_at'], (int)$s['id']])->fetchColumn();
        return $r ? (int)$r : null;
    }

    static function breaks(int $sessionId): array { return self::breaksOf([$sessionId])[$sessionId] ?? []; }

    /** Employee card data: name, email, workspace role, manager name (or null). */
    static function employee(int $uid, array $ws): array {
        $r = Db::q('SELECT u.name, u.email, m.role, mg.name manager FROM users u LEFT JOIN workspace_members m ON m.user_id = u.id AND m.workspace_id = ? LEFT JOIN users mg ON mg.id = m.manager_id WHERE u.id = ?', [(int)$ws['id'], $uid])->fetch();
        return $r ?: ['name' => '', 'email' => '', 'role' => null, 'manager' => null];
    }

    // ---------- display helpers ----------

    private static function local(string $utc, DateTimeZone $tz): DateTimeImmutable { return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz); }
    static function clock(string $utc, DateTimeZone $tz): string { return self::local($utc, $tz)->format('h:i A'); }
    static function dateTime(string $utc, DateTimeZone $tz): string { return self::local($utc, $tz)->format('M d, Y, h:i A'); }
    /** Local date "Y-m-d" of a UTC time. */
    static function ymd(string $utc, DateTimeZone $tz): string { return self::local($utc, $tz)->format('Y-m-d'); }
    /** Sign-out cell: the clock time, with the date when it is not the sign-in day (overnight). */
    static function outLabel(array $s, DateTimeZone $tz): string {
        if (!$s['sign_out_at']) return '';
        return self::ymd($s['sign_out_at'], $tz) === self::ymd($s['sign_in_at'], $tz) ? self::clock($s['sign_out_at'], $tz) : self::local($s['sign_out_at'], $tz)->format('M d, h:i A');
    }
    /** "(GMT+6) Dhaka" */
    static function tzLabel(DateTimeZone $tz): string {
        $o = $tz->getOffset(new DateTimeImmutable('now')); $h = intdiv(abs($o), 3600); $m = intdiv(abs($o) % 3600, 60);
        $city = str_replace('_', ' ', (string)substr(strrchr('/' . $tz->getName(), '/'), 1));
        return '(GMT' . ($o < 0 ? '-' : '+') . $h . ($m ? ':' . sprintf('%02d', $m) : '') . ') ' . $city;
    }
}
