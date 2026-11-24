<?php
final class DashboardController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        if (EverywhereController::wants($u)) { EverywhereController::show('dashboard', $u); return; }
        $week = ($_GET['week'] ?? 'this') === 'last' ? 'last' : 'this';
        render('dashboard/index', ['title' => 'Dashboard', 'user' => $u, 'nav' => 'dashboard', 'crumb' => 'Dashboard', 'dash' => Dashboard::data((int)$u['id'], $week), 'team' => self::team((int)$u['id']), 'appInstalled' => Dashboard::appInstalled((int)$u['id']), 'downloadUrl' => (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe')], 'app');
    }

    private static function team(int $uid): ?array {
        try { return Dashboard::team($uid, Workspace::ensureFor($uid)); } catch (Throwable $e) { error_log((string)$e); return null; }
    }
}
