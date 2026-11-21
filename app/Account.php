<?php
/** Your own account (Settings > Profile): profile details, e-mail change, password, and the places you are signed in. */
final class Account {
    /** label shown => [PHP format, PHP format without the year] */
    const DATE_FORMATS = [
        'DD MMM YYYY' => ['d M Y', 'd M'], 'MMM DD, YYYY' => ['M d, Y', 'M d'], 'DD/MM/YYYY' => ['d/m/Y', 'd/m'],
        'MM/DD/YYYY' => ['m/d/Y', 'm/d'], 'YYYY-MM-DD' => ['Y-m-d', 'm-d'],
    ];
    const DEFAULT_FORMAT = 'MMM DD, YYYY';   // the look every page had before this setting existed
    const CODE_TTL_MIN = 10, CODE_TRIES = 5, RESEND_SEC = 60, MAX_FAILS = 5, WINDOW_MIN = 15, SEEN_DAYS = 2;

    private static function now(): string { return gmdate('Y-m-d H:i:s'); }
    private static function ago(int $sec): string { return gmdate('Y-m-d H:i:s', time() - $sec); }

    // ---------- appearance and notifications (per person) ----------

    const THEMES = ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'];
    static function prefs(int $uid): array {
        try { $r = Db::q('SELECT theme, notify_email, notify_weekly, notify_tips FROM users WHERE id = ?', [$uid])->fetch(); } catch (Throwable $e) { $r = null; }
        return $r ? ['theme' => (string)$r['theme'], 'email' => (bool)$r['notify_email'], 'weekly' => (bool)$r['notify_weekly'], 'tips' => (bool)$r['notify_tips']] : ['theme' => 'system', 'email' => true, 'weekly' => true, 'tips' => false];
    }
    /** @return string '' on success, else a message */
    static function savePrefs(int $uid, array $p): string {
        $theme = (string)($p['theme'] ?? ''); if (!isset(self::THEMES[$theme])) return 'Choose Light, Dark or System.';
        Db::q('UPDATE users SET theme = ?, notify_email = ?, notify_weekly = ?, notify_tips = ? WHERE id = ?', [$theme, empty($p['email']) ? 0 : 1, empty($p['weekly']) ? 0 : 1, empty($p['tips']) ? 0 : 1, $uid]);
        return '';
    }

    // ---------- display preferences (used by every page) ----------

    /** PHP date() format for the signed-in person's chosen style. $short drops the year. */
    static function df(bool $short = false): string {
        $u = Auth::user(); $k = (string)($u['date_format'] ?? '');
        if (!isset(self::DATE_FORMATS[$k])) $k = (string)Workspace::prefs()['date_format'];   // not chosen: follow the workspace
        return (self::DATE_FORMATS[$k] ?? self::DATE_FORMATS[self::DEFAULT_FORMAT])[$short ? 1 : 0];
    }
    /** PHP date() format for a clock time: workspace choice of 12-hour (3:05 PM) or 24-hour (15:05). */
    static function tf(bool $pad = true): string { return Workspace::prefs()['time_format'] === '24' ? 'H:i' : ($pad ? 'h:i A' : 'g:i A'); }
    /** The person's own timezone, or null to follow the workspace. */
    static function ownTimezone(): ?DateTimeZone {
        $u = Auth::user(); $z = (string)($u['timezone'] ?? '');
        if ($z === '') return null;
        try { return new DateTimeZone($z); } catch (Throwable $e) { return null; }
    }
    /** id => "Asia/Dhaka (UTC+06:00)" for the searchable timezone list, ordered by offset then name. */
    static function timezones(): array {
        $rows = []; $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        foreach (DateTimeZone::listIdentifiers() as $id) { $o = (new DateTimeZone($id))->getOffset($now); $rows[$id] = $o; }
        uasort($rows, fn($a, $b) => $a <=> $b);
        $out = [];
        foreach ($rows as $id => $o) { $s = $o < 0 ? '-' : '+'; $a = abs($o); $out[$id] = sprintf('%s (UTC%s%02d:%02d)', $id, $s, intdiv($a, 3600), intdiv($a % 3600, 60)); }
        return $out;
    }

