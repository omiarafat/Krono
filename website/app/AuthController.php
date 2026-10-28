<?php
final class AuthController {
    static function showLogin(): void { if (Auth::user()) redirect('/dashboard'); render('auth/login', ['title' => 'Sign in']); }
    static function login(): void {
        $email = Auth::normEmail((string)($_POST['email'] ?? ''));
        $r = Auth::attempt($email, (string)($_POST['password'] ?? ''));
        if ($r === 'ok') redirect('/dashboard');
        if ($r === 'unverified') { $_SESSION['pending_email'] = $email; Auth::sendCode($email); flash('ok', 'Please verify your email to continue.'); redirect('/verify'); }
        $msg = $r === 'locked' ? 'Too many attempts. Try again in ' . Auth::WINDOW_MIN . ' minutes.' : 'Incorrect email or password.';
        render('auth/login', ['title' => 'Sign in', 'errors' => [$msg]]);
    }
    static function showRegister(): void { if (Auth::user()) redirect('/dashboard'); render('auth/register', ['title' => 'Create account']); }
    static function register(): void {
        $name = trim((string)($_POST['name'] ?? '')); $email = Auth::normEmail((string)($_POST['email'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $err = Auth::registrationErrors($name, $email, $pass, (string)($_POST['password_confirm'] ?? ''));
        if (!$err &&!Auth::register($name, $email, $pass)) $err[] = 'An account with this email already exists.';
        if ($err) { render('auth/register', ['title' => 'Create account', 'errors' => $err]); return; }
        $_SESSION['pending_email'] = $email; Auth::sendCode($email); redirect('/verify');
    }
    static function showVerify(): void { if (empty($_SESSION['pending_email'])) redirect('/login'); render('auth/verify', ['title' => 'Verify your email', 'email' => $_SESSION['pending_email']]); }
    static function verify(): void {
        $email = (string)($_SESSION['pending_email'] ?? ''); if (!$email) redirect('/login');
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        $r = strlen($code) === 6 ? Auth::verify($email, $code) : 'invalid';
        if ($r === 'ok') { unset($_SESSION['pending_email']); flash('ok', 'Email verified. You can sign in now.'); redirect('/login'); }
        $m = ['invalid' => 'That code is not correct.', 'expired' => 'That code has expired. Request a new one.', 'locked' => 'Too many wrong attempts. Request a new code.'][$r];
        render('auth/verify', ['title' => 'Verify your email', 'email' => $email, 'errors' => [$m]]);
    }
    static function resend(): void {
        $email = (string)($_SESSION['pending_email'] ?? ''); if (!$email) redirect('/login');
        $r = Auth::sendCode($email);
        flash($r === 'throttled' ? 'error' : 'ok', $r === 'throttled' ? 'Please wait a minute before requesting another code.' : 'A new code was sent.');
        redirect('/verify');
    }
    // Website -> app: the signed-in user presses "Open Krono Desktop". A one-time code (60 s) goes to the app through krono://.
    static function openApp(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $code = Auth::createHandoff((int)$u['id'], 'app');
        render('open-app', ['title' => 'Open Krono Desktop', 'user' => $u, 'link' => 'krono://auth?code=' . $code]);
    }
    // App -> website: "View Website" in the app opens this URL with a one-time code; the browser gets a normal session.
    static function webHandoff(): void {
        $uid = Auth::consumeHandoff((string)($_GET['code'] ?? ''), 'web');
        if (!$uid) { flash('error', 'That sign-in link expired. Open the website again from the Krono Desktop app, or sign in here.'); redirect('/login'); }
        Auth::startSession($uid); redirect('/dashboard');
    }
    static function logout(): void { $_SESSION = []; session_destroy(); redirect('/login'); }
    static function dashboard(): void { $u = Auth::user(); if (!$u) redirect('/login'); render('dashboard-placeholder', ['title' => 'Dashboard', 'user' => $u]); }
}
