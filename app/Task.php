<?php
/**
 * Tasks: the Tasks page (list, filters, add, edit, quick complete, delete) and the task table of Project Details.
 *  - Who sees a task : everyone who can see its project (archived projects are left out of the list).
 *  - Who may add / edit / delete: Owner / Admin / Manager, or the project's leader / creator. Others are read-only.
 *  - Assignee must belong to the project (leader, creator or member).
 *  - Status "completed" stamps completed_at. Changing status never touches timers or saved time logs.
 *  - Time on a task = sum of saved time logs of that task (every user).
 */
final class Task {
    const STATUS = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'on_hold' => 'On Hold', 'completed' => 'Completed'];
    const LIST_LIMIT = 8;   // rows on Project Details
    const PER_PAGE = 10;    // rows on the Tasks page

    static function canAdd(array $ws, array $project, int $uid): bool {
        return Project::canManage($ws) || (int)$project['leader_id'] === $uid || (int)$project['created_by'] === $uid;
    }

    /** True while the task's timer runs in the desktop app. The server stores no running timer yet (it arrives with the Time Tracking stage), so this is always false for now. */
    static function timerActive(int $taskId): bool { return false; }

    /** Task counts per status for a project, plus 'all'. */
    static function counts(int $projectId): array {
        $c = array_fill_keys(array_merge(['all'], array_keys(self::STATUS)), 0);
        foreach (Db::q('SELECT status, COUNT(*) n FROM tasks WHERE project_id = ? GROUP BY status', [$projectId])->fetchAll() as $r) { $c[$r['status']] = (int)$r['n']; $c['all'] += (int)$r['n']; }
        return $c;
    }

    /** First LIST_LIMIT tasks of the chosen tab, newest change first. */
    static function forProject(int $projectId, string $tab): array {
        $where = 't.project_id = ?'; $args = [$projectId];
        if (isset(self::STATUS[$tab])) { $where .= ' AND t.status = ?'; $args[] = $tab; }
        return Db::q("SELECT t.id, t.title, t.status, t.priority, t.due_date, t.updated_at, u.name assignee,
                (SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l WHERE l.task_id = t.id) logged
              FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id WHERE $where ORDER BY t.updated_at DESC, t.id DESC LIMIT " . self::LIST_LIMIT, $args)->fetchAll();
    }

    /** Checks the Add Task form. $people = user ids that may be assigned. @return array{0:array,1:array} */
    static function validate(array $in, array $people): array {
        $v = ['title' => trim((string)($in['title'] ?? '')), 'assignee_id' => (int)($in['assignee_id'] ?? 0), 'priority' => (string)($in['priority'] ?? 'normal'),
              'due_date' => trim((string)($in['due_date'] ?? '')), 'status' => (string)($in['status'] ?? 'todo')];
        $err = [];
        if ($v['title'] === '') $err['title'] = 'This field is required.'; elseif (mb_strlen($v['title']) > 200) $err['title'] = 'Use 200 characters or fewer.';
        if ($v['assignee_id'] && !in_array($v['assignee_id'], $people, true)) $err['assignee_id'] = 'Choose a member of this project.';
        if (!isset(Project::PRIORITY[$v['priority']])) $err['priority'] = 'Choose a priority.';
        if (!isset(self::STATUS[$v['status']])) $err['status'] = 'Choose a status.';
        if ($v['due_date'] !== '' && !(preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['due_date']) && checkdate((int)substr($v['due_date'], 5, 2), (int)substr($v['due_date'], 8, 2), (int)substr($v['due_date'], 0, 4)))) $err['due_date'] = 'Enter a valid date.';
        return [$v, $err];
    }

    static function create(int $projectId, int $uid, array $v): int {
        $now = gmdate('Y-m-d H:i:s');
        Db::q('INSERT INTO tasks (project_id, title, status, priority, assignee_id, due_date, created_by, created_at, updated_at, completed_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$projectId, $v['title'], $v['status'], $v['priority'], $v['assignee_id'] ?: null, $v['due_date'] ?: null, $uid, $now, $now, $v['status'] === 'completed' ? $now : null]);
        $id = (int)Db::pdo()->lastInsertId();
        Db::q('UPDATE projects SET updated_at = ? WHERE id = ?', [$now, $projectId]);
        return $id;
    }

    // ---------- Tasks page ----------

    static function cleanFilters(array $q): array {
        $status = (string)($q['status'] ?? 'all'); if (!isset(self::STATUS[$status])) $status = 'all';
        $priority = (string)($q['priority'] ?? 'all'); if (!isset(Project::PRIORITY[$priority])) $priority = 'all';
        return ['q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 100), 'project' => max(0, (int)($q['project'] ?? 0)), 'status' => $status, 'priority' => $priority, 'page' => max(1, (int)($q['page'] ?? 1))];
    }

    /** "/tasks?status=review&page=2": only the filters that differ from the defaults. $over replaces some of them. */
    static function path(array $f, array $over = []): string {
        $q = array_filter(array_merge($f, $over), fn($v, $k) => $v !== '' && $v !== 'all' && !($k === 'project' && !$v) && !($k === 'page' && (int)$v <= 1), ARRAY_FILTER_USE_BOTH);
        return '/tasks' . ($q ? '?' . http_build_query($q) : '');
    }

    /** Active projects the person can see: id => name (for the Project filter). */
    static function projectNames(int $uid, array $ws): array {
        [$vis, $v] = Project::visible($uid, $ws); $out = [];
        foreach (Db::q("SELECT p.id, p.name FROM projects p WHERE $vis AND p.archived_at IS NULL ORDER BY p.name, p.id", $v)->fetchAll() as $r) $out[(int)$r['id']] = $r['name'];
        return $out;
    }

    /** Projects the person may add tasks to, with their task count and the people who can be assigned: id => [name, tasks, people[id => name]]. */
    static function addable(int $uid, array $ws): array {
        [$vis, $v] = Project::visible($uid, $ws); $where = "$vis AND p.archived_at IS NULL";
        if (!Project::canManage($ws)) { $where .= ' AND (p.leader_id = ? OR p.created_by = ?)'; array_push($v, $uid, $uid); }
        $out = [];
        foreach (Db::q("SELECT p.id, p.name, (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) tasks FROM projects p WHERE $where ORDER BY p.name, p.id", $v)->fetchAll() as $r)
            $out[(int)$r['id']] = ['name' => $r['name'], 'tasks' => (int)$r['tasks'], 'people' => Project::people((int)$r['id'])];
        return $out;
    }

    /** One page of tasks. @return array{items:array,total:int,projects:int,pages:int,page:int} */
    static function listing(int $uid, array $ws, array $f): array {
        [$vis, $args] = Project::visible($uid, $ws); $where = "$vis AND p.archived_at IS NULL";
        if ($f['project']) { $where .= ' AND t.project_id = ?'; $args[] = $f['project']; }
        if ($f['status'] !== 'all') { $where .= ' AND t.status = ?'; $args[] = $f['status']; }
        if ($f['priority'] !== 'all') { $where .= ' AND t.priority = ?'; $args[] = $f['priority']; }
        if ($f['q'] !== '') { $where .= " AND t.title LIKE ? ESCAPE '!'"; $args[] = '%' . strtr($f['q'], ['!' => '!!', '%' => '!%', '_' => '!_']) . '%'; }
        $from = 'FROM tasks t JOIN projects p ON p.id = t.project_id';
        $c = Db::q("SELECT COUNT(*) n, COUNT(DISTINCT t.project_id) pr $from WHERE $where", $args)->fetch();
        $total = (int)$c['n']; $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        $rows = Db::q("SELECT t.id, t.project_id, t.title, t.status, t.priority, t.assignee_id, t.due_date, p.name project_name, p.leader_id, p.created_by, u.name assignee,
                (SELECT COUNT(*) FROM task_time_logs l WHERE l.task_id = t.id) logs,
                (SELECT COALESCE(SUM(l.duration_seconds), 0) FROM task_time_logs l WHERE l.task_id = t.id) logged
              $from LEFT JOIN users u ON u.id = t.assignee_id WHERE $where
              ORDER BY (t.status = 'completed') ASC, (t.due_date IS NULL) ASC, t.due_date ASC, t.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $args)->fetchAll();
        foreach ($rows as &$r) $r['can_edit'] = self::canAdd($ws, $r, $uid);
        return ['items' => $rows, 'total' => $total, 'projects' => (int)$c['pr'], 'pages' => $pages, 'page' => $page];
    }

    /** One task the person may see, with its project's name / leader / creator (created_by) / archive state (or null). */
    static function find(int $id, int $uid, array $ws): ?array {
        [$vis, $v] = Project::visible($uid, $ws);
        $r = Db::q("SELECT t.id, t.project_id, t.title, t.status, t.priority, t.assignee_id, t.due_date, t.completed_at, p.name project_name, p.leader_id, p.created_by, p.archived_at project_archived FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ? AND $vis", array_merge([$id], $v))->fetch();
        return $r ?: null;
    }

    /** Saved time logs of the task: [count, seconds]. Tasks with logs are never deleted. */
    static function logs(int $taskId): array {
        $r = Db::q('SELECT COUNT(*) n, COALESCE(SUM(duration_seconds), 0) s FROM task_time_logs WHERE task_id = ?', [$taskId])->fetch();
        return [(int)$r['n'], (int)$r['s']];
    }

    /** Saves the edited fields. Status changes stamp / clear completed_at. */
    static function update(array $old, array $v): void {
        $now = gmdate('Y-m-d H:i:s');
        $done = $v['status'] === 'completed' ? ($old['status'] === 'completed' ? $old['completed_at'] : $now) : null;
        Db::q('UPDATE tasks SET title = ?, status = ?, priority = ?, assignee_id = ?, due_date = ?, completed_at = ?, updated_at = ? WHERE id = ?',
            [$v['title'], $v['status'], $v['priority'], $v['assignee_id'] ?: null, $v['due_date'] ?: null, $done, $now, (int)$old['id']]);
        Db::q('UPDATE projects SET updated_at = ? WHERE id = ?', [$now, (int)$old['project_id']]);
    }

    static function delete(array $t): void {
        Db::q('DELETE FROM tasks WHERE id = ?', [(int)$t['id']]);
        Db::q('UPDATE projects SET updated_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int)$t['project_id']]);
    }

    // ---------- Task Details ----------

    /** One task with everything the details page shows, if the person may see its project (or null). */
    static function full(int $id, int $uid, array $ws): ?array {
        [$vis, $v] = Project::visible($uid, $ws);
        $r = Db::q("SELECT t.*, p.name project_name, p.status project_status, p.leader_id, p.created_by project_creator, p.archived_at project_archived, c.name creator, a.name assignee, n.body notes
            FROM tasks t JOIN projects p ON p.id = t.project_id LEFT JOIN users c ON c.id = t.created_by LEFT JOIN users a ON a.id = t.assignee_id LEFT JOIN task_notes n ON n.task_id = t.id
            WHERE t.id = ? AND $vis", array_merge([$id], $v))->fetch();
        return $r ?: null;
    }

    /** The task's project in the shape canAdd() reads. */
    static function projectOf(array $t): array {
        return ['id' => $t['project_id'], 'name' => $t['project_name'], 'leader_id' => $t['leader_id'], 'created_by' => $t['project_creator'] ?? $t['created_by'], 'archived_at' => $t['project_archived']];
    }

    /** What changed between a saved task and the new values: [[type, old, new], ...] for the task history. */
    static function changes(array $old, array $v, array $people): array {
        $name = fn($id) => $id ? ($people[(int)$id] ?? (string)Db::q('SELECT name FROM users WHERE id = ?', [(int)$id])->fetchColumn()) : '';
        $due = fn($d) => $d ? Ui::date($d) : '';
        $out = [];
        if ($old['title'] !== $v['title']) $out[] = ['title', $old['title'], $v['title']];
        if ($old['status'] !== $v['status']) $out[] = ['status', self::STATUS[$old['status']], self::STATUS[$v['status']]];
        if ($old['priority'] !== $v['priority']) $out[] = ['priority', Project::PRIORITY[$old['priority']], Project::PRIORITY[$v['priority']]];
        if ((string)$old['due_date'] !== (string)$v['due_date']) $out[] = ['due', $due($old['due_date']), $due($v['due_date'])];
        if ((int)$old['assignee_id'] !== (int)$v['assignee_id']) $out[] = ['assignee', $name($old['assignee_id']), $name($v['assignee_id'])];
        return $out;
    }

    /** A page of saved time logs of the task (every user's), newest first, with the total seconds. @return array{items:array,total:int,pages:int,page:int,seconds:int} */
    static function logsPage(int $taskId, int $page, int $per): array {
        $c = Db::q('SELECT COUNT(*) n, COALESCE(SUM(duration_seconds), 0) s FROM task_time_logs WHERE task_id = ?', [$taskId])->fetch();
        $total = (int)$c['n']; $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, $page), $pages);
        $items = Db::q('SELECT l.started_at, l.duration_seconds, l.work_description, u.name FROM task_time_logs l JOIN users u ON u.id = l.user_id WHERE l.task_id = ? ORDER BY l.started_at DESC, l.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), [$taskId])->fetchAll();
        return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page, 'seconds' => (int)$c['s']];
    }

    /** Checks the "Edit description" form: description, notes (one per line) and the estimate. @return array{0:array,1:array} */
    static function validateDetails(array $in): array {
        $notes = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)($in['notes'] ?? ''))), fn($l) => $l !== ''));
        $h = trim((string)($in['est_h'] ?? '')); $m = trim((string)($in['est_m'] ?? ''));
        $v = ['description' => trim((string)($in['description'] ?? '')), 'notes' => implode("\n", $notes), 'est_h' => $h, 'est_m' => $m, 'estimate' => null];
        $err = [];
        if (mb_strlen($v['description']) > 2000) $err['description'] = 'Use 2000 characters or fewer.';
        if (count($notes) > 20) $err['notes'] = 'Use 20 notes or fewer.';
        foreach ($notes as $l) if (mb_strlen($l) > 200) { $err['notes'] = 'Keep each note under 200 characters.'; break; }
        if (($h !== '' && !ctype_digit($h)) || ($m !== '' && !ctype_digit($m)) || (int)$h > 999 || (int)$m > 59) $err['estimate'] = 'Enter hours (0–999) and minutes (0–59).';
        elseif ($h !== '' || $m !== '') { $v['estimate'] = (int)$h * 60 + (int)$m; if ($v['estimate'] === 0) $v['estimate'] = null; }
        return [$v, $err];
    }

    /** Saves description, notes and estimate. @return string[] which of 'description', 'notes', 'estimate' changed */
    static function saveDetails(array $old, array $v, int $uid): array {
        $now = gmdate('Y-m-d H:i:s'); $id = (int)$old['id']; $changed = [];
        if ((string)$old['description'] !== $v['description']) $changed[] = 'description';
        if ((string)($old['notes'] ?? '') !== $v['notes']) $changed[] = 'notes';
        if ((int)$old['estimate_minutes'] !== (int)$v['estimate']) $changed[] = 'estimate';
        Db::q('UPDATE tasks SET description = ?, estimate_minutes = ?, updated_at = ? WHERE id = ?', [$v['description'] !== '' ? $v['description'] : null, $v['estimate'], $now, $id]);
        Db::q('DELETE FROM task_notes WHERE task_id = ?', [$id]);
        if ($v['notes'] !== '') Db::q('INSERT INTO task_notes (task_id, body, updated_by, updated_at) VALUES (?,?,?,?)', [$id, $v['notes'], $uid, $now]);
        return $changed;
    }
}
