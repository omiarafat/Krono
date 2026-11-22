<?php
/** Workspace Settings (Owner / Admin): General defaults, role overview, e-mail sender and the audit log. Every method works on ONE workspace id. */
final class WsSettings {
    const PAGE = 10, RANGES = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
    const LANGUAGES = ['en' => 'English'], TIME_FORMATS = ['12' => '12-hour (AM/PM)', '24' => '24-hour'];
    const ACTIONS = [
        'team_created' => 'Team created', 'team_renamed' => 'Team renamed', 'team_deleted' => 'Team deleted', 'team_member_added' => 'Added to team', 'team_member_removed' => 'Removed from team', 'team_leader_set' => 'Team Leader set', 'team_leader_removed' => 'Team Leader removed', 'invite_sent' => 'Member invited', 'invite_cancelled' => 'Invitation cancelled', 'invite_resent' => 'Invitation resent', 'invite_accepted' => 'Invitation accepted',
        'role_changed' => 'Role updated', 'manager_changed' => 'Reports-to changed', 'reactivated' => 'Member reactivated', 'deactivated' => 'Member deactivated',
        'email_change_requested' => 'Email change requested', 'email_changed' => 'Email changed', 'password_changed' => 'Password changed',
        'workspace_updated' => 'Workspace settings updated', 'role_created' => 'Role created', 'role_updated' => 'Role updated', 'role_deleted' => 'Role deleted',
        'workspace_created' => 'Workspace created', 'workspace_upgraded' => 'Workspace upgraded to company', 'daysoff_requested' => 'Days-off change requested', 'leave_requested' => 'Leave requested', 'request_approved' => 'Request approved', 'request_rejected' => 'Request rejected',
    ];

    // ---------- General ----------

