<?php
final class DashboardController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $week = ($_GET['week'] ?? 'this') === 'last' ? 'last' : 'this';
        render('dashboard/index', ['title' => 'Dashboard', 'user' => $u, 'nav' => 'dashboard', 'crumb' => 'Dashboard', 'dash' => Dashboard::data((int)$u['id'], $week)], 'app');
    }
}
