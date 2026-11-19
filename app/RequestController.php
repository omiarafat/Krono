<?php
/** Days-off and leave requests: ask (own), withdraw (own) and decide (Owner, Admin, or a manager for direct reports). */
final class RequestController {
    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return [$u, Workspace::ensureFor((int)$u['id'])]; }

    /** Where the form came from: only our own pages. */
    private static function back(string $fallback): string {
        $b = (string)($_POST['back'] ?? '');
        return preg_match('#^/(teams|attendance|settings/profile)(\?[A-Za-z0-9_=&%.\-]*)?$#', $b) ? $b : $fallback;
    }
    private static function done(string $err, string $ok, string $okTitle, string $errTitle, string $back): never {
        if ($err !== '') flash('error', $err, $errTitle); else flash('ok', $ok, $okTitle);
        redirect($back);
    }

    /** Settings > Profile: ask for own weekly days off. */
    static function days(): void {
        [$u, $ws] = self::who();
        try { $err = DaysOff::requestDays($ws, (int)$u['id'], (array)($_POST['days_off'] ?? [])); } catch (Throwable $e) { error_log((string)$e); $err = 'Could not send the request. Try again.'; }
        self::done($err, 'Your Owner or Admin will review it. Until then your current days off stay.', 'Request sent', 'Request not sent', '/settings/profile');
    }

    /** Attendance: ask for leave on a working day. */
    static function leave(): void {
        [$u, $ws] = self::who();
        try { $err = DaysOff::requestLeave($ws, (int)$u['id'], (string)($_POST['date'] ?? ''), (string)($_POST['comment'] ?? ''), Workspace::tz($ws)); } catch (Throwable $e) { error_log((string)$e); $err = 'Could not send the request. Try again.'; }
        self::done($err, 'Until it is approved, that day counts as Absent.', 'Leave request sent', 'Request not sent', self::back('/attendance'));
    }

    static function cancel(): void {
        [$u, $ws] = self::who();
        try { $ok = DaysOff::cancel((int)$ws['id'], (int)$u['id'], (int)($_POST['id'] ?? 0)); } catch (Throwable $e) { error_log((string)$e); $ok = false; }
        self::done($ok ? '' : 'That request was already handled.', 'The request was withdrawn.', 'Request withdrawn', 'Could not withdraw', self::back('/attendance'));
    }

    /** Teams: approve or reject. */
    static function decide(): void {
        [$u, $ws] = self::who(); $do = (string)($_POST['do'] ?? '');
        try { $err = DaysOff::decide($ws, (int)$u['id'], (int)($_POST['id'] ?? 0), $do); } catch (Throwable $e) { error_log((string)$e); $err = 'Could not save the decision. Try again.'; }
        self::done($err, $do === 'approve' ? 'The request was approved.' : 'The request was rejected.', $do === 'approve' ? 'Approved' : 'Rejected', 'Could not decide', self::back('/teams'));
    }
}
