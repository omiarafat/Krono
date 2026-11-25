<?php
/**
 * Dashboard "Overview" tab for Owner / Admin (and Manager or custom roles with dashboard.view_overview, limited to their data scope).
 * One call returns every widget; a widget that fails comes back as ['error' => true] so the others still render.
 * Everything is limited to the people in TimeLog::people() (the same data scope as Reports) and each widget needs its own page permission.
 * "Changes requested" and the Board tag from the plan need the Boards stage and are not shown yet.
 */
final class Overview {
    const RANGES = ['today' => 'Today', 'week' => 'This week', 'month' => 'This month'];
    const REVIEW_DAYS = 2;

    static function available(array $ws): bool { return ($ws['type'] ?? '') !== 'personal' && Perm::can($ws, 'dashboard.view_overview'); }
    static function range(string $r): string { return isset(self::RANGES[$r]) ? $r : 'week'; }
    private static function utc(DateTimeImmutable $d): string { return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private static function in(array $ids): string { return '(' . implode(',', array_fill(0, max(1, count($ids)), '?')) . ')'; }
    private static function safe(callable $f) { try { return $f(); } catch (Throwable $e) { error_log((string)$e); return ['error' => true]; } }

    /** [start, end, previous start, previous end] in the workspace time zone. */
    static function bounds(string $range, DateTimeImmutable $today): array {
        if ($range === 'today') return [$today, $today->modify('+1 day'), $today->modify('-1 day'), $today];
        if ($range === 'month') { $s = $today->modify('first day of this month'); return [$s, $s->modify('+1 month'), $s->modify('-1 month'), $s]; }
        $s = Workspace::weekStart($today); return [$s, $s->modify('+7 days'), $s->modify('-7 days'), $s];
    }

    static function data(int $uid, array $ws, string $range = 'week', ?DateTimeImmutable $now = null): array {
        $range = self::range($range); $tz = Workspace::tz($ws); $wid = (int)$ws['id'];
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz); $today = $now->setTime(0, 0); $ymd = $today->format('Y-m-d');
        [$from, $to, $pFrom, $pTo] = self::bounds($range, $today);
        $people = TimeLog::people($uid, $ws); $ids = array_map('intval', array_keys($people)); if (!$ids) $ids = [$uid];
        $all = Perm::scope($ws) === 'all'; $in = self::in($ids);
        $can = ['attend' => Perm::can($ws, 'attendance.view'), 'time' => Perm::can($ws, 'time.view'), 'tasks' => Perm::can($ws, 'tasks.view'), 'projects' => Perm::can($ws, 'projects.view'),
                'invite' => Perm::can($ws, 'team.invite'), 'team' => Perm::can($ws, 'team.view'), 'approve' => Perm::can($ws, 'team.approve'), 'audit' => Perm::can($ws, 'workspace.audit'), 'export' => Perm::can($ws, 'reports.export')];
        // tasks of the workspace that belong to the scope: everything for "whole workspace", else tasks assigned to the people in scope
        $tw = 'p.workspace_id = ? AND p.archived_at IS NULL' . ($all ? '' : " AND t.assignee_id IN $in"); $targs = array_merge([$wid], $all ? [] : $ids);
        $out = ['range' => $range, 'rangeLabel' => self::RANGES[$range], 'from' => $from->format('Y-m-d'), 'to' => $to->modify('-1 day')->format('Y-m-d'), 'can' => $can, 'size' => count($ids), 'scope' => Perm::scope($ws), 'now' => $now->format('g:i A')];
        $names = $people;

        $out['attendance'] = $can['attend'] ? self::safe(function () use ($ids, $in, $today, $ymd, $now, $wid, $names) {
            $a = self::utc($today); $b = self::utc($today->modify('+1 day')); $nowUtc = self::utc($now);
            $sess = Db::q("SELECT id, user_id, sign_in_at, sign_out_at, work_location FROM attendance_sessions WHERE user_id IN $in AND sign_in_at >= ? AND sign_in_at < ? ORDER BY sign_in_at", array_merge($ids, [$a, $b]))->fetchAll();
            $br = []; if ($sess) { $sid = array_map(fn($s) => (int)$s['id'], $sess); foreach (Db::q('SELECT session_id, start_at, end_at FROM attendance_breaks WHERE session_id IN ' . self::in($sid), $sid)->fetchAll() as $x) $br[(int)$x['session_id']][] = $x; }
            $by = []; foreach ($sess as $s) { $c = Attendance::calc($s, $br[(int)$s['id']] ?? [], strtotime($nowUtc . ' UTC')); $u = (int)$s['user_id']; $r = &$by[$u];
                $r['net'] = ($r['net'] ?? 0) + $c['net']; $r['first'] ??= $s['sign_in_at']; $r['loc'] = $s['work_location'] ?? '';
                if ($c['active']) { $r['live'] = true; foreach ($br[(int)$s['id']] ?? [] as $x) if (!$x['end_at']) $r['break'] = true; } unset($r); }
            $leave = DaysOff::leaveMap($wid, $ids, $ymd, $ymd); $roles = self::roles($wid, $ids);
            $rows = []; $c = ['working' => 0, 'break' => 0, 'absent' => 0, 'expected' => 0, 'leave' => 0];
            foreach ($ids as $id) {
                $off = DaysOff::isOff(DaysOff::listOf(DaysOff::effective($wid, $id)), $today); $r = $by[$id] ?? null; $lv = ($leave[$id][$ymd]['status'] ?? '') === 'approved';
                if (!$off && !$lv) $c['expected']++;
                if ($r && !empty($r['live'])) { if (!empty($r['break'])) { $st = 'On break'; $c['break']++; } else { $st = 'Working'; $c['working']++; } }
                elseif ($r) $st = 'Signed out';
                elseif ($lv) { $st = 'Leave'; $c['leave']++; }
                elseif ($off) $st = 'Non-working day';
                else { $st = 'Absent'; $c['absent']++; }
                $rows[] = ['id' => $id, 'name' => $names[$id] ?? 'Member', 'role' => $roles[$id] ?? 'Member', 'status' => $st, 'net' => (int)($r['net'] ?? 0), 'in' => $r ? (new DateTimeImmutable($r['first'], new DateTimeZone('UTC')))->setTimezone($now->getTimezone())->format('g:i A') : '', 'loc' => $r['loc'] ?? ''];
            }
            $rank = ['Working' => 0, 'On break' => 1, 'Signed out' => 2, 'Absent' => 3, 'Leave' => 4, 'Non-working day' => 5]; usort($rows, fn($x, $y) => [$rank[$x['status']], $x['name']] <=> [$rank[$y['status']], $y['name']]);
            return ['rows' => $rows] + $c;
        }) : null;

        $out['time'] = $can['time'] ? self::safe(function () use ($ids, $in, $wid, $from, $to, $pFrom, $pTo, $range, $tz) {
            $q = "SELECT l.user_id, l.project_id, l.started_at, l.duration_seconds FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE p.workspace_id = ? AND l.user_id IN $in AND l.started_at >= ? AND l.started_at < ? LIMIT 50000";
            $rows = Db::q($q, array_merge([$wid], $ids, [self::utc($from), self::utc($to)]))->fetchAll();
            $prev = (int)Db::q("SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE p.workspace_id = ? AND l.user_id IN $in AND l.started_at >= ? AND l.started_at < ?", array_merge([$wid], $ids, [self::utc($pFrom), self::utc($pTo)]))->fetchColumn();
            $bars = []; $byUser = []; $byProject = []; $total = 0; $utc = new DateTimeZone('UTC');
            if ($range === 'today') { for ($h = 6; $h <= 22; $h++) $bars[$h] = [($h % 12 ?: 12) . ($h < 12 ? 'a' : 'p'), 0]; }
            elseif ($range === 'month') { for ($d = $from; $d < $to; $d = $d->modify('+1 day')) $bars[$d->format('Y-m-d')] = [$d->format('j'), 0]; }
            else { for ($d = $from; $d < $to; $d = $d->modify('+1 day')) $bars[$d->format('Y-m-d')] = [$d->format('D'), 0]; }
            foreach ($rows as $r) {
                $sec = (int)$r['duration_seconds']; $total += $sec; $byUser[(int)$r['user_id']] = ($byUser[(int)$r['user_id']] ?? 0) + $sec; $byProject[(int)$r['project_id']] = ($byProject[(int)$r['project_id']] ?? 0) + $sec;
                $l = (new DateTimeImmutable($r['started_at'], $utc))->setTimezone($tz); $k = $range === 'today' ? min(22, max(6, (int)$l->format('G'))) : $l->format('Y-m-d');
                if (isset($bars[$k])) $bars[$k][1] += $sec;
            }
            return ['bars' => array_values($bars), 'total' => $total, 'prev' => $prev, 'pct' => $prev > 0 ? (int)round(100 * ($total - $prev) / $prev) : null, 'byUser' => $byUser, 'byProject' => $byProject, 'max' => max(1, ...array_column($bars, 1))];
        }) : null;

        $out['tasks'] = $can['tasks'] ? self::safe(function () use ($tw, $targs, $ymd) {
            $r = Db::q("SELECT COUNT(*) open, COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < ? THEN 1 ELSE 0 END), 0) overdue, COALESCE(SUM(CASE WHEN t.status = 'review' THEN 1 ELSE 0 END), 0) review
                FROM tasks t JOIN projects p ON p.id = t.project_id WHERE $tw AND t.status <> 'completed'", array_merge([$ymd], $targs))->fetch();
            $old = (int)Db::q("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id = t.project_id WHERE $tw AND t.status = 'review' AND t.updated_at < ?", array_merge($targs, [self::utc(new DateTimeImmutable('now - ' . self::REVIEW_DAYS . ' days', new DateTimeZone('UTC')))]))->fetchColumn();
            return ['open' => (int)$r['open'], 'overdue' => (int)$r['overdue'], 'review' => (int)$r['review'], 'reviewOld' => $old];
        }) : null;

        $out['projects'] = $can['projects'] ? self::safe(function () use ($wid, $all, $ids, $in, $ymd) {
            $pw = 'p.workspace_id = ? AND p.archived_at IS NULL AND p.status IN (\'planning\',\'in_progress\')' . ($all ? '' : " AND (p.leader_id IN $in OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id IN $in))");
            $args = array_merge([$wid], $all ? [] : array_merge($ids, $ids));
            $rows = Db::q("SELECT p.id, p.name, p.due_date, (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) total, (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completed') done,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status <> 'completed' AND t.due_date IS NOT NULL AND t.due_date < ?) overdue FROM projects p WHERE $pw ORDER BY (p.due_date IS NULL), p.due_date, p.id LIMIT 40", array_merge([$ymd], $args))->fetchAll();
            $past = 0; $list = []; foreach ($rows as $r) { $late = $r['due_date'] && $r['due_date'] < $ymd; if ($late) $past++; $list[] = ['id' => (int)$r['id'], 'name' => $r['name'], 'pct' => $r['total'] ? (int)round(100 * $r['done'] / $r['total']) : 0, 'overdue' => (int)$r['overdue'], 'late' => $late, 'total' => (int)$r['total']]; }
            return ['rows' => array_slice($list, 0, 5), 'past' => $past, 'pastName' => $past ? ($list[array_search(true, array_column($list, 'late'))]['name'] ?? '') : ''];
        }) : null;
        if (is_array($out['projects']) && is_array($out['time'] ?? null) && empty($out['projects']['error'])) {   // tracked time per listed project
            foreach ($out['projects']['rows'] as &$r) $r['tracked'] = (int)($out['time']['byProject'][$r['id']] ?? 0); unset($r);
        }

        $out['workload'] = $can['tasks'] ? self::safe(function () use ($wid, $ids, $in, $ymd, $names, $out) {
            $rows = Db::q("SELECT t.assignee_id id, SUM(t.status <> 'completed') open, SUM(t.status = 'review') review, SUM(t.status <> 'completed' AND t.due_date IS NOT NULL AND t.due_date < ?) overdue
                FROM tasks t JOIN projects p ON p.id = t.project_id WHERE p.workspace_id = ? AND p.archived_at IS NULL AND t.assignee_id IN $in GROUP BY t.assignee_id", array_merge([$ymd, $wid], $ids))->fetchAll();
            $by = []; foreach ($rows as $r) $by[(int)$r['id']] = $r; $list = [];
            foreach ($ids as $id) { $r = $by[$id] ?? null; $list[] = ['id' => $id, 'name' => $names[$id] ?? 'Member', 'open' => (int)($r['open'] ?? 0), 'review' => (int)($r['review'] ?? 0), 'overdue' => (int)($r['overdue'] ?? 0), 'tracked' => (int)($out['time']['byUser'][$id] ?? 0)]; }
            usort($list, fn($a, $b) => [$b['open'], $b['tracked'], $a['name']] <=> [$a['open'], $a['tracked'], $b['name']]); return array_slice($list, 0, 6);
        }) : null;

        $out['attention'] = self::safe(function () use ($out, $can, $wid, $tz, $ws, $uid) {
            $rows = [];
            $t = $out['tasks'] ?? null; if ($t && empty($t['error'])) {
                if ($t['overdue']) $rows[] = ['time', 'Overdue tasks', 'Due date passed, not completed', $t['overdue'], '/tasks', 'red'];
                if ($t['review']) $rows[] = ['eye', 'Waiting for review', $t['reviewOld'] ? $t['reviewOld'] . ' for more than ' . self::REVIEW_DAYS . ' days' : 'In review', $t['review'], '/tasks?status=review', 'amber'];
            }
            $a = $out['attendance'] ?? null; if ($a && empty($a['error']) && $a['absent']) $rows[] = ['user', 'Absent today', 'No attendance and not a non-working day', $a['absent'], '/attendance', 'red'];
            if ($can['approve'] || $can['team']) { $n = array_sum(array_map('count', DaysOff::pendingFor($ws, $uid, $tz))); if ($n) $rows[] = ['file', 'Requests to review', 'Leave or days-off requests waiting', $n, '/teams', 'amber']; }
            if ($can['invite']) { $u0 = new DateTimeZone('UTC'); $nw = (new DateTimeImmutable('now', $u0))->format('Y-m-d H:i:s'); $d1 = (new DateTimeImmutable('now +1 day', $u0))->format('Y-m-d H:i:s');
                $n = (int)Db::q("SELECT COUNT(*) FROM workspace_invitations WHERE workspace_id = ? AND status = 'pending' AND expires_at > ?", [$wid, $nw])->fetchColumn();
                if ($n) { $soon = (int)Db::q("SELECT COUNT(*) FROM workspace_invitations WHERE workspace_id = ? AND status = 'pending' AND expires_at > ? AND expires_at < ?", [$wid, $nw, $d1])->fetchColumn(); $rows[] = ['mail', 'Pending invitations', $soon ? $soon . ' expires within a day' : 'Waiting to be accepted', $n, '/teams', 'green']; } }
            $p = $out['projects'] ?? null; if ($p && empty($p['error']) && $p['past']) $rows[] = ['projects', 'Project past its due date', $p['pastName'], $p['past'], '/projects', 'red'];
            return $rows;
        });

        $out['activity'] = self::safe(function () use ($wid, $ids, $in, $all, $can, $tz, $names) {
            $ev = []; $utc = new DateTimeZone('UTC');
            foreach (Db::q("SELECT a.type, a.subject, a.user_id, a.created_at, p.name project FROM activity_log a JOIN projects p ON p.id = a.project_id WHERE a.workspace_id = ?" . ($all ? '' : " AND a.user_id IN $in") . ' ORDER BY a.created_at DESC, a.id DESC LIMIT 8', array_merge([$wid], $all ? [] : $ids))->fetchAll() as $r) {
                $what = ['project_created' => 'created project', 'task_created' => 'added task', 'task_completed' => 'completed'][$r['type']] ?? str_replace('_', ' ', (string)$r['type']);
                $ev[] = ['who' => $names[(int)$r['user_id']] ?? 'Someone', 'what' => $what, 'what2' => $r['type'] === 'project_created' ? $r['project'] : (string)$r['subject'], 'at' => $r['created_at']];
            }
            if ($can['audit']) foreach (Db::q('SELECT a.action, a.detail, u.name actor, a.created_at FROM audit_log a LEFT JOIN users u ON u.id = a.actor_id WHERE a.workspace_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT 8', [$wid])->fetchAll() as $r)
                $ev[] = ['who' => $r['actor'] ?: 'Someone', 'what' => strtolower(WsSettings::ACTIONS[$r['action']] ?? str_replace('_', ' ', (string)$r['action'])), 'what2' => (string)$r['detail'], 'at' => $r['created_at']];
            usort($ev, fn($a, $b) => strcmp($b['at'], $a['at'])); $ev = array_slice($ev, 0, 6);
            foreach ($ev as &$e) $e['ago'] = self::ago($e['at']); unset($e);
            return $ev;
        });
        return $out;
    }

    static function ago(string $utc): string {
        $s = max(0, time() - strtotime($utc . ' UTC')); if ($s < 90) return 'just now'; if ($s < 3600) return intdiv($s, 60) . ' min ago'; if ($s < 86400) return intdiv($s, 3600) . ' h ago'; return intdiv($s, 86400) . ' d ago';
    }
    /** Role names of the people in scope, for the "Who's working now" list. */
    private static function roles(int $wid, array $ids): array {
        $o = []; try { foreach (Db::q('SELECT m.user_id, m.role, m.custom_role_id, r.name custom FROM workspace_members m LEFT JOIN workspace_roles r ON r.id = m.custom_role_id WHERE m.workspace_id = ? AND m.user_id IN ' . self::in($ids), array_merge([$wid], $ids))->fetchAll() as $r) $o[(int)$r['user_id']] = Perm::label((string)$r['role'], $r['custom_role_id'], $r['custom']); } catch (Throwable $e) {}
        return $o;
    }

    /** Segmented control "Overview | My work" (shown to people who may open the Overview). */
    static function tabsHtml(string $tab): string {
        $h = '<div class="inline-flex rounded-xl border border-line bg-raised p-1" role="group" aria-label="Dashboard view">';
        foreach (['overview' => 'Overview', 'mine' => 'My work'] as $k => $l)
            $h .= '<a href="' . url('/dashboard?tab=' . $k) . '" class="rounded-lg px-3.5 py-1.5 text-tiny font-bold no-underline ' . ($tab === $k ? 'bg-brand text-on-brand' : 'text-muted hover:text-ink') . '"' . ($tab === $k ? ' aria-current="true"' : '') . '>' . $l . '</a>';
        return $h . '</div>';
    }
}
