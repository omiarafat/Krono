<?php
/**
 * Weekly days off and the two kinds of request that need approval:
 *  - "days_off": a person asks to use their own weekly days off instead of the workspace default;
 *  - "leave":    a person asks for leave on one working day (with a comment).
 * Workspace default: workspaces.days_off (set by people with the permission "workspace.daysoff").
 * Own days off: workspace_members.days_off (NULL = follow the workspace). The Owner always follows the workspace.
 * Who may approve: people with "team.approve" - the whole workspace when their data scope is "all", else only their direct reports. Nobody approves their own request.
 * Day status used by reports: Present (signed in) > Leave (approved) > Days off > Absent (pending / rejected / nothing).
 */
final class DaysOff {
    const DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
    const DEFAULT = 'fri', BACK_DAYS = 30, AHEAD_DAYS = 365, COMMENT_MAX = 300;

    private static function now(): string { return gmdate('Y-m-d H:i:s'); }

    // ---------- days off ----------

    /** Any list or "mon,tue" text -> clean "mon,tue" in week order ('' = no days off). */
    static function csv($in): string {
        $in = is_array($in) ? $in : explode(',', (string)$in);
        $set = array_flip(array_map(fn($x) => strtolower(trim((string)$x)), $in));
        return implode(',', array_filter(array_keys(self::DAYS), fn($k) => isset($set[$k])));
    }
    static function listOf(string $csv): array { return $csv === '' ? [] : explode(',', $csv); }
    static function label(string $csv): string { return $csv === '' ? 'None' : implode(', ', array_map('ucfirst', explode(',', $csv))); }
    /** True when the (local) day is one of $days. */
    static function isOff(array $days, DateTimeImmutable $day): bool { return in_array(strtolower($day->format('D')), $days, true); }

    static function workspaceCsv(int $wid): string {
        try { $v = Db::q('SELECT days_off FROM workspaces WHERE id = ?', [$wid])->fetchColumn(); } catch (Throwable $e) { return self::DEFAULT; }
        return $v === false || $v === null ? self::DEFAULT : self::csv($v);
    }
    static function setWorkspace(int $wid, string $csv): void { Db::q('UPDATE workspaces SET days_off = ? WHERE id = ?', [self::csv($csv), $wid]); }

    /** user id => own days off ("" allowed) or null (follows the workspace), for every member of the workspace. */
    static function ownMap(int $wid): array {
        try { $rows = Db::q('SELECT user_id, role, days_off FROM workspace_members WHERE workspace_id = ?', [$wid])->fetchAll(); } catch (Throwable $e) { return []; }
        $o = []; foreach ($rows as $r) $o[(int)$r['user_id']] = ($r['role'] === 'owner' || $r['days_off'] === null) ? null : self::csv($r['days_off']);
        return $o;
    }
    /** The days off that really apply to this person. */
    static function effective(int $wid, int $uid): string { return self::ownMap($wid)[$uid] ?? self::workspaceCsv($wid); }

    // ---------- requests: asking ----------

    /** A person asks to use their own days off. @return string '' on success, else a message */
    static function requestDays(array $ws, int $uid, array $days): string {
        $wid = (int)$ws['id'];
        if ($ws['role'] === 'owner') return 'The Owner follows the workspace days off.';
        $csv = self::csv($days);
        if ($csv === self::effective($wid, $uid)) return 'Those are already your days off.';
        Db::q("DELETE FROM day_requests WHERE workspace_id = ? AND user_id = ? AND kind = 'days_off' AND status = 'pending'", [$wid, $uid]);
        Db::q("INSERT INTO day_requests (workspace_id, user_id, kind, days_off, status, created_at) VALUES (?,?,?,?, 'pending', ?)", [$wid, $uid, 'days_off', $csv, self::now()]);
        Team::audit($wid, $uid, $uid, 'daysoff_requested', 'Days off → ' . self::label($csv));
        return '';
    }