    static function code(int $id): string { return 'KRW-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }

    /** The plan of one workspace: key, label, state. Billing belongs to the workspace, never to a person. Only "free" exists for now. */
    static function plan(int $wid): array {
        try { $k = (string)Db::q('SELECT plan FROM workspaces WHERE id = ?', [$wid])->fetchColumn(); } catch (Throwable $e) { $k = 'free'; }
        return ['key' => 'free', 'label' => 'Free', 'stored' => $k === '' ? 'free' : $k, 'state' => 'Active'];
    }

    static function general(int $wid): array {
        $r = Db::q('SELECT id, name, timezone, created_at FROM workspaces WHERE id = ?', [$wid])->fetch() ?: [];
        $r += Workspace::prefs($wid);
        $r['days_off'] = DaysOff::workspaceCsv($wid);
        $r['owner'] = (string)Db::q("SELECT u.name FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.role = 'owner' ORDER BY m.created_at LIMIT 1", [$wid])->fetchColumn();
        return $r;
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [errors, clean values] */
    static function checkGeneral(array $p, bool $withDays = false): array {
        $err = []; $c = [
            'name' => trim(preg_replace('/\s+/', ' ', (string)($p['name'] ?? ''))), 'timezone' => (string)($p['timezone'] ?? ''), 'week_start' => (string)($p['week_start'] ?? ''),
            'date_format' => (string)($p['date_format'] ?? ''), 'time_format' => (string)($p['time_format'] ?? ''), 'language' => (string)($p['language'] ?? 'en'),
        ];
        if ($withDays) $c['days_off'] = DaysOff::csv((array)($p['days_off'] ?? []));
        if ($c['name'] === '' || mb_strlen($c['name']) > 120) $err['name'] = 'Enter a workspace name (up to 120 characters).';
        if (!in_array($c['timezone'], DateTimeZone::listIdentifiers(), true)) $err['timezone'] = 'Choose a timezone from the list.';
        if (!isset(Workspace::WEEK_DAYS[$c['week_start']])) $err['week_start'] = 'Choose the first day of the week.';
        if (!isset(Account::DATE_FORMATS[$c['date_format']])) $err['date_format'] = 'Choose a date format.';
        if (!isset(self::TIME_FORMATS[$c['time_format']])) $err['time_format'] = 'Choose a time format.';
        if (!isset(self::LANGUAGES[$c['language']])) $err['language'] = 'Choose a language.';
        return [$err, $c];
    }

    static function saveGeneral(int $wid, int $actor, array $c): void {
        $old = self::general($wid); $names = ['name' => 'Name', 'timezone' => 'Timezone', 'week_start' => 'Week start', 'date_format' => 'Date format', 'time_format' => 'Time format', 'language' => 'Language', 'days_off' => 'Weekly days off'];
        $changed = array_keys(array_filter($names, fn($l, $k) => array_key_exists($k, $c) && (string)$old[$k] !== $c[$k], ARRAY_FILTER_USE_BOTH));
        if (!$changed) return;
        Db::q('UPDATE workspaces SET name = ?, timezone = ?, week_start = ?, date_format = ?, time_format = ?, language = ? WHERE id = ?', [$c['name'], $c['timezone'], $c['week_start'], $c['date_format'], $c['time_format'], $c['language'], $wid]);
        if (isset($c['days_off']) && in_array('days_off', $changed, true)) DaysOff::setWorkspace($wid, $c['days_off']);
        Team::audit($wid, $actor, null, 'workspace_updated', implode(', ', array_map(fn($k) => $names[$k], $changed)) . ' changed');
    }

    // ---------- Roles ----------

    /** role => number of active members */
    static function roleCounts(int $wid): array {
        $o = array_fill_keys(array_keys(Team::ROLE_LABEL), 0);
        foreach (Db::q("SELECT role, COUNT(*) n FROM workspace_members WHERE workspace_id = ? AND status = 'active' AND custom_role_id IS NULL GROUP BY role", [$wid])->fetchAll() as $r) $o[$r['role']] = (int)$r['n'];
        return $o;
    }

    // ---------- Audit log ----------

    static function auditFilters(array $q): array {
        $range = (string)($q['range'] ?? '30'); $res = (string)($q['result'] ?? '');
        return ['range' => isset(self::RANGES[$range]) ? $range : '30', 'actor' => max(0, (int)($q['actor'] ?? 0)), 'action' => isset(self::ACTIONS[(string)($q['action'] ?? '')]) ? (string)$q['action'] : '',
                'result' => in_array($res, ['success', 'failed'], true) ? $res : '', 'page' => max(1, (int)($q['page'] ?? 1))];
    }
    static function auditPath(array $f, array $o = []): string {
        $f = $o + $f; $p = [];
        if ($f['range'] !== '30') $p['range'] = $f['range']; if ($f['actor']) $p['actor'] = $f['actor']; if ($f['action'] !== '') $p['action'] = $f['action']; if ($f['result'] !== '') $p['result'] = $f['result']; if ($f['page'] > 1) $p['page'] = $f['page'];
        return '/settings/workspace/audit' . ($p ? '?' . http_build_query($p) : '');
    }
    private static function where(int $wid, array $f): array {
        $w = ['a.workspace_id = ?']; $a = [$wid];
        if ($f['range'] !== 'all') { $w[] = 'a.created_at >= ?'; $a[] = gmdate('Y-m-d H:i:s', time() - (int)$f['range'] * 86400); }
        if ($f['actor']) { $w[] = 'a.actor_id = ?'; $a[] = $f['actor']; }
        if ($f['action'] !== '') { $w[] = 'a.action = ?'; $a[] = $f['action']; }
        if ($f['result'] !== '') { $w[] = 'a.result = ?'; $a[] = $f['result']; }
        return [implode(' AND ', $w), $a];
    }
    private const SEL = 'SELECT a.id, a.action, a.detail, a.result, a.created_at, ua.name actor, ut.name target_name FROM audit_log a LEFT JOIN users ua ON ua.id = a.actor_id LEFT JOIN users ut ON ut.id = a.target_user_id';

    static function auditList(int $wid, array $f): array {
        [$w, $a] = self::where($wid, $f);
        $total = (int)Db::q("SELECT COUNT(*) FROM audit_log a WHERE $w", $a)->fetchColumn(); $pages = max(1, (int)ceil($total / self::PAGE)); $pg = min($f['page'], $pages);
        $items = Db::q(self::SEL . " WHERE $w ORDER BY a.created_at DESC, a.id DESC LIMIT " . self::PAGE . ' OFFSET ' . (($pg - 1) * self::PAGE), $a)->fetchAll();
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $pg];
    }
    static function auditActors(int $wid): array {
        $o = []; foreach (Db::q('SELECT DISTINCT u.id, u.name FROM audit_log a JOIN users u ON u.id = a.actor_id WHERE a.workspace_id = ? ORDER BY u.name', [$wid])->fetchAll() as $r) $o[(int)$r['id']] = $r['name'];
        return $o;
    }

    /** [title, detail] shown in the table and in the CSV. */
    static function describe(array $r): array {
        $a = (string)$r['action']; $d = (string)$r['detail']; $who = (string)($r['target_name'] ?? ''); $role = fn(string $k) => Team::ROLE_LABEL[$k] ?? $k;
        $title = self::ACTIONS[$a] ?? ucfirst(str_replace('_', ' ', $a));
        if ($a === 'role_changed' && preg_match('/^(.+) -> (.+)$/', $d, $m)) $d = $who . ' · ' . $role($m[1]) . ' → ' . $role($m[2]);
        elseif ($a === 'invite_sent' && preg_match('/^(\S+) as (.+)$/', $d, $m)) $d = $m[1] . ' · ' . $role($m[2]);
        elseif ($a === 'invite_accepted') $d = $who . ' · ' . $role($d);
        elseif (in_array($a, ['daysoff_requested', 'leave_requested', 'request_approved', 'request_rejected'], true)) $d = $who . ' · ' . $d;
        elseif (in_array($a, ['manager_changed', 'reactivated', 'deactivated'], true)) $d = $who;
        elseif (in_array($a, ['email_change_requested', 'email_changed', 'password_changed'], true)) $d = $who;
        return [$title, $d];
    }

    static function csv(int $wid, array $f, DateTimeZone $tz): never {
        [$w, $a] = self::where($wid, $f);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="krono-audit-log-' . gmdate('Y-m-d') . '.csv"'); header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        $safe = fn(string $s) => preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;   // a spreadsheet must never run a cell as a formula
        fputcsv($out, ['Date and time', 'Actor', 'Action', 'Details', 'Result'], ',', '"', '');
        foreach (Db::q(self::SEL . " WHERE $w ORDER BY a.created_at DESC, a.id DESC LIMIT 5000", $a) as $r) {
            [$t, $d] = self::describe($r);
            fputcsv($out, [(new DateTimeImmutable($r['created_at'] . ' UTC'))->setTimezone($tz)->format('Y-m-d H:i:s'), $safe((string)($r['actor'] ?: 'System')), $safe($t), $safe($d), $r['result']], ',', '"', '');
        }
        exit;
    }
}
