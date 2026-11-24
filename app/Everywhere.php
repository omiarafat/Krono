<?php
/**
 * Personal workspace combined view: the signed-in person's OWN work in their personal workspace plus the workspaces they chose to link to it.
 * Only their own tasks, projects and saved time are read, and only from workspaces where their role may open that page.
 * Nothing about other people is included. Leaving or being deactivated in a workspace removes it from these lists.
 */
final class Everywhere {
    const ACTIVE = "'planning','in_progress'";

    /** Workspaces linked to a personal workspace that the person is STILL an active member of. @return int[] */
    static function linkedIds(int $personalId, int $uid): array {
        try { return array_map('intval', Db::q("SELECT l.linked_workspace_id FROM workspace_links l JOIN workspace_members m ON m.workspace_id = l.linked_workspace_id AND m.user_id = ? AND m.status = 'active'
            WHERE l.personal_workspace_id = ? AND l.user_id = ?", [$uid, $personalId, $uid])->fetchAll(PDO::FETCH_COLUMN)); }
        catch (Throwable $e) { return []; }
    }
    /** The personal workspace the person is in right now, or null (company workspaces never combine). */
    private static function personal(int $uid): ?array { $w = Workspace::current(); return $w && ($w['type'] ?? '') === 'personal' && ($w['role'] ?? '') === 'owner' ? $w : null; }
    /** True when the current workspace is a personal one that has linked workspaces. */
    static function active(int $uid): bool { $p = self::personal($uid); return $p && self::linkedIds((int)$p['id'], $uid) !== []; }
    /** Combined view on: personal + linked workspaces (the current personal one first). Otherwise just the current workspace. */
    static function spaces(int $uid): array {
        $p = self::personal($uid); if (!$p) return [];
        $ids = array_merge([(int)$p['id']], self::linkedIds((int)$p['id'], $uid)); $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')';
        try { return Db::q("SELECT w.id, w.name, w.type, w.timezone, m.role, m.custom_role_id FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id WHERE m.user_id = ? AND m.status = 'active' AND w.id IN $in ORDER BY (w.id = ?) DESC, w.name, w.id", array_merge([$uid], $ids, [(int)$p['id']]))->fetchAll(); }
        catch (Throwable $e) { return Db::q("SELECT w.id, w.name, w.type, w.timezone, m.role FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id WHERE m.user_id = ? AND m.status = 'active' AND w.id IN $in ORDER BY (w.id = ?) DESC, w.name", array_merge([$uid], $ids, [(int)$p['id']]))->fetchAll(); }
    }
    /** Other workspaces that can be linked (everything the person belongs to except the personal one) with whether each is linked now. */
    static function candidates(int $uid): array {
        $p = self::personal($uid); if (!$p) return [];
        $on = self::linkedIds((int)$p['id'], $uid);
        return array_map(fn($w) => $w + ['linked' => in_array((int)$w['id'], $on, true)], array_values(array_filter(Workspace::mine($uid), fn($w) => (int)$w['id'] !== (int)$p['id'])));
    }
    /** Add or remove a link. @return string '' on success, else a message */
    static function setLink(int $uid, int $wid, bool $on): string {
        $p = self::personal($uid); if (!$p) return 'Open your personal workspace first.';
        if ($wid === (int)$p['id']) return 'That is your personal workspace.';
        if (!Db::q("SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND status = 'active'", [$wid, $uid])->fetchColumn()) return 'You are not a member of that workspace.';
        if ($on) Db::q('INSERT IGNORE INTO workspace_links (personal_workspace_id, linked_workspace_id, user_id) VALUES (?,?,?)', [(int)$p['id'], $wid, $uid]);
        else Db::q('DELETE FROM workspace_links WHERE personal_workspace_id = ? AND linked_workspace_id = ? AND user_id = ?', [(int)$p['id'], $wid, $uid]);
        return '';
    }
    private static function utc(DateTimeImmutable $d): string { return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
    private static function allowed(array $spaces, string $key): array { return array_values(array_filter($spaces, fn($w) => Perm::can($w, $key))); }

    /** One row per workspace: my active projects, open and overdue tasks, saved time this month. Each figure only where the page behind it is allowed. */
    static function summary(int $uid, ?DateTimeImmutable $now = null): array {
        $out = [];
        foreach (self::spaces($uid) as $w) {
            $wid = (int)$w['id']; $tz = Workspace::tz($w); $n = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone($tz); $today = $n->setTime(0, 0);
            $m0 = $today->modify('first day of this month'); $row = ['ws' => $w, 'projects' => null, 'open' => null, 'overdue' => null, 'month' => null];
            if (Perm::can($w, 'projects.view')) $row['projects'] = (int)Db::q('SELECT COUNT(*) FROM projects p WHERE p.workspace_id = ? AND p.archived_at IS NULL AND p.status IN (' . self::ACTIVE . ')
                AND (p.created_by = ? OR p.leader_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))', [$wid, $uid, $uid, $uid])->fetchColumn();
            if (Perm::can($w, 'tasks.view')) {
                $r = Db::q("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN t.due_date IS NOT NULL AND t.due_date < ? THEN 1 ELSE 0 END), 0) o FROM tasks t JOIN projects p ON p.id = t.project_id
                    WHERE p.workspace_id = ? AND p.archived_at IS NULL AND t.assignee_id = ? AND t.status <> 'completed'", [$today->format('Y-m-d'), $wid, $uid])->fetch();
                $row['open'] = (int)$r['c']; $row['overdue'] = (int)$r['o'];
            }
            if (Perm::can($w, 'time.view')) $row['month'] = (int)Db::q('SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE p.workspace_id = ? AND l.user_id = ? AND l.started_at >= ? AND l.started_at < ?',
                [$wid, $uid, self::utc($m0), self::utc($m0->modify('+1 month'))])->fetchColumn();
            $out[] = $row;
        }
        return $out;
    }

    /** My open tasks, grouped by workspace: [ [ws, tasks[]] ... ] (earliest due date first, no date last). */
    static function tasks(int $uid, int $perSpace = 100): array {
        $out = [];
        foreach (self::allowed(self::spaces($uid), 'tasks.view') as $w) {
            $rows = Db::q("SELECT t.id, t.title, t.status, t.priority, t.due_date, p.id project_id, p.name project FROM tasks t JOIN projects p ON p.id = t.project_id
                WHERE p.workspace_id = ? AND p.archived_at IS NULL AND t.assignee_id = ? AND t.status <> 'completed' ORDER BY (t.due_date IS NULL), t.due_date, t.id LIMIT " . (int)$perSpace, [(int)$w['id'], $uid])->fetchAll();
            $out[] = ['ws' => $w, 'tasks' => $rows, 'today' => (new DateTimeImmutable('now', Workspace::tz($w)))->format('Y-m-d')];
        }
        return $out;
    }

    /** My latest saved time logs over all allowed workspaces, newest first, plus the total of each workspace this week. */
    static function logs(int $uid, int $limit = 50): array {
        $spaces = self::allowed(self::spaces($uid), 'time.view'); if (!$spaces) return ['rows' => [], 'week' => []];
        $ids = array_map(fn($w) => (int)$w['id'], $spaces); $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $rows = Db::q("SELECT l.started_at, l.duration_seconds, l.work_description, p.workspace_id, p.name project, t.title task FROM task_time_logs l JOIN projects p ON p.id = l.project_id JOIN tasks t ON t.id = l.task_id
            WHERE l.user_id = ? AND p.workspace_id IN $in ORDER BY l.started_at DESC, l.id DESC LIMIT " . (int)$limit, array_merge([$uid], $ids))->fetchAll();
        $week = [];
        foreach ($spaces as $w) {
            $tz = Workspace::tz($w); $s = Workspace::weekStart((new DateTimeImmutable('now', $tz))->setTime(0, 0));
            $week[(int)$w['id']] = (int)Db::q('SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l JOIN projects p ON p.id = l.project_id WHERE p.workspace_id = ? AND l.user_id = ? AND l.started_at >= ? AND l.started_at < ?', [(int)$w['id'], $uid, self::utc($s), self::utc($s->modify('+7 days'))])->fetchColumn();
        }
        $names = []; foreach ($spaces as $w) $names[(int)$w['id']] = ['name' => $w['name'], 'tz' => Workspace::tz($w)];
        foreach ($rows as &$r) { $x = $names[(int)$r['workspace_id']]; $r['ws'] = $x['name']; $r['day'] = (new DateTimeImmutable($r['started_at'], new DateTimeZone('UTC')))->setTimezone($x['tz'])->format(Account::df()); } unset($r);
        return ['rows' => $rows, 'week' => $week, 'names' => $names];
    }

    /** A small POST form that makes a workspace current and then opens $next (an allowed own page). */
    static function openForm(int $wid, string $next, string $label, string $cls = 'btn h-9'): string {
        return '<form method="post" action="' . url('/workspaces/switch') . '" class="m-0">' . csrf_field() . '<input type="hidden" name="workspace_id" value="' . $wid . '"><input type="hidden" name="next" value="' . e($next) . '"><button type="submit" class="' . $cls . '">' . e($label) . '</button></form>';
    }
}