    // ---------- profile ----------

    static function profile(int $uid): array {
        try { $r = Db::q('SELECT id, name, email, timezone, date_format, avatar FROM users WHERE id = ?', [$uid])->fetch(); }
        catch (Throwable $e) { $r = Db::q('SELECT id, name, email FROM users WHERE id = ?', [$uid])->fetch(); }
        $r['avatar'] = $r['avatar'] ?? null; $r['timezone'] = (string)($r['timezone'] ?? ''); $r['date_format'] = (string)($r['date_format'] ?? '');
        return $r;
    }

    /** @return array [errors, clean] */
    static function checkProfile(array $in): array {
        $err = []; $name = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')));
        $tz = trim((string)($in['timezone'] ?? '')); $df = (string)($in['date_format'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $err['name'] = 'Enter your full name (2 to 100 characters).';
        if ($tz !== '' && !in_array($tz, DateTimeZone::listIdentifiers(), true)) $err['timezone'] = 'Choose a timezone from the list.';
        if ($df !== '' && !isset(self::DATE_FORMATS[$df])) $err['date_format'] = 'Choose a date format from the list.';
        return [$err, ['name' => $name, 'timezone' => $tz, 'date_format' => $df]];
    }

    static function saveProfile(int $uid, array $c): void {
        Db::q('UPDATE users SET name = ?, timezone = ?, date_format = ? WHERE id = ?', [$c['name'], $c['timezone'] === '' ? null : $c['timezone'], $c['date_format'], $uid]);
    }

    // ---------- guarding the current password (shared by e-mail and password changes) ----------

    private static function key(int $uid): string { return 'uid:' . $uid; }
    private static function locked(int $uid): bool {
        return (int)Db::q('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [self::key($uid), self::ago(self::WINDOW_MIN * 60)])->fetchColumn() >= self::MAX_FAILS;
    }
    /** '' when the password is right, else a message the person can read. */
    static function passwordProblem(int $uid, string $pass): string {
        if (self::locked($uid)) return 'Too many wrong attempts. Try again in ' . self::WINDOW_MIN . ' minutes.';
        $hash = (string)Db::q('SELECT password_hash FROM users WHERE id = ?', [$uid])->fetchColumn();
        if ($pass !== '' && password_verify($pass, $hash)) { Db::q('DELETE FROM login_attempts WHERE email = ?', [self::key($uid)]); return ''; }
        Db::q('INSERT INTO login_attempts (email, ip, created_at) VALUES (?,?,?)', [self::key($uid), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), self::now()]);
        return 'That password is not correct.';
    }

    private static function audit(int $uid, string $action, string $detail = ''): void {
        try { $w = Workspace::forUser($uid); if ($w) Team::audit((int)$w['id'], $uid, $uid, $action, $detail); } catch (Throwable $e) { error_log((string)$e); }
    }

    // ---------- e-mail change (the old address stays until the new one is confirmed) ----------

    static function pendingEmail(int $uid): ?array {
        try { return Db::q('SELECT * FROM email_changes WHERE user_id = ? AND consumed_at IS NULL AND expires_at > ? ORDER BY id DESC LIMIT 1', [$uid, self::now()])->fetch() ?: null; }
        catch (Throwable $e) { return null; }
    }

    /** Sends a code to the new address. Returns a list of field => message (empty = code sent). */
    static function startEmailChange(array $user, string $new, string $password): array {
        $uid = (int)$user['id']; $new = Auth::normEmail($new);
        if (!filter_var($new, FILTER_VALIDATE_EMAIL) || strlen($new) > 190) return ['email' => 'Enter a valid email address.'];
        if ($new === Auth::normEmail((string)$user['email'])) return ['email' => 'That is already your email address.'];
        if ($p = self::passwordProblem($uid, $password)) return ['current_password' => $p];
        if (Db::q('SELECT 1 FROM users WHERE email = ?', [$new])->fetchColumn()) return ['email' => 'This email address cannot be used. Try another one.'];
        $last = Db::q('SELECT created_at FROM email_changes WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid])->fetchColumn();
        if ($last && strtotime($last . ' UTC') > time() - self::RESEND_SEC) return ['email' => 'A code was just sent. Wait a minute before asking for another.'];
        return self::sendChangeCode($uid, $new);
    }

    private static function sendChangeCode(int $uid, string $new): array {
        $code = Auth::fixedCode() ? (string)env('FIXED_CODE', '123456') : (string)random_int(100000, 999999);
        try { Mailer::send($new, 'Confirm your new Krono email', "Your Krono code is $code. It expires in " . self::CODE_TTL_MIN . ' minutes. If you did not ask for this, ignore this email.'); }
        catch (Throwable $e) { error_log((string)$e); return ['email' => 'Could not send the code. Check the address and try again.']; }
        Db::q('UPDATE email_changes SET consumed_at = ? WHERE user_id = ? AND consumed_at IS NULL', [self::now(), $uid]);
        Db::q('INSERT INTO email_changes (user_id, new_email, code_hash, expires_at, created_at) VALUES (?,?,?,?,?)', [$uid, $new, Auth::h($code), gmdate('Y-m-d H:i:s', time() + self::CODE_TTL_MIN * 60), self::now()]);
        self::audit($uid, 'email_change_requested');
        return [];
    }

    static function resendEmailCode(int $uid): string {
        $p = self::pendingEmail($uid); if (!$p) return 'That request has expired. Start again.';
        if (strtotime($p['created_at'] . ' UTC') > time() - self::RESEND_SEC) return 'A code was just sent. Wait a minute before asking for another.';
        $e = self::sendChangeCode($uid, $p['new_email']); return $e ? reset($e) : '';
    }

    /** '' = changed; otherwise a message. */
    static function confirmEmail(int $uid, string $code): string {
        $p = self::pendingEmail($uid); if (!$p) return 'That code has expired. Request a new one.';
        if ((int)$p['attempts'] >= self::CODE_TRIES) return 'Too many wrong codes. Request a new one.';
        if (!hash_equals($p['code_hash'], Auth::h(trim($code)))) { Db::q('UPDATE email_changes SET attempts = attempts + 1 WHERE id = ?', [$p['id']]); return 'That code is not correct.'; }
        try {
            Db::q('UPDATE users SET email = ? WHERE id = ?', [$p['new_email'], $uid]);
        } catch (PDOException $e) { if ($e->getCode() === '23000') return 'This email address cannot be used. Try another one.'; throw $e; }
        Db::q('UPDATE email_changes SET consumed_at = ? WHERE user_id = ? AND consumed_at IS NULL', [self::now(), $uid]);
        self::audit($uid, 'email_changed');
        return '';
    }

    static function cancelEmail(int $uid): void { Db::q('UPDATE email_changes SET consumed_at = ? WHERE user_id = ? AND consumed_at IS NULL', [self::now(), $uid]); }

    // ---------- password ----------

    /** Returns field => message. On success every OTHER sign-in (browsers and desktop) is signed out. */
    static function changePassword(int $uid, string $cur, string $new, string $confirm): array {
        $e = [];
        if (strlen($new) < 8) $e['new_password'] = 'Use at least 8 characters.';
        elseif (strlen($new) > 200) $e['new_password'] = 'That password is too long.';
        elseif ($new === $cur) $e['new_password'] = 'Choose a password different from the current one.';
        if ($new !== $confirm) $e['confirm_password'] = 'Passwords do not match.';
        if ($e) { if ($cur === '') $e['current_password'] = 'This field is required.'; return $e; }
        if ($p = self::passwordProblem($uid, $cur)) return ['current_password' => $p];
        Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
        self::signOutOthers($uid);
        self::audit($uid, 'password_changed');
        return [];
    }

    // ---------- sign-ins: websites (web_sessions) and desktop apps (api_tokens) ----------

    private static function sid(): string { return hash('sha256', session_id()); }

    private static function device(): string {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $b = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Web browser'))));
        $o = preg_match('/Windows/', $ua) ? 'Windows' : (preg_match('/Android/', $ua) ? 'Android' : (preg_match('/iPhone|iPad/', $ua) ? 'iOS' : (preg_match('/Mac OS X/', $ua) ? 'macOS' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
        return $o ? "$b on $o" : $b;
    }

    /** A new website sign-in. Never breaks a sign-in when the table is not there yet. */
    static function begin(int $uid): void {
        try {
            Db::q('DELETE FROM web_sessions WHERE last_seen_at < ?', [self::ago(30 * 86400)]);
            $n = self::now(); Db::q('INSERT INTO web_sessions (user_id, sid_hash, device, ip, created_at, last_seen_at) VALUES (?,?,?,?,?,?)', [$uid, self::sid(), self::device(), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), $n, $n]);
        } catch (Throwable $e) { error_log((string)$e); }
    }

    static function end(): void {
        try { if (session_id() !== '') Db::q('DELETE FROM web_sessions WHERE sid_hash = ?', [self::sid()]); } catch (Throwable $e) { error_log((string)$e); }
    }

    /** Called on every signed-in page: false when this browser was signed out from Settings. Sign-ins from before this feature are added on the fly. */
    static function track(int $uid): bool {
        if (session_id() === '') return true;
        try {
            $r = Db::q('SELECT id, user_id, revoked_at, last_seen_at FROM web_sessions WHERE sid_hash = ?', [self::sid()])->fetch();
            if (!$r || (int)$r['user_id'] !== $uid) { if ($r) Db::q('DELETE FROM web_sessions WHERE id = ?', [$r['id']]); self::begin($uid); return true; }
            if ($r['revoked_at']) return false;
            if (strtotime($r['last_seen_at'] . ' UTC') < time() - 60) Db::q('UPDATE web_sessions SET last_seen_at = ? WHERE id = ?', [self::now(), $r['id']]);
        } catch (Throwable $e) { error_log((string)$e); }
        return true;
    }

    /** Active sign-ins, this browser first. Each: kind web|app, id, device, ip, last (UTC), created (UTC), current. */
    static function sessions(int $uid): array {
        $out = [];
        try {
            foreach (Db::q('SELECT id, sid_hash, device, ip, created_at, last_seen_at FROM web_sessions WHERE user_id = ? AND revoked_at IS NULL AND last_seen_at > ? ORDER BY last_seen_at DESC', [$uid, self::ago(self::SEEN_DAYS * 86400)])->fetchAll() as $r)
                $out[] = ['kind' => 'web', 'id' => (int)$r['id'], 'device' => $r['device'], 'ip' => $r['ip'], 'last' => $r['last_seen_at'], 'created' => $r['created_at'], 'current' => hash_equals($r['sid_hash'], self::sid())];
        } catch (Throwable $e) { error_log((string)$e); }
        foreach (Db::q('SELECT id, device_name, created_at, last_used_at FROM api_tokens WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ? ORDER BY last_used_at DESC', [$uid, self::now()])->fetchAll() as $r)
            $out[] = ['kind' => 'app', 'id' => (int)$r['id'], 'device' => $r['device_name'], 'ip' => '', 'last' => $r['last_used_at'] ?: $r['created_at'], 'created' => $r['created_at'], 'current' => false];
        usort($out, fn($a, $b) => [$b['current'], $b['last']] <=> [$a['current'], $a['last']]);
        return $out;
    }

    /** Signs out one sign-in. This browser is not allowed here (use Sign out). Returns true when something changed. */
    static function revoke(int $uid, string $kind, int $id): bool {
        if ($kind === 'app') return Db::q('UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [self::now(), $id, $uid])->rowCount() > 0;
        return Db::q('UPDATE web_sessions SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND sid_hash <> ?', [self::now(), $id, $uid, self::sid()])->rowCount() > 0;
    }

    static function signOutOthers(int $uid): int {
        $n = 0; $now = self::now();
        try { $n += Db::q('UPDATE web_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL AND sid_hash <> ?', [$now, $uid, self::sid()])->rowCount(); } catch (Throwable $e) { error_log((string)$e); }
        $n += Db::q('UPDATE api_tokens SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [$now, $uid])->rowCount();
        return $n;
    }
}
