<?php
/**
 * Projects (list, create, edit, archive). One place for the rules, used by the website now and the app API later.
 *
 *  - Who sees what : Owner / Admin / Manager see every project of the workspace.
 *                    Member / Viewer see only projects they created, lead or were added to.
 *  - Who may change: Owner / Admin / Manager create, edit and archive. Member / Viewer are read-only.
 *  - Progress      : completed tasks / all tasks of the project (no tasks = 0%). Same rule as the Dashboard.
 *  - Logged time   : sum of saved time logs (task_time_logs) of the project, every user.
 *  - Archive       : hides the project from active lists. Tasks and time logs are kept. Nothing is ever deleted.
 */
final class Project {
    const STATUS = ['upcoming' => 'Upcoming', 'planning' => 'Planning', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'completed' => 'Completed'];
    const PRIORITY = ['low' => 'Low', 'normal' => 'Normal', 'medium' => 'Medium', 'high' => 'High'];
    const SORT = ['updated' => 'Recently updated', 'newest' => 'Newest first', 'name' => 'Name A–Z', 'due' => 'Due date'];
    const PER_PAGE = 8;

    static function canManage(array $ws): bool { return Workspace::atLeast($ws['role'], 'manager'); }

    private static function utcNow(): string { return gmdate('Y-m-d H:i:s'); }

    /** SQL (alias p) for "projects this person can see" + bound values. */
    private static function visible(int $uid, array $ws): array {
        $sql = 'p.workspace_id = ?'; $v = [(int)$ws['id']];
        if (!self::canManage($ws)) { $sql .= ' AND (p.created_by = ? OR p.leader_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))'; array_push($v, $uid, $uid, $uid); }
        return [$sql, $v];
    }

    /** One project the person may see (or null). */
    static function find(int $id, int $uid, array $ws): ?array {
        [$vis, $v] = self::visible($uid, $ws);
        $r = Db::q("SELECT p.* FROM projects p WHERE p.id = ? AND $vis", array_merge([$id], $v))->fetch();
        return $r ?: null;
    }

