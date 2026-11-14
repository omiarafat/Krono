<?php
/** Attendance history and Attendance Details (both read-only). */
final class AttendanceController {
    private static function dl(): string { return (string)env('APP_DOWNLOAD_URL', 'https://github.com/omiarafat/krono-desktop/releases/latest/download/Krono-Setup.exe'); }

    static function index(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid); $tz = Workspace::tz($ws);
        $people = [$uid => (string)$u['name']]; $list = null; $any = false; $failed = false; $f = null;
        try {
            $people = TimeLog::people($uid, $ws); $f = Attendance::cleanFilters($_GET, $tz, $people, $uid);
            $any = Attendance::hasAny($people);
            if ($any) $list = Attendance::listing($people, $f, $tz);
        } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        $f ??= Attendance::cleanFilters([], $tz, $people, $uid);
        render('attendance/index', ['title' => 'Attendance', 'user' => $u, 'nav' => 'attendance', 'crumb' => 'Attendance', 'ws' => $ws, 'tz' => $tz, 'f' => $f, 'list' => $list, 'people' => $people, 'any' => $any, 'failed' => $failed, 'downloadUrl' => self::dl()], 'app');
    }

    /** /attendance/details?id=N (one session) or ?user=N&date=Y-m-d (that person's first session that day). */
    static function details(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid); $tz = Workspace::tz($ws);
        $nf = fn() => (http_response_code(404) || true) && render('attendance/not-found', ['title' => 'Attendance record not found', 'user' => $u, 'nav' => 'attendance', 'crumb' => 'Attendance', 'crumb2' => 'Attendance Details', 'crumburl' => '/attendance', 'ws' => $ws], 'app');
        try {
            $people = TimeLog::people($uid, $ws); $s = null; $emp = $uid; $date = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
            if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) { $s = Attendance::find((int)$_GET['id'], $people); if (!$s) { $nf(); return; } $emp = (int)$s['user_id']; $date = Attendance::ymd($s['sign_in_at'], $tz); }
            else {
                $q = (string)($_GET['user'] ?? ''); if ($q !== '') { if (!ctype_digit($q) || !isset($people[(int)$q])) { $nf(); return; } $emp = (int)$q; }
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($_GET['date'] ?? ''));
                if ($d && $d->format('Y-m-d') === ($_GET['date'] ?? '') && $d->format('Y-m-d') <= $date) $date = $d->format('Y-m-d');
                $s = Attendance::onDate($emp, $date, $tz);
            }
            $breaks = $s ? Attendance::breaks((int)$s['id']) : []; $calc = $s ? Attendance::calc($s, $breaks) : null;
            $prev = $s ? Attendance::neighbour($s, 'prev') : null; $next = $s ? Attendance::neighbour($s, 'next') : null;
            render('attendance/details', ['title' => 'Attendance Details', 'user' => $u, 'nav' => 'attendance', 'crumb' => 'Attendance', 'crumb2' => 'Attendance Details', 'crumburl' => '/attendance', 'ws' => $ws, 'tz' => $tz,
                's' => $s, 'breaks' => $breaks, 'calc' => $calc, 'emp' => Attendance::employee($emp, $ws), 'empId' => $emp, 'date' => $date, 'prev' => $prev, 'next' => $next, 'today' => (new DateTimeImmutable('today', $tz))->format('Y-m-d')], 'app');
        } catch (Throwable $e) { error_log((string)$e); http_response_code(500); render('errors/500', ['title' => 'Something went wrong', 'detail' => env('APP_ENV') === 'local' ? $e->getMessage() : '']); }
    }
}
