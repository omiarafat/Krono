<?php
/** Workspace Settings (Owner / Admin): General defaults, role overview, e-mail sender and the audit log. Every method works on ONE workspace id. */
final class WsSettings {
    const PAGE = 10, RANGES = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'];
    const LANGUAGES = ['en' => 'English'], TIME_FORMATS = ['12' => '12-hour (AM/PM)', '24' => '24-hour'];
    const ACTIONS = [
        'invite_sent' => 'Member invited', 'invite_cancelled' => 'Invitation cancelled', 'invite_resent' => 'Invitation resent', 'invite_accepted' => 'Invitation accepted',
        'role_changed' => 'Role updated', 'manager_changed' => 'Reports-to changed', 'reactivated' => 'Member reactivated', 'deactivated' => 'Member deactivated',
        'email_change_requested' => 'Email change requested', 'email_changed' => 'Email changed', 'password_changed' => 'Password changed',
        'workspace_updated' => 'Workspace settings updated', 'email_settings_updated' => 'Email settings updated', 'email_test' => 'Test email sent',
    ];

    // ---------- General ----------

    static function code(int $id): string { return 'KRW-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }

    static function general(int $wid): array {
        $r = Db::q('SELECT id, name, timezone, created_at FROM workspaces WHERE id = ?', [$wid])->fetch() ?: [];
        $r += Workspace::prefs($wid);
        $r['owner'] = (string)Db::q("SELECT u.name FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.role = 'owner' ORDER BY m.created_at LIMIT 1", [$wid])->fetchColumn();
        return $r;
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [errors, clean values] */
    static function checkGeneral(array $p): array {
        $err = []; $c = [
            'name' => trim(preg_replace('/\s+/', ' ', (string)($p['name'] ?? ''))), 'timezone' => (string)($p['timezone'] ?? ''), 'week_start' => (string)($p['week_start'] ?? ''),
            'date_format' => (string)($p['date_format'] ?? ''), 'time_format' => (string)($p['time_format'] ?? ''), 'language' => (string)($p['language'] ?? 'en'),
        ];
        if ($c['name'] === '' || mb_strlen($c['name']) > 120) $err['name'] = 'Enter a workspace name (up to 120 characters).';
        if (!in_array($c['timezone'], DateTimeZone::listIdentifiers(), true)) $err['timezone'] = 'Choose a timezone from the list.';
        if (!isset(Workspace::WEEK_DAYS[$c['week_start']])) $err['week_start'] = 'Choose the first day of the week.';
        if (!isset(Account::DATE_FORMATS[$c['date_format']])) $err['date_format'] = 'Choose a date format.';
        if (!isset(self::TIME_FORMATS[$c['time_format']])) $err['time_format'] = 'Choose a time format.';
        if (!isset(self::LANGUAGES[$c['language']])) $err['language'] = 'Choose a language.';
        return [$err, $c];
    }

    static function saveGeneral(int $wid, int $actor, array $c): void {
        $old = self::general($wid); $names = ['name' => 'Name', 'timezone' => 'Timezone', 'week_start' => 'Week start', 'date_format' => 'Date format', 'time_format' => 'Time format', 'language' => 'Language'];
        $changed = array_keys(array_filter($names, fn($l, $k) => (string)$old[$k] !== $c[$k], ARRAY_FILTER_USE_BOTH));
        if (!$changed) return;
        Db::q('UPDATE workspaces SET name = ?, timezone = ?, week_start = ?, date_format = ?, time_format = ?, language = ? WHERE id = ?', [$c['name'], $c['timezone'], $c['week_start'], $c['date_format'], $c['time_format'], $c['language'], $wid]);
        Team::audit($wid, $actor, null, 'workspace_updated', implode(', ', array_map(fn($k) => $names[$k], $changed)) . ' changed');
    }

    // ---------- Roles ----------

    /** role => number of active members */
    static function roleCounts(int $wid): array {
        $o = array_fill_keys(array_keys(Team::ROLE_LABEL), 0);
        foreach (Db::q("SELECT role, COUNT(*) n FROM workspace_members WHERE workspace_id = ? AND status = 'active' GROUP BY role", [$wid])->fetchAll() as $r) $o[$r['role']] = (int)$r['n'];
        return $o;
    }

    // ---------- E-mail ----------

    private static function key(): string { return hash('sha256', 'krono-mail|' . (string)env('APP_KEY', ''), true); }
    private static function seal(string $s): string { $iv = random_bytes(12); $c = openssl_encrypt($s, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag); return base64_encode($iv . $tag . $c); }
    private static function unseal(?string $s): string {
        $b = base64_decode((string)$s, true); if ($b === false || strlen($b) < 29) return '';
        $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16)); return $p === false ? '' : $p;
    }

