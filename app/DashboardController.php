<?php
final class DashboardController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $week = ($_GET['week'] ?? 'this') === 'last' ? 'last' : 'this';
        render('dashboard/index', ['title' => 'Dashboard', 'user' => $u, 'nav' => 'dashboard', 'crumb' => 'Dashboard', 'dash' => Dashboard::data((int)$u['id'], $week), 'appInstalled' => Dashboard::appInstalled((int)$u['id']), 'downloadUrl' => (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe')], 'app');
    }
}
