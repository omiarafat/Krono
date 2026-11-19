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

    /** The user's current workspace (the one they last joined, else their first active membership) as ['id','name','timezone','role'], or null. */
    static function forUser(int $uid): ?array {
        $sel = 'SELECT w.id, w.name, w.timezone, m.role, m.custom_role_id FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id';
        $old = 'SELECT w.id, w.name, w.timezone, m.role FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id';
        try {   // current_workspace_id exists after "php database/migrate.php"; until then fall back to the first membership
            $r = Db::q("$sel JOIN users u ON u.id = m.user_id WHERE m.user_id = ? AND m.status = 'active' ORDER BY (CASE WHEN w.id = u.current_workspace_id THEN 0 ELSE 1 END), m.created_at, w.id LIMIT 1", [$uid])->fetch();
        } catch (Throwable $e) {
            $r = Db::q("$old WHERE m.user_id = ? AND m.status = 'active' ORDER BY m.created_at, w.id LIMIT 1", [$uid])->fetch();
        }
        return $r ?: null;
    }

    /** Makes a workspace the user's current one (only if they are an active member of it). */
    static function setCurrent(int $uid, int $wid): void {
        if (Db::q("SELECT 1 FROM workspace_members WHERE user_id = ? AND workspace_id = ? AND status = 'active'", [$uid, $wid])->fetchColumn()) {
            try { Db::q('UPDATE users SET current_workspace_id = ? WHERE id = ?', [$wid, $uid]); } catch (Throwable $e) { error_log((string)$e); }
        }
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

    /** Display timezone: the person's own (Settings > Profile) when set, else the workspace's. Stored times stay UTC. */
    static function tz(?array $ws): DateTimeZone {
        $own = Account::ownTimezone(); if ($own) return $own;
        try { return new DateTimeZone((string)($ws['timezone'] ?? '') ?: (string)env('APP_TIMEZONE', 'Asia/Dhaka')); } catch (Exception $e) { return app_tz(); }
    }

    /** The signed-in person's role in their current workspace ('' when signed out). Looked up once per request. */
    static function role(): string {
        static $r = null;
        if ($r === null) { $u = Auth::user(); $ws = $u ? self::ensureFor((int)$u['id']) : null; $r = (string)($ws['role'] ?? ''); }
        return $r;
    }

    /** Workspace display defaults (Settings > Workspace > General). Falls back to the old look before migrate.php. */
    static function prefs(?int $wid = null): array {
        static $cache = [];
        if ($wid === null) { $u = Auth::user(); if (!$u) return self::DEFAULTS; $wid = (int)self::ensureFor((int)$u['id'])['id']; }
        if (!isset($cache[$wid])) {
            try { $r = Db::q('SELECT week_start, date_format, time_format, language FROM workspaces WHERE id = ?', [$wid])->fetch(); } catch (Throwable $e) { $r = null; }
            $cache[$wid] = $r ? $r + self::DEFAULTS : self::DEFAULTS;
        }
        return $cache[$wid];
    }
    const DEFAULTS = ['week_start' => 'mon', 'date_format' => 'MMM DD, YYYY', 'time_format' => '12', 'language' => 'en'];
    const WEEK_DAYS = ['mon' => 'Monday', 'sun' => 'Sunday', 'sat' => 'Saturday'];

    /** First day of the week that contains $day, using the workspace's "week starts on". */
    static function weekStart(DateTimeImmutable $day): DateTimeImmutable {
        $start = ['sun' => 0, 'mon' => 1, 'sat' => 6][self::prefs()['week_start']] ?? 1;
        $off = ((int)$day->format('w') - $start + 7) % 7;
        return $off ? $day->modify("-$off days") : $day;
    }
}
