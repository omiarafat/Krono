<?php
/** Custom roles: validation, saving and deleting. Built-in roles are never changed. Every change is written to the audit log. */
final class Roles {
    /** May this person open / change this custom role? (never one that outranks them, never their own) */
    static function editable(array $ws, array $role): bool {
        if ((int)($ws['custom_role_id'] ?? 0) === (int)$role['id']) return false;
        return Perm::SCOPE_RANK[$role['scope']] <= Perm::SCOPE_RANK[Perm::scope($ws)];
    }

    /** Active people per role token ("manager", "c12"): token => [ [id, name, avatar], ... ] (A-Z). */
    static function people(int $wid): array {
        $o = [];
        foreach (Db::q("SELECT u.id, u.name, u.avatar, m.role, m.custom_role_id FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.status = 'active' ORDER BY u.name, u.id", [$wid])->fetchAll() as $r)
            $o[$r['custom_role_id'] ? 'c' . (int)$r['custom_role_id'] : $r['role']][] = ['id' => (int)$r['id'], 'name' => $r['name'], 'avatar' => $r['avatar']];
        return $o;
    }

    /** @return array{0: array<string,string>, 1: array} [errors, clean values]. $current = the role being edited (or null for a new one). */
    static function check(array $ws, array $p, ?array $current): array {
        $err = []; $mine = Perm::set($ws);
        $name = trim(preg_replace('/\s+/', ' ', (string)($p['name'] ?? ''))); $desc = trim((string)($p['description'] ?? '')); $scope = (string)($p['scope'] ?? '');
        $sent = array_values(array_filter((array)($p['perm'] ?? []), 'is_string'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) $err['name'] = 'Enter a role name between 2 and 40 characters.';
        elseif (preg_match('/[<>]/', $name)) $err['name'] = 'Use letters, numbers and common punctuation only.';
        else {
            foreach (Perm::BUILT_IN as $b) if (mb_strtolower($b[0]) === mb_strtolower($name)) $err['name'] = 'That name is used by a built-in role. Choose another.';
            if (!isset($err['name'])) foreach (Perm::customRoles((int)$ws['id']) as $r) if (mb_strtolower($r['name']) === mb_strtolower($name) && (int)$r['id'] !== (int)($current['id'] ?? 0)) $err['name'] = 'A role with this name already exists.';
        }
        if (mb_strlen($desc) > 120) $err['description'] = 'Use up to 120 characters.';
        if (!isset(Perm::SCOPES[$scope])) $err['scope'] = 'Choose a data scope.';
        elseif (Perm::SCOPE_RANK[$scope] > Perm::SCOPE_RANK[Perm::scope($ws)] && $scope !== ($current['scope'] ?? '')) $err['scope'] = 'You cannot give a wider data scope than your own.';
        $known = Perm::keys();
        foreach ($sent as $k) if (!in_array($k, $known, true) || (!in_array($k, $mine, true) && !in_array($k, $current['permissions'] ?? [], true))) { $err['perm'] = 'You can only give permissions that you have yourself.'; break; }
        // keys the person does not have stay exactly as they were on this role; everything else follows the switches
        $keep = array_values(array_diff($current['permissions'] ?? [], $mine));
        $final = Perm::close(array_merge(array_values(array_intersect($sent, $mine)), $keep));
        if (!$final && !isset($err['perm'])) $err['perm'] = 'Turn on at least one permission.';
        return [$err, ['name' => $name, 'description' => $desc, 'scope' => $scope, 'permissions' => $final, 'sent' => $sent]];
    }

    /** @return int the role id */
    static function save(array $ws, int $actor, array $c, ?array $current): int {
        $wid = (int)$ws['id']; $json = json_encode($c['permissions']); $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            if (!$current) {
                Db::q('INSERT INTO workspace_roles (workspace_id, name, description, scope, permissions, created_by) VALUES (?,?,?,?,?,?)', [$wid, $c['name'], $c['description'], $c['scope'], $json, $actor]);
                $id = (int)$pdo->lastInsertId(); Team::audit($wid, $actor, null, 'role_created', $c['name']);
            } else {
                $id = (int)$current['id'];
                Db::q('UPDATE workspace_roles SET name = ?, description = ?, scope = ?, permissions = ? WHERE id = ? AND workspace_id = ?', [$c['name'], $c['description'], $c['scope'], $json, $id, $wid]);
                if ($c['scope'] !== $current['scope']) self::rebase($wid, $id, Perm::BASE[$current['scope']], Perm::BASE[$c['scope']]);
                Team::audit($wid, $actor, null, 'role_updated', $c['name']);
            }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        return $id;
    }

    /** The scope changed: the people (and pending invitations) on this role get the matching base role; reporting lines that no longer fit are cleared. */
    private static function rebase(int $wid, int $roleId, string $old, string $new): void {
        if ($old === 'manager' && $new !== 'manager') Db::q('UPDATE workspace_members SET manager_id = NULL WHERE workspace_id = ? AND manager_id IN (SELECT user_id FROM (SELECT user_id FROM workspace_members WHERE workspace_id = ? AND custom_role_id = ?) x)', [$wid, $wid, $roleId]);
        Db::q('UPDATE workspace_members SET role = ?' . (in_array($new, Team::REPORTING, true) ? '' : ', manager_id = NULL') . ' WHERE workspace_id = ? AND custom_role_id = ?', [$new, $wid, $roleId]);
        Db::q('UPDATE workspace_invitations SET role = ?' . (in_array($new, Team::REPORTING, true) ? '' : ', manager_id = NULL') . " WHERE workspace_id = ? AND custom_role_id = ? AND status = 'pending'", [$new, $wid, $roleId]);
    }

    /** @return string '' on success, else a message */
    static function delete(array $ws, int $actor, array $role): string {
        $wid = (int)$ws['id']; $n = (int)Db::q('SELECT COUNT(*) FROM workspace_members WHERE workspace_id = ? AND custom_role_id = ?', [$wid, $role['id']])->fetchColumn();
        $i = (int)Db::q("SELECT COUNT(*) FROM workspace_invitations WHERE workspace_id = ? AND custom_role_id = ? AND status = 'pending'", [$wid, $role['id']])->fetchColumn();
        if ($n || $i) return 'This role is still used by ' . ($n ? $n . ' member' . ($n === 1 ? '' : 's') : '') . ($n && $i ? ' and ' : '') . ($i ? $i . ' pending invitation' . ($i === 1 ? '' : 's') : '') . '. Move them to another role first.';
        Db::q('DELETE FROM workspace_roles WHERE id = ? AND workspace_id = ?', [$role['id'], $wid]);
        Team::audit($wid, $actor, null, 'role_deleted', $role['name']);
        return '';
    }
}