    static function mail(int $wid): array {
        $r = Db::q('SELECT * FROM workspace_email WHERE workspace_id = ?', [$wid])->fetch() ?: [];
        $r += ['provider' => 'default', 'from_name' => '', 'from_email' => '', 'reply_to' => '', 'smtp_host' => '', 'smtp_port' => 587, 'smtp_secure' => 'tls', 'smtp_user' => '', 'smtp_pass_enc' => null, 'test_sent_at' => null];
        $r['has_pass'] = $r['smtp_pass_enc'] !== null && $r['smtp_pass_enc'] !== '';
        return $r;
    }
    /** What this server sends with when the workspace uses "Server default" (set in .env, never shown on screen). */
    static function serverMail(): bool { return in_array(env('MAIL_MODE', 'log'), ['smtp'], true) || (env('APP_ENV') === 'local' && in_array(env('MAIL_MODE', 'log'), ['fixed', 'log'], true)); }
    static function configured(array $m): bool { return $m['provider'] === 'smtp' ? ($m['smtp_host'] !== '' && $m['from_email'] !== '') : self::serverMail(); }

    /** Settings for Mailer::send() (invitations and test mail), or null before anything is saved. */
    static function mailConfig(int $wid): ?array {
        try { $m = self::mail($wid); } catch (Throwable $e) { return null; }
        $cfg = ['name' => $m['from_name'], 'email' => $m['from_email'], 'reply' => $m['reply_to'], 'smtp' => null];
        if ($m['provider'] === 'smtp' && $m['smtp_host'] !== '') $cfg['smtp'] = ['host' => $m['smtp_host'], 'port' => (int)$m['smtp_port'], 'secure' => $m['smtp_secure'], 'user' => $m['smtp_user'], 'pass' => self::unseal($m['smtp_pass_enc'])];
        return $cfg;
    }

