<?php
/** Teams page (members, reporting structure) and the invitation links. */
final class TeamController {
    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return [$u, Workspace::ensureFor((int)$u['id'])]; }

    /** Where a form came from: only our own /teams addresses. */
    private static function back(): string {
        $b = (string)($_POST['back'] ?? ''); parse_str((string)parse_url($b, PHP_URL_QUERY), $q);
        return Team::path(Team::cleanFilters(str_starts_with($b, '/teams') ? $q : []));
    }

    private static function page(array $u, array $ws, array $f, ?array $inviteForm = null): void {
        $uid = (int)$u['id'];
        Perm::gate($ws, 'team.view', $u, 'team', 'Teams');
        $roster = []; $failed = false;
        try { $roster = Team::roster($ws, $uid); } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        $manage = Team::canManage($ws);
        if (!$manage) $f['tab'] = 'members';
        $data = ['title' => 'Teams', 'user' => $u, 'nav' => 'team', 'crumb' => 'Teams', 'ws' => $ws, 'tz' => Workspace::tz($ws), 'f' => $f, 'failed' => $failed, 'manage' => $manage,
            'counts' => Team::counts($roster), 'managers' => Team::managers($roster), 'eligible' => Team::eligible($roster), 'roles' => Team::assignableRoles($ws), 'inviteForm' => $inviteForm, 'back' => Team::path($f)];
        if ($f['tab'] === 'structure') $data += ['tree' => Team::structure($roster, $f['q'])]; else $data += ['list' => Team::page($roster, $f)];
        render('teams/index', $data, 'app');
    }

    static function index(): void { [$u, $ws] = self::who(); self::page($u, $ws, Team::cleanFilters($_GET)); }

    static function invite(): void {
        [$u, $ws] = self::who(); $back = self::back();
        if (!Perm::can($ws, 'team.invite')) { flash('error', 'You do not have permission to do that.', 'Not allowed'); redirect($back); }
        $v = ['email' => mb_strtolower(trim((string)($_POST['email'] ?? ''))), 'role' => (string)($_POST['role'] ?? 'member'), 'manager_id' => (int)($_POST['manager_id'] ?? 0)];
        try { [$errors, $info] = Team::invite($ws, $u, $v['email'], $v['role'], $v['manager_id']); $dbFail = false; }
        catch (Throwable $e) { error_log((string)$e); $errors = []; $info = null; $dbFail = true; }
        if ($errors || !$info) { http_response_code(422); self::page($u, $ws, Team::cleanFilters(['tab' => 'members']), ['values' => $v, 'errors' => $errors, 'failed' => $dbFail]); return; }
        self::showCreated($info);
    }

    /** The link is only known right now (just a hash is stored), so it is kept in the session for the "invitation created" page. */
    private static function showCreated(array $info): void { $_SESSION['invite_created'] = $info + ['at' => time()]; redirect('/teams/invite/created'); }

    /** "Invitation created" page: status, expiry and the link to copy. */
    static function created(): void {
        [$u, $ws] = self::who();
        if (!Perm::can($ws, 'team.invite')) { flash('error', 'You do not have permission to do that.', 'Not allowed'); redirect('/teams'); }
        $c = $_SESSION['invite_created'] ?? null; $card = is_array($c) && time() - (int)$c['at'] < 3600 ? Team::inviteCard((int)$ws['id'], (int)$c['id']) : null;
        if (!$card || !preg_match('/^[0-9a-f]{64}$/', (string)$c['token'])) { flash('error', 'That invitation link is no longer available. Resend the invitation to get a new one.', 'Link not available'); redirect('/teams'); }
        render('teams/invite-created', ['title' => 'Invitation created', 'user' => $u, 'nav' => 'team', 'crumb' => 'Teams', 'crumb2' => 'Invitation created', 'crumburl' => '/teams', 'ws' => $ws, 'inv' => $card, 'link' => url('/invite?token=' . $c['token']), 'mailed' => !empty($c['mailed']), 'tz' => Workspace::tz($ws)], 'app');
    }

    private static function result(string $err, string $ok, string $okTitle, string $errTitle): void {
        if ($err !== '') flash('error', $err, $errTitle); else flash('ok', $ok, $okTitle);
        redirect(self::back());
    }

    private static function nameOf(int $uid): string { return (string)Db::q('SELECT name FROM users WHERE id = ?', [$uid])->fetchColumn(); }

    static function role(): void {
        [$u, $ws] = self::who(); $t = (int)($_POST['user_id'] ?? 0); $role = (string)($_POST['role'] ?? '');
        $err = Team::setRole($ws, (int)$u['id'], $t, $role, (string)($_POST['reports'] ?? ''));
        self::result($err, self::nameOf($t) . ' is now ' . (Team::assignableRoles($ws)[$role] ?? $role) . '.', 'Role updated', 'Could not change the role');
    }

    static function manager(): void {
        [$u, $ws] = self::who(); $t = (int)($_POST['user_id'] ?? 0); $m = (int)($_POST['manager_id'] ?? 0);
        $err = Team::setManager($ws, (int)$u['id'], $t, $m);
        self::result($err, $m ? self::nameOf($t) . ' now reports to ' . self::nameOf($m) . '.' : self::nameOf($t) . ' no longer has a direct manager.', 'Member assigned', 'Could not assign the member');
    }

    static function status(): void {
        [$u, $ws] = self::who(); $t = (int)($_POST['user_id'] ?? 0); $do = ($_POST['do'] ?? '') === 'reactivate' ? 'reactivate' : 'deactivate';
        $err = Team::setStatus($ws, (int)$u['id'], $t, $do, (string)($_POST['reports'] ?? ''));
        $name = self::nameOf($t);
        self::result($err, $do === 'reactivate' ? "$name can use the workspace again." : "$name was deactivated. Saved attendance, time logs and projects are kept.", $do === 'reactivate' ? 'Member reactivated' : 'Member deactivated', 'Could not change the status');
    }

    static function invitation(): void {
        [$u, $ws] = self::who(); $do = ($_POST['do'] ?? '') === 'cancel' ? 'cancel' : 'resend';
        try { [$err, $info] = Team::invitation($ws, $u, (int)($_POST['id'] ?? 0), $do); } catch (Throwable $e) { error_log((string)$e); $err = 'Could not save the change. Try again.'; $info = null; }
        if ($err === '' && $info) self::showCreated($info);
        self::result($err, $do === 'cancel' ? 'The invitation was cancelled.' : 'The invitation was sent again.', $do === 'cancel' ? 'Invitation cancelled' : 'Invitation sent', 'Could not update the invitation');
    }

    // ---------- invitation link ----------

    static function showInvite(): void {
        $token = (string)($_GET['token'] ?? ''); $inv = Team::invitationByToken($token); $u = Auth::user();
        if (!$inv) { http_response_code(404); render('teams/invite', ['title' => 'Invitation', 'state' => 'invalid'], 'auth'); return; }
        if (!$u) { $_SESSION['invite_token'] = $token; render('teams/invite', ['title' => 'Invitation', 'state' => 'signin', 'inv' => $inv, 'exists' => (bool)Db::q('SELECT 1 FROM users WHERE email = ?', [mb_strtolower((string)$inv['email'])])->fetchColumn()], 'auth'); return; }
        $same = mb_strtolower($u['email']) === mb_strtolower($inv['email']);
        render('teams/invite', ['title' => 'Invitation', 'state' => $same ? 'accept' : 'other', 'inv' => $inv, 'user' => $u, 'token' => $token], 'auth');
    }

    static function accept(): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        $token = (string)($_POST['token'] ?? ''); $inv = Team::invitationByToken($token);
        if (!$inv) { http_response_code(404); render('teams/invite', ['title' => 'Invitation', 'state' => 'invalid'], 'auth'); return; }
        $err = Team::accept($inv, $u);
        if ($err !== '') { flash('error', $err); redirect('/invite?token=' . $token); }
        unset($_SESSION['invite_token']); flash('ok', 'You joined ' . $inv['workspace'] . '.', 'Welcome to the workspace'); redirect('/dashboard');
    }

    /** After sign-in: where a person with a waiting invitation link should go (or null). */
    static function pendingInvite(): ?string {
        $t = (string)($_SESSION['invite_token'] ?? ''); return $t !== '' && Team::invitationByToken($t) ? '/invite?token=' . $t : null;
    }
}