    /** A person asks for leave on a working day. @return string '' on success, else a message */
    static function requestLeave(array $ws, int $uid, string $ymd, string $comment, DateTimeZone $tz): string {
        $wid = (int)$ws['id'];
        if ($ws['role'] === 'owner') return 'The Owner does not need approval for leave.';
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, $tz);
        if (!$d || $d->format('Y-m-d') !== $ymd) return 'Choose a date.';
        $today = new DateTimeImmutable('today', $tz);
        if ($d < $today->modify('-' . self::BACK_DAYS . ' days')) return 'You can ask for leave up to ' . self::BACK_DAYS . ' days back.';
        if ($d > $today->modify('+' . self::AHEAD_DAYS . ' days')) return 'That date is too far ahead.';
        $comment = trim(preg_replace('/\s+/', ' ', $comment));
        if ($comment === '') return 'Write a short reason for the leave.';
        if (mb_strlen($comment) > self::COMMENT_MAX) return 'Keep the reason under ' . self::COMMENT_MAX . ' characters.';
        if (self::isOff(self::listOf(self::effective($wid, $uid)), $d)) return 'That day is already your weekly day off.';
        $a = $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); $b = $d->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if (Db::q('SELECT 1 FROM attendance_sessions WHERE user_id = ? AND sign_in_at >= ? AND sign_in_at < ? LIMIT 1', [$uid, $a, $b])->fetchColumn()) return 'You signed in on that day, so it is not a leave day.';
        if (Db::q("SELECT 1 FROM day_requests WHERE workspace_id = ? AND user_id = ? AND kind = 'leave' AND leave_date = ? AND status IN ('pending','approved')", [$wid, $uid, $ymd])->fetchColumn()) return 'You already asked for leave on that day.';
        Db::q("INSERT INTO day_requests (workspace_id, user_id, kind, leave_date, comment, status, created_at) VALUES (?,?,?,?,?, 'pending', ?)", [$wid, $uid, 'leave', $ymd, $comment, self::now()]);
        Team::audit($wid, $uid, $uid, 'leave_requested', 'Leave on ' . $ymd);
        return '';
    }

    /** Own pending "days off" request, or null. */
    static function pendingDays(int $wid, int $uid): ?array {
        try { return Db::q("SELECT id, days_off, created_at FROM day_requests WHERE workspace_id = ? AND user_id = ? AND kind = 'days_off' AND status = 'pending' ORDER BY id DESC LIMIT 1", [$wid, $uid])->fetch() ?: null; }
        catch (Throwable $e) { return null; }
    }
    /** Own leave requests, newest day first. */
    static function myLeave(int $wid, int $uid, int $limit = 20): array {
        try { return Db::q("SELECT id, leave_date, comment, status, decided_at FROM day_requests WHERE workspace_id = ? AND user_id = ? AND kind = 'leave' ORDER BY leave_date DESC, id DESC LIMIT " . (int)$limit, [$wid, $uid])->fetchAll(); }
        catch (Throwable $e) { return []; }
    }
    /** A person withdraws their own pending request. */
    static function cancel(int $wid, int $uid, int $id): bool {
        return Db::q("DELETE FROM day_requests WHERE id = ? AND workspace_id = ? AND user_id = ? AND status = 'pending'", [$id, $wid, $uid])->rowCount() > 0;
    }

    // ---------- requests: deciding ----------

    /** May the signed-in person (membership $ws, id $actor) decide a request of $target (whose direct manager is $targetManager)? */
    static function canDecide(array $ws, int $actor, int $target, int $targetManager): bool {
        if ($actor === $target || !Perm::can($ws, 'team.approve')) return false;
        return Perm::scope($ws) === 'all' || $targetManager === $actor;
    }

    /** Pending requests the actor may decide: user id => list of [id, kind, text, comment, when]. */
    static function pendingFor(array $ws, int $actor, DateTimeZone $tz): array {
        if (!Perm::can($ws, 'team.approve')) return [];
        try {
            $rows = Db::q("SELECT r.id, r.user_id, r.kind, r.leave_date, r.days_off, r.comment, r.created_at, m.manager_id FROM day_requests r JOIN workspace_members m ON m.workspace_id = r.workspace_id AND m.user_id = r.user_id
                WHERE r.workspace_id = ? AND r.status = 'pending' AND m.status = 'active' ORDER BY r.created_at, r.id", [(int)$ws['id']])->fetchAll();
        } catch (Throwable $e) { return []; }
        $o = [];
        foreach ($rows as $r) {
            if (!self::canDecide($ws, $actor, (int)$r['user_id'], (int)$r['manager_id'])) continue;
            $leave = $r['kind'] === 'leave';
            $o[(int)$r['user_id']][] = ['id' => (int)$r['id'], 'kind' => $r['kind'], 'title' => $leave ? 'Leave on ' . (new DateTimeImmutable($r['leave_date']))->format(Account::df()) : 'Weekly days off',
                'text' => $leave ? (string)$r['comment'] : 'Wants: ' . self::label((string)$r['days_off']), 'when' => Ui::day($r['created_at'], $tz)];
        }
        return $o;
    }

    /** Approve or reject. @return string '' on success, else a message */
    static function decide(array $ws, int $actor, int $id, string $do): string {
        $wid = (int)$ws['id']; if (!in_array($do, ['approve', 'reject'], true)) return 'Choose approve or reject.';
        $r = Db::q("SELECT r.*, m.manager_id, m.status mstatus, u.name FROM day_requests r JOIN workspace_members m ON m.workspace_id = r.workspace_id AND m.user_id = r.user_id JOIN users u ON u.id = r.user_id
            WHERE r.id = ? AND r.workspace_id = ? AND r.status = 'pending'", [$id, $wid])->fetch();
        if (!$r) return 'That request was already handled.';
        if (!self::canDecide($ws, $actor, (int)$r['user_id'], (int)$r['manager_id'])) return 'You cannot decide this request.';
        $ok = $do === 'approve';
        $n = Db::q("UPDATE day_requests SET status = ?, decided_by = ?, decided_at = ? WHERE id = ? AND status = 'pending'", [$ok ? 'approved' : 'rejected', $actor, self::now(), $id])->rowCount();
        if (!$n) return 'That request was already handled.';
        if ($ok && $r['kind'] === 'days_off') Db::q('UPDATE workspace_members SET days_off = ? WHERE workspace_id = ? AND user_id = ?', [self::csv((string)$r['days_off']), $wid, (int)$r['user_id']]);
        $what = $r['kind'] === 'leave' ? 'Leave on ' . $r['leave_date'] : 'Days off → ' . self::label((string)$r['days_off']);
        Team::audit($wid, $actor, (int)$r['user_id'], $ok ? 'request_approved' : 'request_rejected', $what);
        return '';
    }

    // ---------- for reports ----------

    /** Leave of the given people between two dates (Y-m-d, inclusive): user id => date => ['status' => pending|approved|rejected, 'comment']. */
    static function leaveMap(int $wid, array $uids, string $from, string $to): array {
        if (!$uids) return [];
        try {
            $rows = Db::q("SELECT user_id, leave_date, status, comment FROM day_requests WHERE workspace_id = ? AND kind = 'leave' AND leave_date BETWEEN ? AND ? AND user_id IN (" . implode(',', array_fill(0, count($uids), '?')) . ') ORDER BY id', array_merge([$wid, $from, $to], array_map('intval', $uids)))->fetchAll();
        } catch (Throwable $e) { return []; }
        $o = []; foreach ($rows as $r) $o[(int)$r['user_id']][$r['leave_date']] = ['status' => $r['status'], 'comment' => (string)$r['comment']];
        return $o;
    }
}
