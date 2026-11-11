<?php
/**
 * Tasks of a project (read for Project Details, create from "Add Task"). The Tasks page reuses this class.
 *  - Who may add a task: Owner / Admin / Manager, or the project's leader / creator.
 *  - Assignee must belong to the project (leader, creator or member).
 *  - Status "completed" stamps completed_at. Changing status never touches timers or saved time logs.
 *  - Time on a task = sum of saved time logs of that task (every user).
 */
final class Task {
    const STATUS = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'Review', 'on_hold' => 'On Hold', 'completed' => 'Completed'];
    const LIST_LIMIT = 8;

    static function canAdd(array $ws, array $project, int $uid): bool {
        return Project::canManage($ws) || (int)$project['leader_id'] === $uid || (int)$project['created_by'] === $uid;
    }

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
}
