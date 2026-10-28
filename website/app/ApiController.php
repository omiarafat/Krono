<?php
/**
 * JSON API for the Krono Desktop app. Same users table and same rules as the website,
 * so an email can never exist twice. Auth = Bearer token (see Auth::issueToken).
 */
final class ApiController {
    private static function in(): array {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw === false ? '' : $raw, true);
        return is_array($d) ? $d : [];
    }
    private static function out(array $d, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
        echo json_encode($d, JSON_UNESCAPED_UNICODE); exit;
    }
    private static function fail(string $code, string $msg, int $status = 400): never { self::out(['ok' => false, 'error' => $code, 'message' => $msg], $status); }
    private static function need(): array { $u = Auth::apiUser(); if (!$u) self::fail('unauthorized', 'Please sign in again.', 401); return $u; }
    private static function userOut(array $u): array { return ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email']]; }
    private static function str(array $d, string $k): string { return is_string($d[$k] ?? null) ? $d[$k] : ''; }

    /** Wraps a handler: JSON errors, no stack traces to the client. */
    static function run(callable $fn): void {
        try { $fn(); }
        catch (Throwable $e) {
            error_log((string)$e);
            self::fail('server_error', env('APP_ENV') === 'local' ? $e->getMessage() : 'Something went wrong. Try again.', 500);
        }
    }

    static function register(): void {
        $d = self::in(); $name = trim(self::str($d, 'name')); $email = Auth::normEmail(self::str($d, 'email')); $pass = self::str($d, 'password');
        $err = Auth::registrationErrors($name, $email, $pass, null);
        if ($err) self::fail('invalid', implode(' ', $err));
        if (!Auth::register($name, $email, $pass)) self::fail('email_exists', 'An account with this email already exists. Sign in instead.', 409);
        Auth::sendCode($email);
        self::out(['ok' => true, 'status' => 'verify', 'devMode' => Auth::fixedCode()]);
    }
    static function verify(): void {
        $d = self::in(); $email = Auth::normEmail(self::str($d, 'email')); $code = preg_replace('/\D/', '', self::str($d, 'code'));
        $r = strlen($code) === 6 ? Auth::verify($email, $code) : 'invalid';
        if ($r !== 'ok') {
            $m = ['invalid' => 'That code is not correct.', 'expired' => 'That code has expired. Request a new one.', 'locked' => 'Too many wrong attempts. Request a new code.'][$r];
            self::fail($r, $m, 422);
        }
        self::out(['ok' => true]); // the person signs in next (password), same as on the website
    }
    static function resend(): void {
        $email = Auth::normEmail(self::str(self::in(), 'email'));
        if (Auth::sendCode($email) === 'throttled') self::fail('throttled', 'Please wait a minute before requesting another code.', 429);
        self::out(['ok' => true, 'devMode' => Auth::fixedCode()]); // same answer whether or not the email exists
    }
    static function login(): void {
        $d = self::in(); $email = Auth::normEmail(self::str($d, 'email'));
        [$r, $u] = Auth::check($email, self::str($d, 'password'));
        if ($r === 'locked') self::fail('locked', 'Too many attempts. Try again in ' . Auth::WINDOW_MIN . ' minutes.', 429);
        if ($r === 'unverified') { Auth::sendCode($email); self::out(['ok' => false, 'error' => 'unverified', 'message' => 'Please verify your email to continue.', 'devMode' => Auth::fixedCode()], 403); }
        if ($r !== 'ok') self::fail('invalid_credentials', 'Incorrect email or password.', 401);
        $dev = mb_substr(self::str($d, 'device') ?: 'Krono Desktop', 0, 100);
        self::out(['ok' => true, 'token' => Auth::issueToken((int)$u['id'], $dev), 'user' => self::userOut($u)]);
    }
    /** Website -> app auto-login: the app exchanges the one-time code from krono://auth?code=... for its own token. */
    static function handoff(): void {
        $d = self::in(); $uid = Auth::consumeHandoff(strtolower(self::str($d, 'code')), 'app');
        if (!$uid) self::fail('invalid_code', 'This sign-in link expired or was already used. Press Open Krono Desktop on the website again.', 401);
        $u = Db::q('SELECT id, name, email FROM users WHERE id = ?', [$uid])->fetch();
        self::out(['ok' => true, 'token' => Auth::issueToken($uid, mb_substr(self::str($d, 'device') ?: 'Krono Desktop', 0, 100)), 'user' => self::userOut($u)]);
    }
    static function me(): void { self::out(['ok' => true, 'user' => self::userOut(self::need())]); }
    static function logout(): void { self::need(); Auth::revokeBearer(); self::out(['ok' => true]); }
    /** App -> website auto-login: returns a one-time URL for the browser. */
    static function webLink(): void {
        $u = self::need();
        self::out(['ok' => true, 'url' => url('/auth/handoff') . '?code=' . Auth::createHandoff((int)$u['id'], 'web')]);
    }
    static function notFound(): void { self::fail('not_found', 'Unknown API route.', 404); }
}
