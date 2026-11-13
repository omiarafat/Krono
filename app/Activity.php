<?php
/** Project activity feed. Rows are written when something changes; saved time logs are read live from task_time_logs. */
final class Activity {
    static function log(int $wsId, int $projectId, ?int $userId, string $type, ?string $subject = null): void {
        try { Db::q('INSERT INTO activity_log (workspace_id, project_id, user_id, type, subject, created_at) VALUES (?,?,?,?,?,?)', [$wsId, $projectId, $userId, $type, $subject, gmdate('Y-m-d H:i:s')]); }
        catch (Throwable $e) { error_log((string)$e); }   // the feed must never break saving
    }

    /** What the feed says after the person's name. */
    private static function text(string $type, ?string $subject): string {
        $q = $subject !== null && $subject !== '' ? ' “' . $subject . '”' : '';
        return match ($type) {
            'project_created' => 'created this project',
            'project_updated' => 'updated the project details',
            'status_changed' => 'changed the status to ' . ($subject ?? ''),
            'project_archived' => 'archived this project',
            'task_created' => 'added a task' . $q,
            'task_updated' => 'updated the task' . $q,
            'task_completed' => 'completed the task' . $q,
            'task_deleted' => 'deleted the task' . $q,
            default => 'made a change',
        };
    }

    /** Newest first. @return array{items:array,total:int,pages:int,page:int} each item: name, text, utc */
    static function feed(int $projectId, int $page = 1, int $per = 5): array {
        $items = [];
        foreach (Db::q('SELECT a.type, a.subject, a.created_at, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.project_id = ?', [$projectId])->fetchAll() as $r)
            $items[] = ['name' => $r['name'] ?? 'Someone', 'text' => self::text($r['type'], $r['subject']), 'utc' => $r['created_at']];
        foreach (Db::q('SELECT l.duration_seconds, l.ended_at, u.name, t.title FROM task_time_logs l JOIN users u ON u.id = l.user_id JOIN tasks t ON t.id = l.task_id WHERE l.project_id = ?', [$projectId])->fetchAll() as $r)
            $items[] = ['name' => $r['name'], 'text' => 'logged ' . fmt_hm((int)$r['duration_seconds']) . ' on “' . $r['title'] . '”', 'utc' => $r['ended_at']];
        usort($items, fn($a, $b) => strcmp($b['utc'], $a['utc']));
        $total = count($items); $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, $page), $pages);
        return ['items' => array_slice($items, ($page - 1) * $per, $per), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    // ---------- one task ----------

    /** Writes one line of the task's own history. Never throws. */
    static function logTask(int $taskId, ?int $userId, string $type, ?string $old = null, ?string $new = null): void {
        try { Db::q('INSERT INTO task_activity (task_id, user_id, type, old_value, new_value, created_at) VALUES (?,?,?,?,?,?)', [$taskId, $userId, $type, $old, $new, gmdate('Y-m-d H:i:s')]); }
        catch (Throwable $e) { error_log((string)$e); }
    }

    private static function taskText(string $type, ?string $old, ?string $new): string {
        $old = (string)$old; $new = (string)$new;
        return match ($type) {
            'title' => 'renamed the task from “' . $old . '” to “' . $new . '”',
            'status' => 'changed the status from ' . $old . ' to ' . $new,
            'priority' => 'changed the priority from ' . $old . ' to ' . $new,
            'due' => $new === '' ? 'removed the due date (was ' . $old . ')' : ($old === '' ? 'set the due date to ' . $new : 'changed the due date from ' . $old . ' to ' . $new),
            'assignee' => $new === '' ? 'unassigned this task (was ' . $old . ')' : 'assigned this task to ' . $new,
            'description' => 'updated the description',
            'notes' => 'updated the notes',
            'estimate' => $new === '' ? 'removed the estimate (was ' . $old . ')' : ($old === '' ? 'set the estimate to ' . $new : 'changed the estimate from ' . $old . ' to ' . $new),
            default => 'made a change',
        };
    }

    /** Newest first: edits, saved time logs and the creation of the task. @return array{items:array,total:int,pages:int,page:int} item: name, text, note, utc */
    static function taskFeed(array $task, int $page = 1, int $per = 10): array {
        $id = (int)$task['id']; $items = [];
        foreach (Db::q('SELECT a.type, a.old_value, a.new_value, a.created_at, u.name FROM task_activity a LEFT JOIN users u ON u.id = a.user_id WHERE a.task_id = ?', [$id])->fetchAll() as $r)
            $items[] = ['name' => $r['name'] ?? 'Someone', 'text' => self::taskText($r['type'], $r['old_value'], $r['new_value']), 'note' => '', 'utc' => $r['created_at']];
        foreach (Db::q('SELECT l.duration_seconds, l.work_description, l.ended_at, u.name FROM task_time_logs l JOIN users u ON u.id = l.user_id WHERE l.task_id = ?', [$id])->fetchAll() as $r)
            $items[] = ['name' => $r['name'], 'text' => 'logged ' . fmt_hm((int)$r['duration_seconds']) . ' on this task', 'note' => (string)$r['work_description'], 'utc' => $r['ended_at']];
        $items[] = ['name' => $task['creator'] ?? 'Someone', 'text' => 'created this task', 'note' => '', 'utc' => $task['created_at']];
        usort($items, fn($a, $b) => strcmp($b['utc'], $a['utc']));
        $total = count($items); $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, $page), $pages);
        return ['items' => array_slice($items, ($page - 1) * $per, $per), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }
}