    static function checkMail(array $p, array $cur): array {
        $err = []; $mail = fn(string $k) => mb_strtolower(trim((string)($p[$k] ?? '')));
        $c = ['provider' => ($p['provider'] ?? '') === 'smtp' ? 'smtp' : 'default', 'from_name' => trim((string)($p['from_name'] ?? '')), 'from_email' => $mail('from_email'), 'reply_to' => $mail('reply_to'),
              'smtp_host' => trim((string)($p['smtp_host'] ?? '')), 'smtp_port' => (int)($p['smtp_port'] ?? 587), 'smtp_secure' => (string)($p['smtp_secure'] ?? 'tls'), 'smtp_user' => trim((string)($p['smtp_user'] ?? '')), 'smtp_pass' => (string)($p['smtp_pass'] ?? '')];
        if (mb_strlen($c['from_name']) > 100 || preg_match('/[\r\n<>]/', $c['from_name'])) $err['from_name'] = 'Use up to 100 characters, without line breaks or < >.';
        if ($c['from_email'] !== '' && !filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)) $err['from_email'] = 'Enter a valid email address.';
        if ($c['reply_to'] !== '' && !filter_var($c['reply_to'], FILTER_VALIDATE_EMAIL)) $err['reply_to'] = 'Enter a valid email address.';
        if ($c['provider'] === 'smtp') {
            if ($c['from_email'] === '') $err['from_email'] = 'Enter the address the mail is sent from.';
            if ($c['smtp_host'] === '' || !preg_match('/^[A-Za-z0-9.-]{1,190}$/', $c['smtp_host'])) $err['smtp_host'] = 'Enter the mail server name, for example smtp.example.com.';
            if ($c['smtp_port'] < 1 || $c['smtp_port'] > 65535) $err['smtp_port'] = 'Enter a port between 1 and 65535.';
            if (!in_array($c['smtp_secure'], ['tls', 'ssl', 'none'], true)) $err['smtp_secure'] = 'Choose a connection type.';
            if ($c['smtp_user'] !== '' && $c['smtp_pass'] === '' && !$cur['has_pass']) $err['smtp_pass'] = 'Enter the password for this mail account.';
        }
        return [$err, $c];
    }

    static function saveMail(int $wid, int $actor, array $c): void {
        $cur = self::mail($wid);
        $pass = $c['smtp_pass'] !== '' ? self::seal($c['smtp_pass']) : $cur['smtp_pass_enc'];   // blank = keep the saved password
        if ($c['smtp_user'] === '') $pass = null;
        $vals = [$c['provider'], $c['from_name'], $c['from_email'], $c['reply_to'], $c['smtp_host'], $c['smtp_port'], $c['smtp_secure'], $c['smtp_user'], $pass];
        if (Db::q('SELECT 1 FROM workspace_email WHERE workspace_id = ?', [$wid])->fetchColumn()) Db::q('UPDATE workspace_email SET provider=?, from_name=?, from_email=?, reply_to=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_user=?, smtp_pass_enc=? WHERE workspace_id = ?', [...$vals, $wid]);
        else Db::q('INSERT INTO workspace_email (provider, from_name, from_email, reply_to, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass_enc, workspace_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$vals, $wid]);
        Team::audit($wid, $actor, null, 'email_settings_updated', $c['provider'] === 'smtp' ? 'Own mail server' : 'Server default');
    }

    /** Sends a test message to the signed-in person's own address. @return string '' on success, else a message the person can read */
    static function sendTest(int $wid, array $u): string {
        $m = self::mail($wid);
        if ($m['test_sent_at'] && time() - strtotime($m['test_sent_at'] . ' UTC') < 30) return 'Wait a moment before sending another test email.';
        try {
            Mailer::send((string)$u['email'], 'Krono test email', "This is a test email from your Krono workspace.\nIf you can read it, your email settings work.", self::mailConfig($wid));
            $ok = true; $why = '';
        } catch (Throwable $e) { error_log((string)$e); $ok = false; $why = $e->getMessage(); }
        if (Db::q('SELECT 1 FROM workspace_email WHERE workspace_id = ?', [$wid])->fetchColumn()) Db::q('UPDATE workspace_email SET test_sent_at = ? WHERE workspace_id = ?', [gmdate('Y-m-d H:i:s'), $wid]);
        Db::q('INSERT INTO audit_log (workspace_id, actor_id, target_user_id, action, detail, result, created_at) VALUES (?,?,?,?,?,?,?)', [$wid, (int)$u['id'], null, 'email_test', $ok ? 'Sent to ' . $u['email'] : mb_substr($why, 0, 200), $ok ? 'success' : 'failed', gmdate('Y-m-d H:i:s')]);
        return $ok ? '' : $why;
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
        if ($a === 'email_test' && $r['result'] === 'failed') $title = 'Email test failed';
        if ($a === 'role_changed' && preg_match('/^(\w+) -> (\w+)$/', $d, $m)) $d = $who . ' · ' . $role($m[1]) . ' → ' . $role($m[2]);
        elseif ($a === 'invite_sent' && preg_match('/^(\S+) as (\w+)$/', $d, $m)) $d = $m[1] . ' · ' . $role($m[2]);
        elseif ($a === 'invite_accepted') $d = $who . ' · ' . $role($d);
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
