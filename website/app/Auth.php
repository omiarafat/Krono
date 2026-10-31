<?php
final class Auth {
    const MAX_FAILS = 5, WINDOW_MIN = 15, CODE_TTL_MIN = 10, CODE_MAX_TRIES = 5, RESEND_SEC = 60;
    const TOKEN_DAYS = 90, HANDOFF_SEC = 60;
    // Hash used only to keep login timing similar when the email does not exist.
    const DUMMY_HASH = '$2y$10$AeWcwo8PtvnG/0w2wncI3eRAugcO0531Pn4d/xnprVV1KE5MZSOHi';

    static function fixedCode(): bool { return env('APP_ENV') === 'local' && env('MAIL_MODE') === 'fixed'; }
    private static function h(string $c): string {
        $k = (string)env('APP_KEY', ''); if (strlen($k) < 16) throw new RuntimeException('Set APP_KEY (16+ chars) in .env');
        return hash_hmac('sha256', $c, $k);
    }
    // Tokens and handoff codes are 256-bit random values, so a plain SHA-256 of them is enough to store.
    private static function sha(string $v): string { return hash('sha256', $v); }

    // ---------- website (cookie session) ----------
    static function user(): ?array {
        static $u = false;
        if ($u === false) {
            $u = null;
            if (!empty($_SESSION['uid'])) $u = Db::q('SELECT id, name, email FROM users WHERE id = ? AND status = "active" AND email_verified_at IS NOT NULL', [$_SESSION['uid']])->fetch() ?: null;
        }
        return $u;
    }
    /** Checks email + password with throttling. Returns [result, userRow|null]; result = ok | fail | locked | unverified. */
    static function check(string $email, string $pass): array {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $w = 'created_at > UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE';
        $byEmail = (int)Db::q("SELECT COUNT(*) FROM login_attempts WHERE $w AND email = ?", [$email])->fetchColumn();
        $byIp = (int)Db::q("SELECT COUNT(*) FROM login_attempts WHERE $w AND ip = ?", [$ip])->fetchColumn();
        if ($byEmail >= self::MAX_FAILS || $byIp >= 20) return ['locked', null];
        $u = Db::q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        $okPass = password_verify($pass, $u['password_hash'] ?? self::DUMMY_HASH);
        if (!$u || $u['status'] !== 'active' || !$okPass) {
            Db::q('INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$email, $ip]);
            return ['fail', null];
        }
        if (!$u['email_verified_at']) return ['unverified', $u];
        Db::q('DELETE FROM login_attempts WHERE email = ?', [$email]);
        return ['ok', $u];
    }
    static function attempt(string $email, string $pass): string {
        [$r, $u] = self::check($email, $pass);
        if ($r === 'ok') self::startSession((int)$u['id']);
        return $r;
    }
    static function startSession(int $uid): void { session_regenerate_id(true); $_SESSION['uid'] = $uid; }

    // ---------- registration + email verification (shared by website and app) ----------
    /** One set of rules for both clients. Returns a list of error messages (empty = valid). */
    static function registrationErrors(string $name, string $email, string $pass, ?string $confirm): array {
        $err = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $err[] = 'Enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $err[] = 'Enter a valid email address.';
        if (strlen($pass) < 8) $err[] = 'Password must be at least 8 characters.';
        if ($confirm !== null && $pass !== $confirm) $err[] = 'Passwords do not match.';
        return $err;
    }
    static function normEmail(string $e): string { return strtolower(trim($e)); }
    static function register(string $name, string $email, string $pass): bool {
        try {
            Db::q('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            return true;
        } catch (PDOException $e) { if ($e->getCode() === '23000') return false; throw $e; }
    }
    static function sendCode(string $email): string {
        $u = Db::q('SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL', [$email])->fetch();
        if (!$u) return 'none';
        $last = Db::q('SELECT created_at FROM email_verification_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$u['id']])->fetchColumn();
        if ($last && strtotime($last . ' UTC') > time() - self::RESEND_SEC) return 'throttled';
        $code = self::fixedCode() ? (string)env('FIXED_CODE', '123456') : (string)random_int(100000, 999999);
        Db::q('UPDATE email_verification_codes SET consumed_at = UTC_TIMESTAMP() WHERE user_id = ? AND consumed_at IS NULL', [$u['id']]);
        Db::q('INSERT INTO email_verification_codes (user_id, code_hash, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ? MINUTE, UTC_TIMESTAMP())', [$u['id'], self::h($code), self::CODE_TTL_MIN]);
        Mailer::send($email, 'Your Krono verification code', "Your Krono verification code is $code. It expires in " . self::CODE_TTL_MIN . ' minutes.');
        return 'sent';
    }
    static function verify(string $email, string $code): string {
        $u = Db::q('SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL', [$email])->fetch();
        if (!$u) return 'invalid';
        $r = Db::q('SELECT * FROM email_verification_codes WHERE user_id = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1', [$u['id']])->fetch();
        if (!$r || strtotime($r['expires_at'] . ' UTC') < time()) return 'expired';
        if ((int)$r['attempts'] >= self::CODE_MAX_TRIES) return 'locked';
        if (!hash_equals($r['code_hash'], self::h($code))) { Db::q('UPDATE email_verification_codes SET attempts = attempts + 1 WHERE id = ?', [$r['id']]); return 'invalid'; }
        Db::q('UPDATE email_verification_codes SET consumed_at = UTC_TIMESTAMP() WHERE id = ?', [$r['id']]);
        Db::q('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE id = ?', [$u['id']]);
        return 'ok';
    }

    // ---------- desktop app (Bearer token) ----------
    /** Creates a token for the app. Returns the plain token once; only its hash is stored. */
    static function issueToken(int $uid, string $device = 'Krono Desktop'): string {
        $t = bin2hex(random_bytes(32));
        Db::q('INSERT INTO api_tokens (user_id, token_hash, device_name, created_at, last_used_at, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL ' . self::TOKEN_DAYS . ' DAY)', [$uid, self::sha($t), mb_substr($device, 0, 100)]);
        return $t;
    }
    static function bearer(): string {
        $h = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($h === '' && function_exists('getallheaders')) foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $h = (string)$v;
        return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($h), $m) ? strtolower($m[1]) : '';
    }
    static function apiUser(): ?array {
        $t = self::bearer(); if ($t === '') return null;
        $u = Db::q('SELECT u.id, u.name, u.email FROM api_tokens k JOIN users u ON u.id = k.user_id
                    WHERE k.token_hash = ? AND k.revoked_at IS NULL AND k.expires_at > UTC_TIMESTAMP() AND u.status = "active" AND u.email_verified_at IS NOT NULL', [self::sha($t)])->fetch();
        if ($u) Db::q('UPDATE api_tokens SET last_used_at = UTC_TIMESTAMP() WHERE token_hash = ?', [self::sha($t)]);
        return $u ?: null;
    }
    static function revokeBearer(): void {
        $t = self::bearer(); if ($t !== '') Db::q('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ? AND revoked_at IS NULL', [self::sha($t)]);
    }

    // ---------- auto-login between website and app (one-time, 60 s, hashed) ----------
    /** target 'app' = website -> app; target 'web' = app -> website. */
    static function createHandoff(int $uid, string $target): string {
        Db::q('DELETE FROM login_handoffs WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY'); // tidy old rows
        $c = bin2hex(random_bytes(32));
        Db::q('INSERT INTO login_handoffs (user_id, code_hash, target, expires_at, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ' . self::HANDOFF_SEC . ' SECOND, UTC_TIMESTAMP())', [$uid, self::sha($c), $target]);
        return $c;
    }
    /** Uses a code once. Returns the user id, or null if wrong, expired, already used, or for the other target. */
    static function consumeHandoff(string $code, string $target): ?int {
        if (!preg_match('/^[a-f0-9]{64}$/', $code)) return null;
        $s = Db::q('UPDATE login_handoffs SET used_at = UTC_TIMESTAMP() WHERE code_hash = ? AND target = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()', [self::sha($code), $target]);
        if ($s->rowCount() !== 1) return null;
        $uid = Db::q('SELECT user_id FROM login_handoffs WHERE code_hash = ?', [self::sha($code)])->fetchColumn();
        $ok = $uid ? Db::q('SELECT id FROM users WHERE id = ? AND status = "active" AND email_verified_at IS NOT NULL', [$uid])->fetchColumn() : false;
        return $ok ? (int)$ok : null;
    }
}
