<?php
/**
 * Teams: members, roles, direct-manager relationships and invitations.
 *  - What a person may do comes from Perm: team.view opens the page, team.invite / team.roles / team.deactivate allow the matching changes.
 *    Nobody changes the Owner or themselves, and nobody changes (or creates) someone of the same or a higher rank.
 *  - Data scope "all" sees the whole roster; "team" / "own" see only themselves + their DIRECT reports (read-only).
 *  - A role is either built-in (owner..viewer) or a custom role (token "c12"). A person's `role` column always holds the BASE role, `custom_role_id` the custom one.
 *  - A person has zero or one direct manager. Only Members and Viewers report to a manager (managers are role "manager").
 *  - Changing a role / manager / status changes ONLY access: projects, attendance and time logs are never touched. Nothing is hard-deleted.
 *  - Every change is written to audit_log (actor, target, action, time). Invitation tokens are stored as SHA-256 hashes only.
 */
final class Team {
    const PER_PAGE = 8;
    const INVITE_DAYS = 7;
    const ROLE_LABEL = ['owner' => 'Owner', 'admin' => 'Admin', 'manager' => 'Manager', 'member' => 'Member', 'viewer' => 'Viewer'];
    const REPORTING = ['member', 'viewer'];   // the roles that can have a direct manager

    static function canView(array $ws): bool { return Perm::can($ws, 'team.view'); }
    /** True when the person may change anything on the page (invite, change roles, deactivate). */
    static function canManage(array $ws): bool { return Perm::can($ws, 'team.invite') || Perm::can($ws, 'team.roles') || Perm::can($ws, 'team.deactivate'); }

    /** Roles the viewer may give, as token => label. Built-in roles ranked below the viewer, then custom roles whose base rank is below the viewer's
     *  and whose permissions the viewer has all of (nobody can hand out more than they hold). Tokens: "admin" ... "viewer", "c12" for a custom role. */
    static function assignableRoles(array $ws): array {
        $out = []; $mine = Perm::set($ws); $rank = Workspace::ROLES[$ws['role']] ?? 0;
        foreach (['admin', 'manager', 'member', 'viewer'] as $r) if (Workspace::ROLES[$r] < $rank) $out[$r] = self::ROLE_LABEL[$r];
        foreach (Perm::customRoles((int)$ws['id']) as $c)
            if (Workspace::ROLES[$c['base']] < $rank && !array_diff($c['permissions'], $mine)) $out['c' . $c['id']] = $c['name'];
        return $out;
    }

    /** The base built-in role behind a token ("c12" -> its scope's base role, "viewer" -> "viewer"). */
    static function baseOf(array $ws, string $token): string {
        if ($token !== '' && $token[0] === 'c' && ctype_digit(substr($token, 1))) return Perm::custom((int)$ws['id'], (int)substr($token, 1))['base'] ?? 'member';
        return $token;
    }

    /** Every role a filter may pick: token => label (built-in, then the workspace's custom roles). */
    static function filterRoles(array $ws): array {
        $o = self::ROLE_LABEL; foreach (Perm::customRoles((int)$ws['id']) as $c) $o['c' . $c['id']] = $c['name'];
        return $o;
    }

    /** A token from assignableRoles() as [base role, custom role id or null, label], or null when the viewer may not give it. */
    static function resolveRole(array $ws, string $token): ?array {
        $ok = self::assignableRoles($ws); if (!isset($ok[$token])) return null;
        if ($token[0] === 'c' && ctype_digit(substr($token, 1))) { $c = Perm::custom((int)$ws['id'], (int)substr($token, 1)); return $c ? [$c['base'], $c['id'], $c['name']] : null; }
        return [$token, null, $ok[$token]];
    }

    /** May the viewer change this person's role / manager / status with permission $perm? (never the Owner, never themselves, only below their own rank) */
    static function canActOn(array $ws, int $actor, string $targetRole, int $targetId, string $perm): bool {
        return Perm::can($ws, $perm) && $targetId !== $actor && $targetRole !== 'owner' && Workspace::ROLES[$targetRole] < Workspace::ROLES[$ws['role']];
    }