    static function cleanFilters(array $q): array {
        $status = (string)($q['status'] ?? 'all'); if ($status !== 'archived' && !isset(self::STATUS[$status])) $status = 'all';
        $sort = (string)($q['sort'] ?? 'updated'); if (!isset(self::SORT[$sort])) $sort = 'updated';
        return ['q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 100), 'status' => $status, 'sort' => $sort, 'page' => max(1, (int)($q['page'] ?? 1))];
    }

    /** @return array{items:array,total:int,pages:int,page:int} */
    static function listing(int $uid, array $ws, array $f): array {
        [$vis, $v] = self::visible($uid, $ws);
        $where = $vis; $args = $v;
        $where .= $f['status'] === 'archived' ? ' AND p.archived_at IS NOT NULL' : ' AND p.archived_at IS NULL';
        if (isset(self::STATUS[$f['status']])) { $where .= ' AND p.status = ?'; $args[] = $f['status']; }
        if ($f['q'] !== '') { $where .= " AND p.name LIKE ? ESCAPE '!'"; $args[] = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'; }
        $total = (int)Db::q("SELECT COUNT(*) FROM projects p WHERE $where", $args)->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        $order = ['updated' => 'p.updated_at DESC, p.id DESC', 'newest' => 'p.created_at DESC, p.id DESC', 'name' => 'p.name ASC, p.id ASC', 'due' => '(p.due_date IS NULL) ASC, p.due_date ASC, p.id ASC'][$f['sort']];
        $rows = Db::q("SELECT p.*,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) tasks_total,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'completed') tasks_done,
                (SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l WHERE l.project_id = p.id) logged,
                (SELECT COUNT(*) FROM task_time_logs l WHERE l.project_id = p.id) logs_count
              FROM projects p WHERE $where ORDER BY $order LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $args)->fetchAll();
        $ids = array_column($rows, 'id'); $people = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (['leader_id', 'created_by'] as $col)   // leader first, then the creator, then the other members
                foreach (Db::q("SELECT p.id pid, u.id, u.name FROM projects p JOIN users u ON u.id = p.$col WHERE p.id IN ($in)", $ids)->fetchAll() as $m) $people[$m['pid']] = ($people[$m['pid']] ?? []) + [$m['id'] => $m['name']];
            foreach (Db::q("SELECT pm.project_id, u.id, u.name FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.project_id IN ($in) ORDER BY pm.added_at, u.id", $ids)->fetchAll() as $m) $people[$m['project_id']][$m['id']] ??= $m['name'];
        }
        $items = [];
        foreach ($rows as $r) {
            $t = (int)$r['tasks_total']; $d = (int)$r['tasks_done']; $names = array_values($people[$r['id']] ?? []);
            $items[] = $r + ['percent' => $t > 0 ? (int)round(100 * $d / $t) : 0, 'initials' => array_map([self::class, 'initials'], array_slice($names, 0, 2)), 'more' => max(0, count($names) - 2)];
        }
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    static function initials(string $name): string {
        $w = preg_split('/\s+/u', trim($name)) ?: [];
        $s = mb_substr($w[0] ?? '', 0, 1) . (count($w) > 1 ? mb_substr(end($w), 0, 1) : mb_substr($w[0] ?? '', 1, 1));
        return mb_strtoupper($s) ?: '?';
    }

    /** Checks the form values. @return array{0:array,1:array} [clean values, errors by field] */
    static function validate(array $in): array {
        $v = ['name' => trim((string)($in['name'] ?? '')), 'description' => trim((string)($in['description'] ?? '')), 'start_date' => trim((string)($in['start_date'] ?? '')),
              'due_date' => trim((string)($in['due_date'] ?? '')), 'status' => (string)($in['status'] ?? 'planning'), 'priority' => (string)($in['priority'] ?? 'normal')];
        $err = [];
        if ($v['name'] === '') $err['name'] = 'This field is required.'; elseif (mb_strlen($v['name']) > 150) $err['name'] = 'Use 150 characters or fewer.';
        if (mb_strlen($v['description']) > 2000) $err['description'] = 'Use 2000 characters or fewer.';
        $ok = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
        if ($v['start_date'] === '') $err['start_date'] = 'This field is required.'; elseif (!$ok($v['start_date'])) $err['start_date'] = 'Enter a valid date.';
        if ($v['due_date'] !== '') { if (!$ok($v['due_date'])) $err['due_date'] = 'Enter a valid date.'; elseif (!isset($err['start_date']) && $v['due_date'] < $v['start_date']) $err['due_date'] = 'Due date cannot be before the start date.'; }
        if (!isset(self::STATUS[$v['status']])) $err['status'] = 'Choose a status.';
        if (!isset(self::PRIORITY[$v['priority']])) $err['priority'] = 'Choose a priority.';
        return [$v, $err];
    }

    static function create(int $uid, array $ws, array $v): int {
        $now = self::utcNow(); $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            Db::q('INSERT INTO projects (workspace_id, name, description, status, priority, start_date, due_date, leader_id, created_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [(int)$ws['id'], $v['name'], $v['description'] ?: null, $v['status'], $v['priority'], $v['start_date'], $v['due_date'] ?: null, $uid, $uid, $now, $now]);
            $id = (int)$pdo->lastInsertId();
            Db::q('INSERT INTO project_members (project_id, user_id, added_at) VALUES (?,?,?)', [$id, $uid, $now]);   // the leader is a member too
            $pdo->commit();
            Activity::log((int)$ws['id'], $id, $uid, 'project_created');
            return $id;
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }

    /** $old = the project row before the change (to notice a status change in the feed). */
    static function update(array $old, array $v, int $uid): void {
        $id = (int)$old['id'];
        Db::q('UPDATE projects SET name = ?, description = ?, status = ?, priority = ?, start_date = ?, due_date = ?, updated_at = ? WHERE id = ?',
            [$v['name'], $v['description'] ?: null, $v['status'], $v['priority'], $v['start_date'], $v['due_date'] ?: null, self::utcNow(), $id]);
        if ($old['status'] !== $v['status']) Activity::log((int)$old['workspace_id'], $id, $uid, 'status_changed', self::STATUS[$v['status']]);
        else Activity::log((int)$old['workspace_id'], $id, $uid, 'project_updated');
    }

    /** Archive: hide only. Returns what stays linked, so the message can say so. */
    static function archive(array $p, int $uid): array {
        $id = (int)$p['id']; $linked = self::linked($id);
        Db::q('UPDATE projects SET archived_at = ?, updated_at = ? WHERE id = ? AND archived_at IS NULL', [self::utcNow(), self::utcNow(), $id]);
        Activity::log((int)$p['workspace_id'], $id, $uid, 'project_archived');
        return $linked;
    }
    static function linked(int $id): array {
        return ['tasks' => (int)Db::q('SELECT COUNT(*) FROM tasks WHERE project_id = ?', [$id])->fetchColumn(), 'logs' => (int)Db::q('SELECT COUNT(*) FROM task_time_logs WHERE project_id = ?', [$id])->fetchColumn()];
    }

    /** People on a project, leader first, then the creator, then the other members. @return array<int,string> id => name */
    static function people(int $id): array {
        $out = [];
        foreach (['leader_id', 'created_by'] as $col)
            foreach (Db::q("SELECT u.id, u.name FROM projects p JOIN users u ON u.id = p.$col WHERE p.id = ?", [$id])->fetchAll() as $r) $out[(int)$r['id']] ??= $r['name'];
        foreach (Db::q('SELECT u.id, u.name FROM project_members pm JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY pm.added_at, u.id', [$id])->fetchAll() as $r) $out[(int)$r['id']] ??= $r['name'];
        return $out;
    }

    /** Everything the Project Details page shows, calculated from the database. */
    static function summary(array $p): array {
        $id = (int)$p['id']; $counts = Task::counts($id); $people = self::people($id);
        $lg = Db::q('SELECT COALESCE(SUM(duration_seconds), 0) s, COUNT(*) n FROM task_time_logs WHERE project_id = ?', [$id])->fetch(); $logged = (int)$lg['s'];
        $active = 0;
        if ($people) { $in = implode(',', array_fill(0, count($people), '?')); $active = (int)Db::q("SELECT COUNT(DISTINCT user_id) FROM attendance_sessions WHERE sign_out_at IS NULL AND user_id IN ($in)", array_keys($people))->fetchColumn(); }
        $total = $counts['all']; $done = $counts['completed'];
        $names = fn(?int $uid) => $uid ? ($people[$uid] ?? (Db::q('SELECT name FROM users WHERE id = ?', [$uid])->fetchColumn() ?: null)) : null;
        return ['counts' => $counts, 'total' => $total, 'done' => $done, 'remaining' => $total - $done, 'percent' => $total > 0 ? (int)round(100 * $done / $total) : 0,
                'logged' => $logged, 'logs' => (int)$lg['n'], 'people' => $people, 'active' => $active, 'leader' => $names((int)($p['leader_id'] ?: $p['created_by']))   /* no leader set = the creator leads */, 'creator' => $names((int)$p['created_by'])];
    }
}
