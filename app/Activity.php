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
}
