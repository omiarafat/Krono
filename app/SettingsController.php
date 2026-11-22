<?php
/** Settings: Account (profile, password, sessions), Preferences (appearance, notifications) and Workspace Settings (each tab needs its own permission). */
final class SettingsController {
    /** group => [tab key => [menu label, icon, permission needed ("" = everyone), page address, page title, page sub-title]]. The left menu and every page check this one list. */
    const GROUPS = [
        'Account' => [
            'profile' => ['Profile information', 'user', '', '/settings/profile', 'Profile settings', 'Manage your account details and preferences'],
            'security' => ['Password', 'lock', '', '/settings/security', 'Password', 'Keep your account safe with a strong password'],
            'sessions' => ['Sessions', 'monitor', '', '/settings/sessions', 'Sessions', 'See where you are signed in and sign out of other devices'],
        ],
        'Preferences' => [
            'appearance' => ['Appearance & notifications', 'sliders', '', '/settings/appearance', 'Appearance & notifications', 'Choose how Krono looks and which emails you receive'],
        ],
        'Workspace Settings' => [
            'general' => ['General', 'building', 'workspace.settings', '/settings/workspace/general', 'Workspace Settings', 'Workspace identity and default preferences'],
            'roles' => ['Roles & Permissions', 'shield', 'workspace.roles', '/settings/workspace/roles', 'Workspace Settings', 'Built-in and custom roles, and what each one can see and do'],
            'billing' => ['Plans & Billing', 'file', 'workspace.settings', '/settings/workspace/billing', 'Plans & billing', 'Manage the plan for this workspace. Payments are not enabled yet'],
            'audit' => ['Audit Log', 'history', 'workspace.audit', '/settings/workspace/audit', 'Workspace Settings', 'Review important workspace changes'],
        ],
    ];

    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return $u; }
    private static function go(string $tab): never { foreach (self::GROUPS as $g) if (isset($g[$tab])) redirect($g[$tab][3]); redirect('/settings'); }

    private static function allowed(array $entry): bool { return $entry[2] === '' || Perm::me($entry[2]); }

    /** The menu entry for $key when the signed-in person may use it, else null. */
    private static function find(string $key): ?array {
        foreach (self::GROUPS as $g) if (isset($g[$key])) return self::allowed($g[$key]) ? $g[$key] : null;
        return null;
    }
    /** Groups with only the entries this person may use; a group with nothing left is dropped (so "Workspace Settings" disappears for people without any of its permissions). */
    static function menu(): array {
        $out = [];
        foreach (self::GROUPS as $name => $items) { $ok = array_filter($items, fn($i) => self::allowed($i)); if ($ok) $out[$name] = $ok; }
        return $out;
    }
    /** Opens a tab for someone allowed to see it. Anyone else gets the plain "page not found" so the page's existence is not shown. */
    private static function open(string $key): array {
        $u = self::who(); $e = self::find($key);
        if (!$e) { http_response_code(404); render('settings/not-found', ['title' => 'Page not found', 'user' => $u, 'nav' => 'settings', 'crumb' => 'Settings'], 'app'); exit; }
        return [$u, $e];
    }

    private static function page(string $key, array $u, array $extra = [], int $status = 200, ?string $view = null): void {
        $e = self::find($key); $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid);
        $d = ['title' => 'Settings', 'user' => $u, 'nav' => 'settings', 'crumb' => 'Settings', 'crumburl' => '/settings', 'crumb2' => $e[0], 'tab' => $key, 'entry' => $e, 'menu' => self::menu(), 'ws' => $ws, 'wid' => (int)$ws['id'], 'tz' => Workspace::tz($ws)];
        if ($key === 'profile') { $d['dayOff'] = $ws['role'] !== 'owner' ? ['days' => DaysOff::effective((int)$ws['id'], $uid), 'custom' => (DaysOff::ownMap((int)$ws['id'])[$uid] ?? null) !== null, 'default' => DaysOff::workspaceCsv((int)$ws['id']), 'pending' => DaysOff::pendingDays((int)$ws['id'], $uid)] : null; $d['profile'] = Account::profile($uid); $d['pending'] = Account::pendingEmail($uid); $d['zones'] = Account::timezones(); $d['formats'] = array_keys(Account::DATE_FORMATS); }
        if ($key === 'sessions') $d['sessions'] = Account::sessions($uid);
        if ($key === 'appearance') $d['prefs'] = Account::prefs($uid);
        if ($key === 'general') { $d['g'] = WsSettings::general((int)$ws['id']); $d['zones'] = Account::timezones(); }
        if ($key === 'billing') $d['plan'] = WsSettings::plan((int)$ws['id']);
        if ($key === 'roles' && !$view) { $d['counts'] = WsSettings::roleCounts((int)$ws['id']); $d['custom'] = Perm::customRoles((int)$ws['id']); $d['people'] = Roles::people((int)$ws['id']); }
        if ($status !== 200) http_response_code($status);
        render('settings/' . (str_starts_with($e[3], '/settings/workspace') ? 'workspace/' : '') . ($view ?? $key), $extra + $d, 'app');
    }

    static function root(): void { self::who(); redirect('/settings/profile'); }
    static function profile(): void { self::page('profile', self::who()); }
    static function security(): void { self::page('security', self::who()); }
    static function sessions(): void { self::page('sessions', self::who()); }
    static function appearance(): void { self::page('appearance', self::who()); }

    static function saveProfile(): void {
        $u = self::who(); $uid = (int)$u['id'];
        [$err, $clean] = Account::checkProfile($_POST);
        $email = Auth::normEmail((string)($_POST['email'] ?? '')); $changing = $email !== '' && $email !== Auth::normEmail((string)$u['email']);
        $form = $clean + ['email' => $email === '' ? $u['email'] : $email];
        $pw = (string)($_POST['current_password'] ?? '');
        if (!$err && $changing) { try { $err += Account::startEmailChange($u, $email, $pw); } catch (Throwable $e) { error_log((string)$e); $err['_'] = 'Could not save changes.'; } }
        elseif (!$err && $email === '') $err['email'] = 'Enter a valid email address.';
        if ($err) { self::page('profile', $u, ['errors' => $err, 'form' => $form, 'askPassword' => $changing], 422); return; }
        try { Account::saveProfile($uid, $clean); }
        catch (Throwable $e) { error_log((string)$e); self::page('profile', $u, ['errors' => ['_' => 'Your changes were not saved. Check your connection and try again. Your input has been kept.'], 'form' => $form, 'askPassword' => $changing], 500); return; }
        if ($changing) flash('ok', 'We sent a code to ' . $email . '. Enter it below to finish changing your email.', 'Check your new email');
        else flash('ok', 'Your profile was saved.', 'Profile updated');
        self::go('profile');
    }

    static function verifyEmail(): void {
        $u = self::who(); $err = Account::confirmEmail((int)$u['id'], (string)($_POST['code'] ?? ''));
        if ($err !== '') { self::page('profile', $u, ['errors' => ['code' => $err]], 422); return; }
        flash('ok', 'Your email address is now changed. Use it the next time you sign in.', 'Email changed'); self::go('profile');
    }
    static function resendEmail(): void {
        $u = self::who(); $err = Account::resendEmailCode((int)$u['id']);
        flash($err === '' ? 'ok' : 'error', $err === '' ? 'We sent a new code.' : $err, $err === '' ? 'Code sent' : 'Could not send the code'); self::go('profile');
    }
    static function cancelEmail(): void { $u = self::who(); Account::cancelEmail((int)$u['id']); flash('ok', 'The email change was cancelled. Your email stays the same.', 'Change cancelled'); self::go('profile'); }

    static function password(): void {
        $u = self::who();
        $err = Account::changePassword((int)$u['id'], (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
        if ($err) { self::page('security', $u, ['errors' => $err], 422); return; }
        flash('ok', 'Password updated. Other sessions were signed out.', 'Password updated'); self::go('security');
    }

    static function revoke(): void {
        $u = self::who(); $kind = ($_POST['kind'] ?? '') === 'app' ? 'app' : 'web';
        $ok = Account::revoke((int)$u['id'], $kind, (int)($_POST['id'] ?? 0));
        $ok ? flash('ok', 'That device was signed out.', 'Signed out') : flash('error', 'That session is no longer active.', 'Could not sign out');
        self::go('sessions');
    }
    static function revokeOthers(): void {
        $u = self::who(); $n = Account::signOutOthers((int)$u['id']);
        flash('ok', $n ? 'All other sessions were signed out.' : 'There were no other sessions.', $n ? 'Signed out' : 'Nothing to sign out'); self::go('sessions');
    }

    // ---------- profile photo ----------

    static function uploadPhoto(): void {
        $u = self::who(); $err = Avatar::save((int)$u['id'], $_FILES['photo'] ?? []);
        $err === '' ? flash('ok', 'Your photo was updated.', 'Photo updated') : flash('error', $err, 'Photo not changed'); self::go('profile');
    }
    static function removePhoto(): void { $u = self::who(); Avatar::remove((int)$u['id']); flash('ok', 'Your photo was removed. Your initial is shown again.', 'Photo removed'); self::go('profile'); }

    // ---------- appearance and notifications ----------

    static function saveAppearance(): void {
        $u = self::who(); $err = Account::savePrefs((int)$u['id'], $_POST);
        if ($err !== '') { self::page('appearance', $u, ['error' => $err, 'prefs' => ['theme' => (string)($_POST['theme'] ?? 'system'), 'email' => !empty($_POST['email']), 'weekly' => !empty($_POST['weekly']), 'tips' => !empty($_POST['tips'])]], 422); return; }
        flash('ok', 'Your appearance and notification choices were saved.', 'Preferences saved'); self::go('appearance');
    }

    // ---------- Workspace Settings (Owner / Admin) ----------

    static function general(): void { [$u] = self::open('general'); self::page('general', $u); }
    static function billing(): void { [$u] = self::open('billing'); self::page('billing', $u); }
    static function roles(): void { [$u] = self::open('roles'); self::page('roles', $u); }

    // ---------- custom roles ----------

    private static function roleNotFound(array $u): never {
        http_response_code(404); render('settings/not-found', ['title' => 'Page not found', 'user' => $u, 'nav' => 'settings', 'crumb' => 'Settings'], 'app'); exit;
    }

    /** What the role form shows: ['mode' => new|edit|view, 'role' => [id, name, description, scope, permissions, builtin]]. Built-in roles are view-only. */
    private static function roleSource(array $u, array $ws): array {
        $blank = ['id' => 0, 'name' => '', 'description' => '', 'scope' => 'own', 'permissions' => [], 'builtin' => ''];
        $key = (string)($_GET['role'] ?? ''); $copy = (string)($_GET['copy'] ?? '');
        $built = function (string $k): ?array { $b = Perm::BUILT_IN[$k] ?? null; return $b ? ['id' => 0, 'name' => $b[0], 'description' => $b[1], 'scope' => Perm::SCOPE_OF[$k], 'permissions' => Perm::preset($k), 'builtin' => $k] : null; };
        $custom = fn(string $id): ?array => ctype_digit($id) ? Perm::custom((int)$ws['id'], (int)$id) : null;
        if ($key !== '') {
            if ($b = $built($key)) return ['mode' => 'view', 'role' => $b];
            if ($c = $custom($key)) return ['mode' => Roles::editable($ws, $c) ? 'edit' : 'view', 'role' => $c + ['builtin' => '']];
            self::roleNotFound($u);
        }
        if ($copy !== '') {
            $src = $built($copy) ?? ($custom($copy) ? $custom($copy) + ['builtin' => ''] : null); if (!$src) self::roleNotFound($u);
            $name = mb_substr('Copy of ' . $src['name'], 0, 40);
            return ['mode' => 'new', 'role' => ['id' => 0, 'name' => $name, 'builtin' => ''] + $src];
        }
        return ['mode' => 'new', 'role' => $blank];
    }

    static function roleForm(): void {
        [$u] = self::open('roles'); $ws = Workspace::ensureFor((int)$u['id']); $src = self::roleSource($u, $ws);
        self::roleView($u, $ws, $src['mode'], $src['role']);
    }

    private static function roleView(array $u, array $ws, string $mode, array $role, array $errors = [], int $status = 200): void {
        $title = $mode === 'new' ? 'Add a role' : ($mode === 'edit' ? 'Edit role' : $role['name']);
        $sub = $mode === 'view' ? ($role['builtin'] !== '' ? 'A built-in role. It can be viewed and copied, but not changed.' : 'You can see this role but not change it.') : 'Choose a data scope and what people with this role can do.';
        $entry = self::find('roles'); $entry[4] = $title; $entry[5] = $sub;
        $e = $entry; self::page('roles', $u, ['entry' => $e, 'mode' => $mode, 'role' => $role, 'errors' => $errors, 'mine' => Perm::set($ws), 'myScope' => Perm::scope($ws)], $status, 'role-form');
    }

    static function saveRole(): void {
        [$u] = self::open('roles'); $ws = Workspace::ensureFor((int)$u['id']); $id = (int)($_POST['id'] ?? 0); $current = null;
        if ($id) { $current = Perm::custom((int)$ws['id'], $id); if (!$current) { flash('error', 'That role is not available.', 'Could not save the role'); redirect('/settings/workspace/roles'); } }
        if ($current && !Roles::editable($ws, $current)) { flash('error', 'You cannot change a role that gives more access than you have.', 'Permission denied'); redirect('/settings/workspace/roles'); }
        [$err, $c] = Roles::check($ws, $_POST, $current);
        $shown = ['id' => $id, 'name' => $c['name'], 'description' => $c['description'], 'scope' => $c['scope'], 'permissions' => $c['sent'], 'builtin' => ''];
        if ($err) { self::roleView($u, $ws, $id ? 'edit' : 'new', $shown, $err, 422); return; }
        try { Roles::save($ws, (int)$u['id'], $c, $current); }
        catch (Throwable $e) { error_log((string)$e); self::roleView($u, $ws, $id ? 'edit' : 'new', $shown, ['_' => 'Your changes were not saved. Check your connection and try again. Your input has been kept.'], 500); return; }
        flash('ok', $current ? '“' . $c['name'] . '” was updated.' : '“' . $c['name'] . '” was created. You can now give it to people in Teams.', $current ? 'Role updated' : 'Role created');
        redirect('/settings/workspace/roles');
    }

    static function deleteRole(): void {
        [$u] = self::open('roles'); $ws = Workspace::ensureFor((int)$u['id']); $role = Perm::custom((int)$ws['id'], (int)($_POST['id'] ?? 0));
        if (!$role || !Roles::editable($ws, $role)) { flash('error', 'That role is not available.', 'Could not delete the role'); redirect('/settings/workspace/roles'); }
        try { $err = Roles::delete($ws, (int)$u['id'], $role); } catch (Throwable $e) { error_log((string)$e); $err = 'Could not delete the role. Try again.'; }
        if ($err !== '') flash('error', $err, 'Role kept'); else flash('ok', '“' . $role['name'] . '” was deleted.', 'Role deleted');
        redirect('/settings/workspace/roles');
    }

    static function saveGeneral(): void {
        [$u] = self::open('general'); $wid = (int)Workspace::ensureFor((int)$u['id'])['id'];
        [$err, $clean] = WsSettings::checkGeneral($_POST, Perm::me('workspace.daysoff'));
        if ($err) { self::page('general', $u, ['errors' => $err, 'form' => $clean], 422); return; }
        try { WsSettings::saveGeneral($wid, (int)$u['id'], $clean); }
        catch (Throwable $e) { error_log((string)$e); self::page('general', $u, ['errors' => ['_' => 'Your changes were not saved. Check your connection and try again. Your input has been kept.'], 'form' => $clean], 500); return; }
        flash('ok', 'Workspace defaults were saved. Saved records and timestamps are not changed.', 'Workspace updated'); self::go('general');
    }

    static function audit(): void {
        [$u] = self::open('audit'); $ws = Workspace::ensureFor((int)$u['id']); $wid = (int)$ws['id']; $f = WsSettings::auditFilters($_GET);
        try { $list = WsSettings::auditList($wid, $f); $actors = WsSettings::auditActors($wid); $failed = false; }
        catch (Throwable $e) { error_log((string)$e); $list = ['items' => [], 'total' => 0, 'pages' => 1, 'page' => 1]; $actors = []; $failed = true; }
        self::page('audit', $u, ['f' => $f, 'list' => $list, 'actors' => $actors, 'failed' => $failed]);
    }
    static function auditCsv(): void {
        [$u] = self::open('audit'); $ws = Workspace::ensureFor((int)$u['id']);
        WsSettings::csv((int)$ws['id'], WsSettings::auditFilters($_GET), Workspace::tz($ws));
    }
}
