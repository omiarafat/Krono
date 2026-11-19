<?php
/** Reports page (read-only, live filters). PDF generation comes in the next stage. */
final class ReportController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid); Perm::gate($ws, 'reports.view', $u, 'reports', 'Reports'); $tz = Workspace::tz($ws);
        $people = [$uid => (string)$u['name']]; $f = null; $r = null; $opt = ['projects' => [], 'tasks' => []]; $any = false; $failed = false;
        try {
            $people = TimeLog::people($uid, $ws); $f = Report::cleanFilters($_GET, $tz, $people); $any = TimeLog::hasAny($ws, $people);
            if ($any) { $opt = TimeLog::options($ws, $people); $r = Report::build($ws, $people, $f, $tz); }
        } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        $f ??= Report::cleanFilters([], $tz, $people);
        render('reports/index', ['title' => 'Reports', 'user' => $u, 'nav' => 'reports', 'crumb' => 'Reports', 'ws' => $ws, 'tz' => $tz, 'f' => $f, 'r' => $r, 'opt' => $opt, 'people' => $people, 'any' => $any, 'failed' => $failed,
            'appInstalled' => Dashboard::appInstalled($uid), 'downloadUrl' => (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe')], 'app');
    }
}