    private static function now(): string { return gmdate('Y-m-d H:i:s'); }
    private static function sha(string $t): string { return hash('sha256', $t); }

    // ---------- reading ----------

    /** Every membership row of the workspace (active + deactivated). */
    private static function memberRows(int $wid): array {
        return Db::q('SELECT u.id user_id, u.name, u.email, m.role, m.custom_role_id, cr.name custom_name, m.manager_id, m.status, m.created_at joined, mg.name manager
            FROM workspace_members m JOIN users u ON u.id = m.user_id LEFT JOIN users mg ON mg.id = m.manager_id LEFT JOIN workspace_roles cr ON cr.id = m.custom_role_id AND cr.workspace_id = m.workspace_id WHERE m.workspace_id = ?', [$wid])->fetchAll();
    }

    private static function inviteRows(int $wid): array {
        return Db::q("SELECT i.id, i.email, i.role, i.custom_role_id, cr.name custom_name, i.manager_id, i.created_at, i.expires_at, mg.name manager, tm.name team, ib.name invited_by
            FROM workspace_invitations i LEFT JOIN users mg ON mg.id = i.manager_id LEFT JOIN teams tm ON tm.id = i.team_id AND tm.workspace_id = i.workspace_id LEFT JOIN users ib ON ib.id = i.invited_by LEFT JOIN workspace_roles cr ON cr.id = i.custom_role_id AND cr.workspace_id = i.workspace_id
            WHERE i.workspace_id = ? AND i.status = 'pending' AND i.expires_at > ? ORDER BY i.id DESC", [$wid, self::now()])->fetchAll();
    }

    /** Latest sign of life per person (UTC string): newest attendance sign-in, saved time log or desktop-app request. */
    private static function lastActive(array $ids): array {
        if (!$ids) return [];
        $in = '(' . implode(',', array_fill(0, count($ids), '?')) . ')'; $out = [];
        foreach (['SELECT user_id, MAX(sign_in_at) t FROM attendance_sessions', 'SELECT user_id, MAX(ended_at) t FROM task_time_logs', 'SELECT user_id, MAX(last_used_at) t FROM api_tokens'] as $sql) {
            foreach (Db::q("$sql WHERE user_id IN $in GROUP BY user_id", $ids)->fetchAll() as $r) if ($r['t'] && (!isset($out[$r['user_id']]) || $r['t'] > $out[$r['user_id']])) $out[(int)$r['user_id']] = $r['t'];
        }
        return $out;
    }

    /** Everyone the viewer may see, with display fields and what the viewer may do. Order: Owner, Admin, Manager, Member, Viewer, then A-Z. */
    static function roster(array $ws, int $uid): array {
        $wid = (int)$ws['id']; $rows = self::memberRows($wid); $manage = self::canManage($ws); $whole = Perm::scope($ws) === 'all';
        $reports = []; foreach ($rows as $r) if ($r['status'] === 'active' && $r['manager_id']) $reports[(int)$r['manager_id']] = ($reports[(int)$r['manager_id']] ?? 0) + 1;
        if (!$whole) $rows = array_values(array_filter($rows, fn($r) => (int)$r['user_id'] === $uid || (int)$r['manager_id'] === $uid));
        $last = self::lastActive(array_map(fn($r) => (int)$r['user_id'], $rows)); $out = [];
        $teamNames = Groups::namesByUser($wid); $own = DaysOff::ownMap($wid); $wsOff = DaysOff::workspaceCsv($wid); $req = DaysOff::pendingFor($ws, $uid, Workspace::tz($ws));
        foreach ($rows as $r) {
            $id = (int)$r['user_id']; $cid = (int)($r['custom_role_id'] ?? 0); $act = fn(string $perm) => $r['status'] === 'active' && self::canActOn($ws, $uid, $r['role'], $id, $perm);
            $out[] = ['kind' => 'member', 'id' => $id, 'user_id' => $id, 'name' => $r['name'], 'email' => $r['email'], 'role' => $r['role'], 'custom_role_id' => $cid, 'role_token' => $cid && $r['custom_name'] ? 'c' . $cid : $r['role'], 'role_name' => Perm::label($r['role'], $cid, $r['custom_name']), 'manager_id' => $r['manager_id'] ? (int)$r['manager_id'] : 0, 'manager' => $r['manager'],
                'status' => $r['status'], 'joined' => $r['joined'], 'last' => $last[$id] ?? null, 'reports' => $reports[$id] ?? 0, 'self' => $id === $uid,
                'teams' => implode(', ', $teamNames[$id] ?? []), 'days_off' => DaysOff::label($own[$id] ?? $wsOff), 'days_custom' => ($own[$id] ?? null) !== null, 'requests' => $r['status'] === 'active' ? ($req[$id] ?? []) : [],
                'can' => ['role' => $act('team.roles'), 'manager' => $act('team.roles') && in_array($r['role'], self::REPORTING, true), 'deactivate' => $act('team.deactivate'),
                          'reactivate' => $r['status'] === 'deactivated' && self::canActOn($ws, $uid, $r['role'], $id, 'team.deactivate')]];
        }
        if ($whole || Perm::can($ws, 'team.invite')) foreach (self::inviteRows($wid) as $i) {
            $icid = (int)($i['custom_role_id'] ?? 0);
            $out[] = ['kind' => 'invite', 'id' => (int)$i['id'], 'user_id' => 0, 'name' => ucfirst((string)strtok($i['email'], '@')), 'email' => $i['email'], 'role' => $i['role'], 'custom_role_id' => $icid, 'role_token' => $icid && $i['custom_name'] ? 'c' . $icid : $i['role'], 'role_name' => Perm::label($i['role'], $icid, $i['custom_name']), 'manager_id' => $i['manager_id'] ? (int)$i['manager_id'] : 0, 'manager' => $i['manager'],
                'status' => 'invited', 'teams' => (string)($i['team'] ?? ''), 'joined' => $i['created_at'], 'last' => null, 'reports' => 0, 'self' => false, 'invited_by' => $i['invited_by'], 'expires' => $i['expires_at'],
                'can' => ['role' => false, 'manager' => false, 'deactivate' => false, 'reactivate' => false, 'invite' => Perm::can($ws, 'team.invite') && Workspace::ROLES[$i['role']] < Workspace::ROLES[$ws['role']]]];
        }
        usort($out, fn($a, $b) => [-Workspace::ROLES[$a['role']], $a['custom_role_id'] ? 1 : 0, mb_strtolower($a['name'])] <=> [-Workspace::ROLES[$b['role']], $b['custom_role_id'] ? 1 : 0, mb_strtolower($b['name'])]);
        return $out;
    }

    /** Summary cards (always from the whole roster the viewer may see). */
    static function counts(array $roster): array {
        $c = ['total' => count($roster), 'active' => 0, 'invited' => 0, 'managers' => 0, 'assigned' => 0, 'unassigned' => 0];
        $mgrs = []; foreach ($roster as $r) if ($r['kind'] === 'member' && $r['status'] === 'active' && $r['role'] === 'manager') $mgrs[$r['id']] = true;
        foreach ($roster as $r) {
            if ($r['status'] === 'active') $c['active']++; if ($r['status'] === 'invited') $c['invited']++;
            if ($r['kind'] !== 'member' || $r['status'] !== 'active') continue;
            if ($r['role'] === 'manager') $c['managers']++;
            if (in_array($r['role'], self::REPORTING, true)) { if ($r['manager_id'] && isset($mgrs[$r['manager_id']])) $c['assigned']++; else $c['unassigned']++; }
        }
        return $c;
    }

    /** Filters from the address bar. @return array{q:string,role:string,status:string,manager:string,page:int,tab:string} */
    static function cleanFilters(array $q): array {
        $role = (string)($q['role'] ?? ''); $st = (string)($q['status'] ?? ''); $mg = (string)($q['manager'] ?? '');
        return ['q' => mb_substr(trim((string)($q['q'] ?? '')), 0, 100), 'role' => isset(self::ROLE_LABEL[$role]) || preg_match('/^c\d{1,9}$/', $role) ? $role : '', 'status' => in_array($st, ['active', 'invited', 'deactivated'], true) ? $st : '',
            'manager' => $mg === 'none' || ctype_digit($mg) ? $mg : '', 'page' => max(1, (int)($q['page'] ?? 1)), 'tab' => in_array($q['tab'] ?? '', ['structure', 'groups'], true) ? $q['tab'] : 'members'];
    }

    static function path(array $f, array $over = []): string {
        $f = array_merge($f, $over); $q = [];
        if (in_array($f['tab'], ['structure', 'groups'], true)) $q['tab'] = $f['tab'];
        foreach (['q', 'role', 'status', 'manager'] as $k) if ($f[$k] !== '') $q[$k] = $f[$k];
        if ((int)$f['page'] > 1) $q['page'] = (int)$f['page'];
        return '/teams' . ($q ? '?' . http_build_query($q) : '');
    }

    static function filtered(array $f): bool { return $f['q'] !== '' || $f['role'] !== '' || $f['status'] !== '' || $f['manager'] !== ''; }

    /** One page of the roster after the filters. @return array{items:array,total:int,pages:int,page:int} */
    static function page(array $roster, array $f): array {
        $q = mb_strtolower($f['q']);
        $rows = array_values(array_filter($roster, function ($r) use ($f, $q) {
            if ($q !== '' && !str_contains(mb_strtolower($r['name']), $q) && !str_contains(mb_strtolower($r['email']), $q)) return false;
            if ($f['role'] !== '' && $r['role_token'] !== $f['role']) return false;
            if ($f['status'] !== '' && $r['status'] !== $f['status']) return false;
            if ($f['manager'] === 'none') return !$r['manager_id'];
            if ($f['manager'] !== '' && $r['manager_id'] !== (int)$f['manager']) return false;
            return true;
        }));
        $total = count($rows); $pages = max(1, (int)ceil($total / self::PER_PAGE)); $page = min($f['page'], $pages);
        return ['items' => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** Managers (active, role manager) with their active direct reports, filtered by the search text. */
    static function structure(array $roster, string $search = ''): array {
        $q = mb_strtolower(trim($search)); $out = [];
        foreach ($roster as $m) {
            if ($m['kind'] !== 'member' || $m['status'] !== 'active' || $m['role'] !== 'manager') continue;
            $kids = array_values(array_filter($roster, fn($r) => $r['kind'] === 'member' && $r['status'] === 'active' && $r['manager_id'] === $m['id'] && in_array($r['role'], self::REPORTING, true)));
            if ($q !== '') {
                $hit = str_contains(mb_strtolower($m['name'] . ' ' . $m['email']), $q);
                if (!$hit) { $kids = array_values(array_filter($kids, fn($r) => str_contains(mb_strtolower($r['name'] . ' ' . $r['email']), $q))); if (!$kids) continue; }
                else $kids = array_values($kids);
            }
            $out[] = ['manager' => $m, 'reports' => $kids];
        }
        return $out;
    }

    /** Active managers: id => name (for selects). */
    static function managers(array $roster): array {
        $o = []; foreach ($roster as $r) if ($r['kind'] === 'member' && $r['status'] === 'active' && $r['role'] === 'manager') $o[$r['id']] = $r['name'];
        return $o;
    }

    /** Active Members / Viewers that can get a manager: id => [name, manager name]. */
    static function eligible(array $roster): array {
        $o = []; foreach ($roster as $r) if ($r['kind'] === 'member' && $r['status'] === 'active' && in_array($r['role'], self::REPORTING, true)) $o[$r['id']] = ['name' => $r['name'], 'manager' => $r['manager'] ?: ''];
        return $o;
    }

    /** "Today" / "Yesterday" / "Nov 10, 2026" / "—" for a UTC time, in the workspace timezone. */
    static function lastLabel(?string $utc, DateTimeZone $tz, bool $withTime = false): string {
        if (!$utc) return '—';
        $t = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($tz); $today = new DateTimeImmutable('today', $tz); $d = (int)$today->diff($t->setTime(0, 0))->format('%r%a');
        $time = $withTime ? ' · ' . $t->format(Account::tf()) : '';
        return $d === 0 ? 'Today' . $time : ($d === -1 ? 'Yesterday' . $time : $t->format(Account::df()));
    }

    // ---------- audit ----------

    static function audit(int $wid, int $actor, ?int $target, string $action, string $detail = ''): void {
        try { Db::q('INSERT INTO audit_log (workspace_id, actor_id, target_user_id, action, detail, created_at) VALUES (?,?,?,?,?,?)', [$wid, $actor, $target, $action, mb_substr($detail, 0, 255), self::now()]); }
        catch (Throwable $e) { error_log((string)$e); }
    }

    // ---------- changes (each returns '' on success or a message the person can read) ----------

    private static function member(int $wid, int $uid): ?array {
        return Db::q('SELECT u.id, u.name, u.email, m.role, m.custom_role_id, m.status, m.manager_id FROM workspace_members m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.user_id = ?', [$wid, $uid])->fetch() ?: null;
    }
    private static function directReports(int $wid, int $managerId): int {
        return (int)Db::q("SELECT COUNT(*) FROM workspace_members WHERE workspace_id = ? AND manager_id = ? AND status = 'active'", [$wid, $managerId])->fetchColumn();
    }
    private static function activeManager(int $wid, int $id): bool {
        return (bool)Db::q("SELECT 1 FROM workspace_members WHERE workspace_id = ? AND user_id = ? AND role = 'manager' AND status = 'active'", [$wid, $id])->fetchColumn();
    }

    /** What to do with the direct reports of a manager who loses the manager role or is deactivated. $choice = "unassign" or a manager id. Returns [error, newManagerId|0]. */
    private static function reportsChoice(int $wid, int $leaving, string $choice): array {
        if ($choice === 'unassign') return ['', 0];
        if (ctype_digit($choice) && (int)$choice !== $leaving && self::activeManager($wid, (int)$choice)) return ['', (int)$choice];
        return ['Choose what happens to this manager\'s direct reports first.', 0];
    }

    /** Tries to e-mail the invitation. The invitation exists either way, so a missing or broken mail setup only means the link is shared by hand. @return bool true when the mail was handed over */
    private static function deliver(array $ws, string $inviter, string $email, string $roleLabel, string $token): bool {
        if (!Mailer::available()) return false;
        $link = url('/invite?token=' . $token);
        try {
            Mailer::send($email, 'You are invited to ' . $ws['name'] . ' on Krono', "$inviter invited you to join the workspace \"{$ws['name']}\" on Krono as " . $roleLabel . ".\nAccept the invitation: $link\nThis link works for " . self::INVITE_DAYS . ' days and only for ' . $email . '.');
            return true;
        } catch (Throwable $e) { error_log((string)$e); return false; }
    }

    /** "a***@gmail.com" - shown to people who are not signed in as the invited address. */
    static function maskEmail(string $email): string {
        $at = strrpos($email, '@'); if ($at === false || $at < 1) return '***';
        return mb_substr($email, 0, 1) . str_repeat('*', max(2, min(6, $at - 1))) . substr($email, $at);
    }

    /** One pending invitation of this workspace for the "link created" page: email, role label, manager, expiry. */
    static function inviteCard(int $wid, int $id): ?array {
        $i = Db::q("SELECT i.id, i.email, i.role, i.custom_role_id, i.expires_at, i.created_at, mg.name manager, tm.name team, cr.name custom_name FROM workspace_invitations i LEFT JOIN users mg ON mg.id = i.manager_id LEFT JOIN teams tm ON tm.id = i.team_id AND tm.workspace_id = i.workspace_id
            LEFT JOIN workspace_roles cr ON cr.id = i.custom_role_id AND cr.workspace_id = i.workspace_id WHERE i.id = ? AND i.workspace_id = ? AND i.status = 'pending' AND i.expires_at > ?", [$id, $wid, self::now()])->fetch();
        if (!$i) return null;
        $i['role_label'] = Perm::label($i['role'], $i['custom_role_id'], $i['custom_name']); return $i;
    }

    static function invite(array $ws, array $actor, string $email, string $role, int $managerId, int $teamId = 0): array {
        $errors = []; $email = mb_strtolower(trim($email)); $wid = (int)$ws['id'];
        if (!Perm::can($ws, 'team.invite')) return [['email' => 'You do not have permission to invite members.'], null];
        $pick = self::resolveRole($ws, $role); $base = $pick[0] ?? '';
        if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (!$pick) $errors['role'] = 'Choose a role you are allowed to give.';
        if ($managerId && (!in_array($base, self::REPORTING, true) || !self::activeManager($wid, $managerId))) $errors['manager_id'] = in_array($base, self::REPORTING, true) ? 'Choose an active manager from the list.' : 'Only members and viewers report to a manager.';
        if ($teamId && !isset(Groups::assignable($ws, (int)$actor['id'])[$teamId])) $errors['team_id'] = 'Choose one of the teams you are allowed to add people to.';
        if (!$errors) {
            $u = Db::q('SELECT id FROM users WHERE email = ?', [$email])->fetchColumn();
            $m = $u ? self::member($wid, (int)$u) : null;
            if ($m) $errors['email'] = $m['status'] === 'active' ? 'This person is already a member of the workspace.' : 'This person is deactivated. Reactivate them instead of sending a new invitation.';
            elseif (Db::q("SELECT 1 FROM workspace_invitations WHERE workspace_id = ? AND email = ? AND status = 'pending' AND expires_at > ?", [$wid, $email, self::now()])->fetchColumn()) $errors['email'] = 'An invitation to this address is already pending. Resend it from the members list.';
        }
        if ($errors) return [$errors, null];
        $token = bin2hex(random_bytes(32));
        Db::q('INSERT INTO workspace_invitations (workspace_id, email, role, custom_role_id, manager_id, team_id, token_hash, invited_by, expires_at, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$wid, $email, $base, $pick[1], $managerId ?: null, $teamId ?: null, self::sha($token), (int)$actor['id'], gmdate('Y-m-d H:i:s', time() + self::INVITE_DAYS * 86400), self::now()]);
        $id = (int)Db::pdo()->lastInsertId();
        $mailed = self::deliver($ws, (string)$actor['name'], $email, $pick[2], $token);
        self::audit($wid, (int)$actor['id'], null, 'invite_sent', "$email as {$pick[2]}" . ($mailed ? '' : ' (link shared by hand)'));
        return [[], ['id' => $id, 'token' => $token, 'mailed' => $mailed]];
    }

    /** "resend" | "cancel" a pending invitation. Resend makes a fresh link. @return array [error message or '', info for the link page or null] */
    static function invitation(array $ws, array $actor, int $id, string $do): array {
        if (!Perm::can($ws, 'team.invite')) return ['You do not have permission to do that.', null];
        $wid = (int)$ws['id']; $i = Db::q("SELECT i.*, cr.name custom_name FROM workspace_invitations i LEFT JOIN workspace_roles cr ON cr.id = i.custom_role_id AND cr.workspace_id = i.workspace_id WHERE i.id = ? AND i.workspace_id = ? AND i.status = 'pending'", [$id, $wid])->fetch();
        if (!$i || Workspace::ROLES[$i['role']] >= Workspace::ROLES[$ws['role']]) return ['That invitation is not available.', null];
        if ($do === 'cancel') { Db::q("UPDATE workspace_invitations SET status = 'cancelled' WHERE id = ?", [$id]); self::audit($wid, (int)$actor['id'], null, 'invite_cancelled', $i['email']); return ['', null]; }
        $token = bin2hex(random_bytes(32));
        Db::q('UPDATE workspace_invitations SET token_hash = ?, expires_at = ? WHERE id = ?', [self::sha($token), gmdate('Y-m-d H:i:s', time() + self::INVITE_DAYS * 86400), $id]);
        $mailed = self::deliver($ws, (string)$actor['name'], $i['email'], Perm::label($i['role'], $i['custom_role_id'], $i['custom_name']), $token);
        self::audit($wid, (int)$actor['id'], null, 'invite_resent', $i['email']);
        return ['', ['id' => $id, 'token' => $token, 'mailed' => $mailed]];
    }

    static function setRole(array $ws, int $actor, int $target, string $role, string $choice): string {
        $wid = (int)$ws['id']; $m = self::member($wid, $target);
        if (!$m || $m['status'] !== 'active' || !self::canActOn($ws, $actor, $m['role'], $target, 'team.roles')) return 'That member is not available.';
        $pick = self::resolveRole($ws, $role); if (!$pick) return 'Choose a role you are allowed to give.';
        [$role, $customId, $label] = $pick; $oldId = (int)($m['custom_role_id'] ?? 0);
        if ($role === $m['role'] && (int)$customId === $oldId) return 'That is already their role.';
        $oldLabel = Perm::label($m['role'], $oldId, $oldId ? (Perm::custom($wid, $oldId)['name'] ?? null) : null);
        $move = 0;
        if ($m['role'] === 'manager' && $role !== 'manager' && self::directReports($wid, $target)) { [$e, $move] = self::reportsChoice($wid, $target, $choice); if ($e) return $e; }
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            if ($m['role'] === 'manager' && $role !== 'manager') Db::q("UPDATE workspace_members SET manager_id = ? WHERE workspace_id = ? AND manager_id = ?", [$move ?: null, $wid, $target]);
            Db::q('UPDATE workspace_members SET role = ?, custom_role_id = ?' . (in_array($role, self::REPORTING, true) ? '' : ', manager_id = NULL') . ' WHERE workspace_id = ? AND user_id = ?', [$role, $customId, $wid, $target]);
            self::audit($wid, $actor, $target, 'role_changed', $oldLabel . ' -> ' . $label); $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log((string)$e); return 'Could not save the change. Try again.'; }
        return '';
    }

    /** $manager = 0 removes the direct manager. */
    static function setManager(array $ws, int $actor, int $target, int $manager): string {
        $wid = (int)$ws['id']; $m = self::member($wid, $target);
        if (!$m || $m['status'] !== 'active' || !in_array($m['role'], self::REPORTING, true) || !self::canActOn($ws, $actor, $m['role'], $target, 'team.roles')) return 'Only active members and viewers can be assigned to a manager.';
        if ($manager && ($manager === $target || !self::activeManager($wid, $manager))) return 'Choose an active manager from the list.';
        if ((int)$m['manager_id'] === $manager) return 'They already report to that manager.';
        try { Db::q('UPDATE workspace_members SET manager_id = ? WHERE workspace_id = ? AND user_id = ?', [$manager ?: null, $wid, $target]); } catch (Throwable $e) { error_log((string)$e); return 'Could not save the change. Try again.'; }
        self::audit($wid, $actor, $target, 'manager_changed', ($m['manager_id'] ?: 'none') . ' -> ' . ($manager ?: 'none'));
        return '';
    }

    /** "deactivate" | "reactivate". A manager with direct reports needs $choice (see reportsChoice). */
    static function setStatus(array $ws, int $actor, int $target, string $do, string $choice): string {
        $wid = (int)$ws['id']; $m = self::member($wid, $target);
        if (!$m || !self::canActOn($ws, $actor, $m['role'], $target, 'team.deactivate')) return 'That member is not available.';
        if ($do === 'reactivate') {
            if ($m['status'] !== 'deactivated') return 'That member is already active.';
            Db::q("UPDATE workspace_members SET status = 'active' WHERE workspace_id = ? AND user_id = ?", [$wid, $target]); self::audit($wid, $actor, $target, 'reactivated'); return '';
        }
        if ($m['status'] !== 'active') return 'That member is already deactivated.';
        $move = 0; if ($m['role'] === 'manager' && self::directReports($wid, $target)) { [$e, $move] = self::reportsChoice($wid, $target, $choice); if ($e) return $e; }
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            Db::q('UPDATE workspace_members SET manager_id = ? WHERE workspace_id = ? AND manager_id = ?', [$move ?: null, $wid, $target]);
            Db::q("UPDATE workspace_members SET status = 'deactivated', manager_id = NULL WHERE workspace_id = ? AND user_id = ?", [$wid, $target]);
            self::audit($wid, $actor, $target, 'deactivated'); $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log((string)$e); return 'Could not save the change. Try again.'; }
        return '';
    }

    // ---------- accepting an invitation ----------

    /** The pending invitation for a token (with the workspace name), or null when unknown, used, cancelled or expired. */
    static function invitationByToken(string $token): ?array {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) return null;
        return Db::q("SELECT i.*, w.name workspace, ib.name inviter FROM workspace_invitations i JOIN workspaces w ON w.id = i.workspace_id LEFT JOIN users ib ON ib.id = i.invited_by
            WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > ?", [self::sha($token), self::now()])->fetch() ?: null;
    }

    /** Joins the signed-in person (whose verified e-mail must match) to the workspace. Returns '' or a message. */
    static function accept(array $inv, array $user): string {
        if (mb_strtolower((string)$user['email']) !== mb_strtolower((string)$inv['email'])) return 'This invitation was sent to ' . $inv['email'] . '. Sign in with that account to accept it.';
        $wid = (int)$inv['workspace_id']; $uid = (int)$user['id']; $existing = self::member($wid, $uid);
        if ($existing && $existing['status'] === 'deactivated') return 'Your access to this workspace was deactivated. Ask an admin to reactivate you.';
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            if (!$existing) Db::q("INSERT INTO workspace_members (workspace_id, user_id, role, custom_role_id, manager_id, status, created_at) VALUES (?,?,?,?,?, 'active', ?)", [$wid, $uid, $inv['role'], $inv['custom_role_id'] ?? null, $inv['manager_id'] ?: null, self::now()]);
            if (!empty($inv['team_id']) && Db::q('SELECT 1 FROM teams WHERE id = ? AND workspace_id = ?', [(int)$inv['team_id'], $wid])->fetchColumn())
                Db::q("INSERT INTO team_members (team_id, user_id, role, created_at) SELECT ?, ?, 'member', ? WHERE NOT EXISTS (SELECT 1 FROM team_members WHERE team_id = ? AND user_id = ?)", [(int)$inv['team_id'], $uid, self::now(), (int)$inv['team_id'], $uid]);
            Db::q("UPDATE workspace_invitations SET status = 'accepted', accepted_at = ? WHERE id = ?", [self::now(), (int)$inv['id']]);
            self::audit($wid, $uid, $uid, 'invite_accepted', Perm::label($inv['role'], $inv['custom_role_id'] ?? null, !empty($inv['custom_role_id']) ? (Perm::custom($wid, (int)$inv['custom_role_id'])['name'] ?? null) : null)); $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log((string)$e); return 'Could not join the workspace. Try again.'; }
        Workspace::setCurrent($uid, $wid);
        return '';
    }
}
