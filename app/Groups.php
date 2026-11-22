<?php
/**
 * Teams (groups) inside one workspace, and the Team Leader.
 *  - A team belongs to ONE workspace; a person can be in several teams. Teams never create a login or a separate workspace.
 *  - Team Leader is a role INSIDE one team (team_members.role = 'leader'), not a workspace role: a leader can add and remove people of that team only.
 *  - Creating, renaming, deleting a team and choosing leaders needs the permission "team.roles" (Owner / Admin). Deleting a team never removes people from the workspace.
 *  - Who sees a team: scope "all" sees every team; everyone else only the teams they are in. Only active workspace members can be in a team.
 *  - Every change is written to the audit log.
 */
final class Groups {
    const NAME_MAX = 80, MAX_PER_WORKSPACE = 100;

    private static function now(): string { return gmdate('Y-m-d H:i:s'); }
    private static function whole(array $ws): bool { return Perm::scope($ws) === 'all'; }
    /** May the person create / rename / delete teams and choose leaders? */
    static function canAdmin(array $ws): bool { return Perm::can($ws, 'team.roles'); }

    /** Teams the person may see, with member count and leaders. */
    static function visible(array $ws, int $uid): array {
        $wid = (int)$ws['id'];
        $rows = Db::q("SELECT t.id, t.name, t.created_at,
            (SELECT COUNT(*) FROM team_members x JOIN workspace_members w ON w.user_id = x.user_id AND w.workspace_id = t.workspace_id AND w.status = 'active' WHERE x.team_id = t.id) AS members
            FROM teams t WHERE t.workspace_id = ? ORDER BY t.name", [$wid])->fetchAll();
        $mine = Db::q('SELECT team_id, role FROM team_members WHERE user_id = ?', [$uid])->fetchAll(PDO::FETCH_KEY_PAIR);
        $lead = []; foreach (Db::q("SELECT x.team_id, u.name FROM team_members x JOIN users u ON u.id = x.user_id JOIN workspace_members w ON w.user_id = x.user_id AND w.workspace_id = ? AND w.status = 'active'
            JOIN teams t ON t.id = x.team_id AND t.workspace_id = ? WHERE x.role = 'leader' ORDER BY u.name", [$wid, $wid])->fetchAll() as $r) $lead[(int)$r['team_id']][] = $r['name'];
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id']; if (!self::whole($ws) && !isset($mine[$id])) continue;
            $out[] = ['id' => $id, 'name' => $r['name'], 'members' => (int)$r['members'], 'leaders' => $lead[$id] ?? [], 'mine' => $mine[$id] ?? null];
        }
        return $out;
    }

    /** One team with its people, only if it belongs to this workspace and the person may see it; else null. */
    static function find(array $ws, int $uid, int $id): ?array {
        $wid = (int)$ws['id'];
        $t = Db::q('SELECT id, name FROM teams WHERE id = ? AND workspace_id = ?', [$id, $wid])->fetch(); if (!$t) return null;
        $role = Db::q('SELECT role FROM team_members WHERE team_id = ? AND user_id = ?', [$id, $uid])->fetchColumn();
        if (!self::whole($ws) && !$role) return null;
        $t['people'] = Db::q("SELECT u.id, u.name, u.email, x.role FROM team_members x JOIN users u ON u.id = x.user_id JOIN workspace_members w ON w.user_id = x.user_id AND w.workspace_id = ? AND w.status = 'active'
            WHERE x.team_id = ? ORDER BY x.role = 'leader' DESC, u.name", [$wid, $id])->fetchAll();
        $t['is_leader'] = $role === 'leader'; $t['can_admin'] = self::canAdmin($ws); $t['can_people'] = $t['can_admin'] || $t['is_leader'];
        $in = array_map(fn($p) => (int)$p['id'], $t['people']);
        $t['candidates'] = array_values(array_filter(Db::q("SELECT u.id, u.name, u.email FROM workspace_members w JOIN users u ON u.id = w.user_id WHERE w.workspace_id = ? AND w.status = 'active' ORDER BY u.name", [$wid])->fetchAll(), fn($c) => !in_array((int)$c['id'], $in, true)));
        return $t;
    }

    /** Team names per user id for the members table (only teams of this workspace). */
    static function namesByUser(int $wid): array {
        try { $rows = Db::q('SELECT x.user_id, t.name FROM team_members x JOIN teams t ON t.id = x.team_id WHERE t.workspace_id = ? ORDER BY t.name', [$wid])->fetchAll(); } catch (Throwable $e) { return []; }
        $o = []; foreach ($rows as $r) $o[(int)$r['user_id']][] = $r['name']; return $o;
    }

    /** Teams the person may put an invited person into: Owner/Admin any team, a Team Leader only their own. id => name */
    static function assignable(array $ws, int $uid): array {
        $wid = (int)$ws['id'];
        if (self::canAdmin($ws)) return Db::q('SELECT id, name FROM teams WHERE workspace_id = ? ORDER BY name', [$wid])->fetchAll(PDO::FETCH_KEY_PAIR);
        return Db::q("SELECT t.id, t.name FROM teams t JOIN team_members x ON x.team_id = t.id AND x.user_id = ? AND x.role = 'leader' WHERE t.workspace_id = ? ORDER BY t.name", [$uid, $wid])->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    static function cleanName(string $n): string { return trim(preg_replace('/\s+/u', ' ', $n)); }
    private static function nameError(int $wid, string $name, int $exceptId = 0): string {
        if ($name === '') return 'Enter a team name.';
        if (mb_strlen($name) > self::NAME_MAX) return 'Keep the team name under ' . self::NAME_MAX . ' characters.';
        if (Db::q('SELECT 1 FROM teams WHERE workspace_id = ? AND LOWER(name) = LOWER(?) AND id <> ?', [$wid, $name, $exceptId])->fetchColumn()) return 'A team with this name already exists.';
        return '';
    }

    /** @return array [error message or '', new team id] */
    static function create(array $ws, array $actor, string $name): array {
        if (!self::canAdmin($ws)) return ['You do not have permission to create teams.', 0];
        $wid = (int)$ws['id']; $name = self::cleanName($name); $err = self::nameError($wid, $name); if ($err !== '') return [$err, 0];
        if ((int)Db::q('SELECT COUNT(*) FROM teams WHERE workspace_id = ?', [$wid])->fetchColumn() >= self::MAX_PER_WORKSPACE) return ['This workspace has reached the limit of ' . self::MAX_PER_WORKSPACE . ' teams.', 0];
        Db::q('INSERT INTO teams (workspace_id, name, created_by, created_at) VALUES (?,?,?,?)', [$wid, $name, (int)$actor['id'], self::now()]);
        $id = (int)Db::pdo()->lastInsertId(); Team::audit($wid, (int)$actor['id'], null, 'team_created', $name);
        return ['', $id];
    }
    static function rename(array $ws, array $actor, int $id, string $name): string {
        if (!self::canAdmin($ws)) return 'You do not have permission to rename teams.';
        $wid = (int)$ws['id']; $old = Db::q('SELECT name FROM teams WHERE id = ? AND workspace_id = ?', [$id, $wid])->fetchColumn(); if ($old === false) return 'That team is not available.';
        $name = self::cleanName($name); $err = self::nameError($wid, $name, $id); if ($err !== '') return $err;
        Db::q('UPDATE teams SET name = ? WHERE id = ? AND workspace_id = ?', [$name, $id, $wid]); Team::audit($wid, (int)$actor['id'], null, 'team_renamed', "$old → $name");
        return '';
    }
    static function delete(array $ws, array $actor, int $id): string {
        if (!self::canAdmin($ws)) return 'You do not have permission to delete teams.';
        $wid = (int)$ws['id']; $name = Db::q('SELECT name FROM teams WHERE id = ? AND workspace_id = ?', [$id, $wid])->fetchColumn(); if ($name === false) return 'That team is not available.';
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try { Db::q('UPDATE workspace_invitations SET team_id = NULL WHERE team_id = ?', [$id]); Db::q('DELETE FROM teams WHERE id = ? AND workspace_id = ?', [$id, $wid]); $pdo->commit(); }
        catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        Team::audit($wid, (int)$actor['id'], null, 'team_deleted', (string)$name);
        return '';
    }

    /** do: add | remove | leader | member. add/remove: Owner/Admin or the team's leader; leader/member (change the leader role): Owner/Admin only. */
    static function person(array $ws, array $actor, int $id, int $target, string $do): string {
        $wid = (int)$ws['id']; $me = (int)$actor['id'];
        $team = self::find($ws, $me, $id); if (!$team) return 'That team is not available.';
        if (!in_array($do, ['add', 'remove', 'leader', 'member'], true)) return 'Choose an action.';
        if (in_array($do, ['leader', 'member'], true) ? !$team['can_admin'] : !$team['can_people']) return 'You do not have permission to change this team.';
        $u = Db::q("SELECT u.name FROM workspace_members w JOIN users u ON u.id = w.user_id WHERE w.workspace_id = ? AND w.user_id = ? AND w.status = 'active'", [$wid, $target])->fetchColumn();
        $in = Db::q('SELECT role FROM team_members WHERE team_id = ? AND user_id = ?', [$id, $target])->fetchColumn();
        if ($do === 'add') {
            if ($u === false) return 'Choose an active member of this workspace.'; if ($in !== false) return 'That person is already in the team.';
            Db::q("INSERT INTO team_members (team_id, user_id, role, created_at) VALUES (?, ?, 'member', ?)", [$id, $target, self::now()]); Team::audit($wid, $me, $target, 'team_member_added', $team['name']); return '';
        }
        if ($in === false) return 'That person is not in the team.';
        if ($do === 'remove') {
            if ($in === 'leader' && !$team['can_admin']) return 'Only an Owner or Admin can remove a Team Leader.';
            Db::q('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$id, $target]); Team::audit($wid, $me, $target, 'team_member_removed', $team['name']); return '';
        }
        if ($u === false) return 'Choose an active member of this workspace.';
        Db::q('UPDATE team_members SET role = ? WHERE team_id = ? AND user_id = ?', [$do === 'leader' ? 'leader' : 'member', $id, $target]);
        Team::audit($wid, $me, $target, $do === 'leader' ? 'team_leader_set' : 'team_leader_removed', $team['name']); return '';
    }
}
