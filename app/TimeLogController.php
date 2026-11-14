<?php
/** Time Tracking page (read-only history of saved sessions). */
final class TimeLogController {
    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid); $tz = Workspace::tz($ws);
        $people = [$uid => (string)$u['name']]; $f = null; $list = null; $opt = ['projects' => [], 'tasks' => []]; $any = false; $failed = false;
        try {
            $people = TimeLog::people($uid, $ws); $f = TimeLog::cleanFilters($_GET, $tz, $people);
            $any = TimeLog::hasAny($ws, $people);
            if ($any) { $opt = TimeLog::options($ws, $people); $list = TimeLog::listing($ws, $people, $f, $tz); }
        } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        $f ??= TimeLog::cleanFilters([], $tz, $people);
        render('time/index', ['title' => 'Time Tracking', 'user' => $u, 'nav' => 'time', 'crumb' => 'Time Tracking', 'ws' => $ws, 'tz' => $tz, 'f' => $f, 'list' => $list, 'opt' => $opt, 'people' => $people, 'any' => $any, 'failed' => $failed,
            'appInstalled' => Dashboard::appInstalled($uid), 'downloadUrl' => (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe')], 'app');
    }
}
