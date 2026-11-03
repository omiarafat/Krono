<?php
/**
 * Workspaces and roles. One workspace = one company/team; every member has ONE role in it.
 * Roles (strongest first): owner > admin > manager > member > viewer.
 * Every account gets a personal workspace (owner) automatically, so every page can rely on having one.
 */
final class Workspace {
    const ROLES = ['viewer' => 1, 'member' => 2, 'manager' => 3, 'admin' => 4, 'owner' => 5];

    /** True when $role is at least as strong as $min (e.g. atLeast('manager', 'member') is true). */
    static function atLeast(?string $role, string $min): bool { return (self::ROLES[$role ?? ''] ?? 0) >= (self::ROLES[$min] ?? 99); }

    /** The user's workspace (first active membership) as ['id','name','timezone','role'], or null. */
    static function forUser(int $uid): ?array {
        $r = Db::q("SELECT w.id, w.name, w.timezone, m.role FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id
                    WHERE m.user_id = ? AND m.status = 'active' ORDER BY m.created_at, w.id LIMIT 1", [$uid])->fetch();
        return $r ?: null;
    }

    /** Makes sure the user has a workspace; creates a personal one (role owner) when missing. Safe to call many times. */
    static function ensureFor(int $uid): array {
        $ws = self::forUser($uid); if ($ws) return $ws;
        $name = (string)Db::q('SELECT name FROM users WHERE id = ?', [$uid])->fetchColumn();
        $first = trim((string)strtok($name, ' ')); $label = ($first !== '' ? $first : 'My') . "'s Workspace";
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            Db::q('INSERT INTO workspaces (name, timezone, created_by) VALUES (?, ?, ?)', [mb_substr($label, 0, 120), (string)env('APP_TIMEZONE', 'Asia/Dhaka'), $uid]);
            $id = (int)$pdo->lastInsertId();
            Db::q("INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')", [$id, $uid]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return self::forUser($uid);
    }

    static function tz(?array $ws): DateTimeZone {
        try { return new DateTimeZone((string)($ws['timezone'] ?? '') ?: (string)env('APP_TIMEZONE', 'Asia/Dhaka')); } catch (Exception $e) { return app_tz(); }
    }
}
