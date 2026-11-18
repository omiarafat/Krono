<?php
/**
 * What a person may do in their workspace. Two things decide it:
 *  1. DATA SCOPE  - whose data they can see: "own" (only theirs), "team" (theirs + the people who report to them) or "all" (the whole workspace).
 *  2. PERMISSIONS - a list of keys (below). Pages and buttons appear only when the key is there, and the server checks the same key again on every request.
 * The five built-in roles are fixed here. A custom role (table workspace_roles) is just its own scope + its own key list; the person's built-in
 * "role" column then holds the matching BASE role (own = member, team = manager, all = admin) so ranking and reporting lines keep working.
 * To add a permission: add it to CATALOG, to the presets that should have it, and check it with Perm::can().
 */
final class Perm {
    /** group => [key => [label, help]] */
    const CATALOG = [
        'Projects' => [
            'projects.view' => ['See projects', 'Open the Projects page and project details.'],
            'projects.edit' => ['Create and edit projects', 'Add new projects and change their details.'],
            'projects.archive' => ['Archive projects', 'Archive and restore projects.'],
        ],
        'Tasks' => [
            'tasks.view' => ['See tasks', 'Open the Tasks page and task details.'],
            'tasks.edit' => ['Create and edit tasks', 'Add tasks, change details and mark them done.'],
            'tasks.assign' => ['Assign tasks to other people', 'Give a task to someone else.'],
            'tasks.delete' => ['Delete tasks', 'Delete tasks that have no saved time logs.'],
        ],
        'Time & Attendance' => [
            'time.view' => ['See time tracking', 'Open saved time logs for the people in their data scope.'],
            'attendance.view' => ['See attendance', 'Open attendance for the people in their data scope.'],
        ],
        'Reports' => [
            'reports.view' => ['See reports', 'Open reports for the people in their data scope.'],
            'reports.export' => ['Export reports as PDF', 'Download a report as a PDF file.'],
        ],
        'Teams' => [
            'team.view' => ['See the team', 'Open the Teams page.'],
            'team.invite' => ['Invite members', 'Send, resend and cancel invitations.'],
            'team.roles' => ['Change roles and reporting lines', 'Change a member\'s role and who they report to.'],
            'team.deactivate' => ['Deactivate members', 'Turn a member\'s access off or back on.'],
        ],
        'Workspace' => [
            'workspace.settings' => ['Open workspace settings', 'Change the workspace name, timezone and display defaults.'],
            'workspace.audit' => ['See the audit log', 'Review and export the history of changes.'],
            'workspace.roles' => ['Manage roles', 'Create, edit and delete custom roles.'],
        ],
    ];
    /** A permission that needs another one first (turning it on turns the other on; turning the other off turns it off). */
    const NEEDS = ['projects.edit' => 'projects.view', 'projects.archive' => 'projects.edit', 'tasks.edit' => 'tasks.view', 'tasks.assign' => 'tasks.edit', 'tasks.delete' => 'tasks.edit',
        'reports.export' => 'reports.view', 'team.invite' => 'team.view', 'team.roles' => 'team.view', 'team.deactivate' => 'team.view'];

    const SCOPES = ['own' => 'Own data only', 'team' => 'Own data and direct reports', 'all' => 'Whole workspace'];
    const BASE = ['own' => 'member', 'team' => 'manager', 'all' => 'admin'];   // the built-in role a custom role of that scope is stored as
    const SCOPE_OF = ['owner' => 'all', 'admin' => 'all', 'manager' => 'team', 'member' => 'own', 'viewer' => 'own'];
    const SCOPE_RANK = ['own' => 1, 'team' => 2, 'all' => 3];

    /** Built-in roles: key => [name, short description, permission keys]. */
    const BUILT_IN = [
        'owner' => ['Owner', 'Full workspace control', ['*']],
        'admin' => ['Admin', 'Manage workspace members and settings', ['*']],
        'manager' => ['Manager', 'Manage assigned employees', ['projects.view', 'projects.edit', 'projects.archive', 'tasks.view', 'tasks.edit', 'tasks.assign', 'tasks.delete', 'time.view', 'attendance.view', 'reports.view', 'reports.export', 'team.view']],
        'member' => ['Member', 'Standard member access', ['projects.view', 'tasks.view', 'time.view', 'attendance.view', 'reports.view', 'reports.export']],
        'viewer' => ['Viewer', 'Read-only access', ['projects.view', 'tasks.view', 'time.view', 'attendance.view', 'reports.view']],
    ];

    /** Every key, in catalog order. */
    static function keys(): array { $o = []; foreach (self::CATALOG as $g) foreach ($g as $k => $_) $o[] = $k; return $o; }

