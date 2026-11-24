<?php
/** The combined personal view of Dashboard, Tasks and Time Tracking (?scope=all). Only the person's own work. */
final class EverywhereController {
    static function show(string $page, array $u): void {
        $uid = (int)$u['id'];
        try {
            if ($page === 'tasks') $data = ['groups' => Everywhere::tasks($uid)];
            elseif ($page === 'time') $data = ['logs' => Everywhere::logs($uid)];
            else $data = ['rows' => Everywhere::summary($uid)];
            $failed = false;
        } catch (Throwable $e) { error_log((string)$e); $data = []; $failed = true; }
        $t = ['dashboard' => 'Dashboard', 'tasks' => 'Tasks', 'time' => 'Time Tracking'][$page] ?? 'Dashboard';
        render("everywhere/$page", $data + ['title' => $t, 'user' => $u, 'nav' => $page === 'time' ? 'time' : $page, 'crumb' => $t, 'failed' => $failed, 'scopeBase' => ['dashboard' => '/dashboard', 'tasks' => '/tasks', 'time' => '/time-tracking'][$page] ?? '/dashboard', 'scopeNow' => 'all'], 'app');
    }
    /** Used by the three controllers: in a personal workspace that has linked workspaces the combined view is the default; ?scope=own shows the personal workspace alone. */
    static function wants(array $u): bool { return ($_GET['scope'] ?? '') !== 'own' && Everywhere::active((int)$u['id']); }
}
