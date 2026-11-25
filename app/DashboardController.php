<?php
final class DashboardController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid);
        $hasOverview = Overview::available($ws); $tab = 'mine';
        if ($hasOverview) {   // Owner / Admin land on the Overview; the choice is remembered per person
            $key = 'krono_dash_' . $uid; $tab = in_array($_COOKIE[$key] ?? '', ['overview', 'mine'], true) ? $_COOKIE[$key] : 'overview';
            if (in_array($_GET['tab'] ?? '', ['overview', 'mine'], true)) { $tab = $_GET['tab']; setcookie($key, $tab, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']); }
            if ($tab === 'overview') { self::overview($u, $ws); return; }
        }
        if (EverywhereController::wants($u)) { EverywhereController::show('dashboard', $u); return; }
        $week = ($_GET['week'] ?? 'this') === 'last' ? 'last' : 'this';
        render('dashboard/index', ['title' => 'Dashboard', 'user' => $u, 'nav' => 'dashboard', 'crumb' => 'Dashboard', 'dash' => Dashboard::data($uid, $week), 'hasOverview' => $hasOverview, 'appInstalled' => Dashboard::appInstalled($uid), 'downloadUrl' => (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe')], 'app');
    }

    private static function overview(array $u, array $ws): void {
        $range = Overview::range((string)($_GET['range'] ?? 'week'));
        try { $o = Overview::data((int)$u['id'], $ws, $range); $failed = false; } catch (Throwable $e) { error_log((string)$e); $o = null; $failed = true; }
        render('dashboard/overview', ['title' => 'Dashboard', 'user' => $u, 'ws' => $ws, 'nav' => 'dashboard', 'crumb' => 'Dashboard', 'o' => $o, 'failed' => $failed, 'range' => $range], 'app');
    }
}
