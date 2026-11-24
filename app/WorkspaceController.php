<?php
/** Switching between the person's workspaces and creating / upgrading a company workspace. */
final class WorkspaceController {
    private static function user(): array { $u = Auth::user(); if (!$u) redirect('/login'); Workspace::ensureFor((int)$u['id']); return $u; }

    static function index(): void {
        $u = self::user(); $cur = Workspace::current();
        render('workspace/index', ['title' => 'Choose a workspace', 'user' => $u, 'nav' => 'workspaces', 'crumb' => 'Choose a workspace', 'list' => Workspace::mine((int)$u['id']), 'currentId' => (int)($cur['id'] ?? 0)], 'app');
    }

    /** Only a workspace the person is an active member of can become current. */
    static function switch(): void {
        $u = self::user(); $id = (int)($_POST['workspace_id'] ?? 0);
        if ($id <= 0 || !Workspace::setCurrent((int)$u['id'], $id)) { flash('error', 'You are not a member of that workspace.'); redirect('/workspaces'); }
        $next = (string)($_POST['next'] ?? '');   // only our own pages
        redirect(preg_match('#^/(dashboard|tasks|time-tracking|projects)$|^/(task|project)\?id=\d+$#', $next) ? $next : '/dashboard');
    }

    private static function eligible(int $uid): array {   // personal workspaces the person owns
        return array_values(array_filter(Workspace::mine($uid), fn($w) => $w['type'] === 'personal' && $w['role'] === 'owner'));
    }
    private static function formView(array $u, array $v, array $errors, array $bad, string $mode): void {
        render('workspace/new', ['title' => 'Create or upgrade workspace', 'user' => $u, 'nav' => 'workspaces', 'crumb' => 'Create or upgrade workspace', 'form' => $v, 'errors' => $errors, 'bad' => $bad, 'mode' => $mode,
            'eligible' => self::eligible((int)$u['id']), 'zones' => Account::timezones()], 'app');
    }
    static function form(): void {
        $u = self::user(); $tz = Account::ownTimezone()?->getName() ?? (string)env('APP_TIMEZONE', 'Asia/Dhaka');
        self::formView($u, ['name' => '', 'company_name' => '', 'industry' => '', 'country' => Workspace::countryOf($tz), 'timezone' => $tz, 'workspace_id' => 0], [], [], 'new');
    }
    static function create(): void {
        $u = self::user(); $uid = (int)$u['id']; $mode = ($_POST['mode'] ?? 'new') === 'upgrade' ? 'upgrade' : 'new';
        [$err, $v] = Workspace::checkCompany($_POST, $mode === 'upgrade'); $v['workspace_id'] = (int)($_POST['workspace_id'] ?? 0);
        if ($mode === 'upgrade') {
            $ok = array_filter(self::eligible($uid), fn($w) => (int)$w['id'] === $v['workspace_id']);
            if (!$ok) $err['workspace_id'] = 'Choose one of your personal workspaces.';
            unset($err['timezone'], $err['country']);   // an upgrade keeps the workspace timezone
        } elseif (count(Workspace::mine($uid)) >= Workspace::MAX_PER_USER) $err['name'] = 'You have reached the limit of ' . Workspace::MAX_PER_USER . ' workspaces.';
        if ($err) { self::formView($u, $v, array_values($err), $err, $mode); return; }
        try {
            if ($mode === 'upgrade') {
                $msg = Workspace::upgrade($uid, $v['workspace_id'], $v);
                if ($msg !== '') { self::formView($u, $v, [$msg], [], $mode); return; }
                Workspace::setCurrent($uid, $v['workspace_id']); flash('ok', 'Your workspace is now a company workspace. Nothing was lost.');
            } else {
                Workspace::create($uid, $v['name'], $v['timezone'], $v['country'], 'company', $v['company_name'], $v['industry']); flash('ok', 'Company workspace created.');
            }
        } catch (Throwable $e) { error_log((string)$e); self::formView($u, $v, ['We could not save the workspace. Please try again.'], [], $mode); return; }
        redirect('/dashboard');
    }
}
