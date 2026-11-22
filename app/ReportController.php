<?php
/** Reports page (live filters) and the PDF export of the same filters. */
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

    /** GET /reports/pdf: A4 PDF of the current filters; needs reports.export, scoped like the page. */
    static function pdf(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid); Perm::gate($ws, 'reports.view', $u, 'reports', 'Reports'); Perm::gate($ws, 'reports.export', $u, 'reports', 'Reports'); $tz = Workspace::tz($ws);
        $back = '/reports' . (($q = (string)($_SERVER['QUERY_STRING'] ?? '')) !== '' ? '?' . $q : '');
        if (!ReportPdf::available()) { flash('error', 'PDF export is not installed yet. Run "composer require dompdf/dompdf" in the project folder.'); redirect($back); }
        try {
            $people = TimeLog::people($uid, $ws); $f = Report::cleanFilters($_GET, $tz, $people);
            $html = ReportPdf::html($ws, $u, $people, $f, $tz, Report::build($ws, $people, $f, $tz, true));
            $name = ReportPdf::filename($ws, $f);
            Team::audit((int)$ws['id'], $uid, $uid, 'report_exported', $name);
            ReportPdf::stream($html, $name);
        } catch (Throwable $e) { error_log((string)$e); flash('error', 'The report could not be generated. Please try again.'); redirect($back); }
    }
}