    /** Adds what each chosen key needs, drops unknown keys. */
    static function close(array $keys): array {
        $all = self::keys(); $set = array_flip(array_intersect($all, array_map('strval', $keys)));
        foreach (array_keys($set) as $k) for ($n = $k; isset(self::NEEDS[$n]); $n = self::NEEDS[$n]) $set[self::NEEDS[$n]] = true;
        return array_values(array_intersect($all, array_keys($set)));
    }

    /** Keys of a built-in role. */
    static function preset(string $role): array { $p = self::BUILT_IN[$role][2] ?? []; return $p === ['*'] ? self::keys() : $p; }

    /** A custom role of the workspace as ['id','name','description','scope','permissions'(list),'base'], or null. */
    static function custom(int $wid, int $id): ?array {
        static $c = []; $ck = "$wid:$id";
        if (!array_key_exists($ck, $c)) {
            try { $r = Db::q('SELECT id, name, description, scope, permissions, created_at, updated_at FROM workspace_roles WHERE workspace_id = ? AND id = ?', [$wid, $id])->fetch(); } catch (Throwable $e) { $r = null; }
            $c[$ck] = $r ? self::shape($r) : null;
        }
        return $c[$ck];
    }
    static function shape(array $r): array {
        $p = json_decode((string)$r['permissions'], true);
        return ['id' => (int)$r['id'], 'name' => $r['name'], 'description' => $r['description'], 'scope' => $r['scope'], 'permissions' => self::close(is_array($p) ? $p : []), 'base' => self::BASE[$r['scope']] ?? 'member', 'created_at' => $r['created_at'] ?? null, 'updated_at' => $r['updated_at'] ?? null];
    }
    /** All custom roles of the workspace with their member counts (people + pending invitations), A-Z. */
    static function customRoles(int $wid): array {
        try {
            $rows = Db::q('SELECT id, name, description, scope, permissions, created_at, updated_at FROM workspace_roles WHERE workspace_id = ? ORDER BY name, id', [$wid])->fetchAll();
            $cnt = []; foreach (Db::q("SELECT custom_role_id id, COUNT(*) n FROM workspace_members WHERE workspace_id = ? AND status = 'active' AND custom_role_id IS NOT NULL GROUP BY custom_role_id", [$wid])->fetchAll() as $x) $cnt[(int)$x['id']] = (int)$x['n'];
            $inv = []; foreach (Db::q("SELECT custom_role_id id, COUNT(*) n FROM workspace_invitations WHERE workspace_id = ? AND status = 'pending' AND custom_role_id IS NOT NULL GROUP BY custom_role_id", [$wid])->fetchAll() as $x) $inv[(int)$x['id']] = (int)$x['n'];
        } catch (Throwable $e) { return []; }
        return array_map(fn($r) => self::shape($r) + ['members' => $cnt[(int)$r['id']] ?? 0, 'invites' => $inv[(int)$r['id']] ?? 0], $rows);
    }

    /** The signed-in membership as given by Workspace::forUser(): ['role', 'custom_role_id', ...] */
    static function set(array $ws): array {
        if (!empty($ws['custom_role_id']) && $ws['role'] !== 'owner') { $c = self::custom((int)$ws['id'], (int)$ws['custom_role_id']); if ($c) return $c['permissions']; }
        return self::preset((string)($ws['role'] ?? ''));
    }
    static function can(array $ws, string $key): bool { return in_array($key, self::set($ws), true); }
    static function scope(array $ws): string {
        if (!empty($ws['custom_role_id']) && $ws['role'] !== 'owner') { $c = self::custom((int)$ws['id'], (int)$ws['custom_role_id']); if ($c) return $c['scope']; }
        return self::SCOPE_OF[$ws['role'] ?? ''] ?? 'own';
    }
    /** Page guard: a person without the key gets the plain "not found" page (the page is hidden, not just disabled). */
    static function gate(array $ws, string $key, array $u, string $nav = '', string $crumb = ''): void {
        if (self::can($ws, $key)) return;
        http_response_code(404); render('errors/404', ['title' => 'Page not found', 'user' => $u, 'nav' => $nav, 'crumb' => $crumb ?: 'Not found'], 'app'); exit;
    }
    /** The signed-in person's own check (menus, settings). False when signed out. */
    static function me(string $key): bool { $u = Auth::user(); return $u && self::can(Workspace::ensureFor((int)$u['id']), $key); }

    /** "Manager" or the custom role's name, for a membership / invitation row that carries role + custom_role_id (+ optional custom_name). */
    static function label(string $role, $customId = null, ?string $customName = null): string {
        return ($customId && $customName) ? $customName : (self::BUILT_IN[$role][0] ?? ucfirst($role));
    }
}
