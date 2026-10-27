<?php
final class Auth {
    const MAX_FAILS = 5, WINDOW_MIN = 15, CODE_TTL_MIN = 10, CODE_MAX_TRIES = 5, RESEND_SEC = 60;

    static function fixedCode(): bool { return env('APP_ENV') === 'local' && env('MAIL_MODE') === 'fixed'; }
    private static function h(string $c): string {
        $k = (string)env('APP_KEY', ''); if (strlen($k) < 16) throw new RuntimeException('Set APP_KEY (16+ chars) in .env');
        return hash_hmac('sha256', $c, $k);
    }
    static function user(): ?array {
        static $u = false;
        if ($u === false) {
            $u = null;
            if (!empty($_SESSION['uid'])) $u = Db::q('SELECT id, name, email FROM users WHERE id = ? AND status = "active" AND email_verified_at IS NOT NULL', [$_SESSION['uid']])->fetch() ?: null;
        }
        return $u;
    }
    static function attempt(string $email, string $pass): string {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $w = 'created_at > UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MIN . ' MINUTE';
        $byEmail = (int)Db::q("SELECT COUNT(*) FROM login_attempts WHERE $w AND email = ?", [$email])->fetchColumn();
        $byIp = (int)Db::q("SELECT COUNT(*) FROM login_attempts WHERE $w AND ip = ?", [$ip])->fetchColumn();
        if ($byEmail >= self::MAX_FAILS || $byIp >= 20) return 'locked';
        $u = Db::q('SELECT * FROM users WHERE email = ?', [$email])->fetch();
        if (!$u || $u['status'] !== 'active' || !password_verify($pass, $u['password_hash'])) {
            Db::q('INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, UTC_TIMESTAMP())', [$email, $ip]);
            return 'fail';
        }
        if (!$u['email_verified_at']) return 'unverified';
        Db::q('DELETE FROM login_attempts WHERE email = ?', [$email]);
        session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
        return 'ok';
    }
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
}
