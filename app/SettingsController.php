<?php
/** Settings: Account (profile, password, sessions), Preferences (appearance, notifications) and Workspace Settings (Owner / Admin only). */
final class SettingsController {
    /** group => [tab key => [menu label, icon, lowest role that may see it, page address, page title, page sub-title]]. The left menu and every page check this one list. */
    const GROUPS = [
        'Account' => [
            'profile' => ['Profile information', 'user', 'viewer', '/settings/profile', 'Profile settings', 'Manage your account details and preferences'],
            'security' => ['Password', 'lock', 'viewer', '/settings/security', 'Password', 'Keep your account safe with a strong password'],
            'sessions' => ['Sessions', 'monitor', 'viewer', '/settings/sessions', 'Sessions', 'See where you are signed in and sign out of other devices'],
        ],
        'Preferences' => [
            'appearance' => ['Appearance & notifications', 'sliders', 'viewer', '/settings/appearance', 'Appearance & notifications', 'Choose how Krono looks and which emails you receive'],
        ],
        'Workspace Settings' => [
            'general' => ['General', 'building', 'admin', '/settings/workspace/general', 'Workspace Settings', 'Workspace identity and default preferences'],
            'roles' => ['Roles & Permissions', 'shield', 'admin', '/settings/workspace/roles', 'Workspace Settings', 'Built-in roles and what each one can see and do'],
            'access' => ['Security & Access', 'key', 'admin', '/settings/workspace/access', 'Workspace Settings', 'Review access boundaries and security requirements'],
            'email' => ['Email Settings', 'mail', 'owner', '/settings/workspace/email', 'Workspace Settings', 'Configure invitation and system email delivery'],
            'audit' => ['Audit Log', 'history', 'admin', '/settings/workspace/audit', 'Workspace Settings', 'Review important workspace changes'],
        ],
    ];

    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return $u; }
    private static function go(string $tab): never { foreach (self::GROUPS as $g) if (isset($g[$tab])) redirect($g[$tab][3]); redirect('/settings'); }

    /** The menu entry for $key when the signed-in person may use it, else null. */
    private static function find(string $key): ?array {
        foreach (self::GROUPS as $g) if (isset($g[$key])) return Workspace::atLeast(Workspace::role(), $g[$key][2]) ? $g[$key] : null;
        return null;
    }
    /** Groups with only the entries this person may use; a group with nothing left is dropped (so "Workspace Settings" disappears for Managers, Members and Viewers). */
    static function menu(): array {
        $role = Workspace::role(); $out = [];
        foreach (self::GROUPS as $name => $items) { $ok = array_filter($items, fn($i) => Workspace::atLeast($role, $i[2])); if ($ok) $out[$name] = $ok; }
        return $out;
    }
    /** Opens a tab for someone allowed to see it. Anyone else gets the plain "page not found" so the page's existence is not shown. */
    private static function open(string $key): array {
        $u = self::who(); $e = self::find($key);
        if (!$e) { http_response_code(404); render('settings/not-found', ['title' => 'Page not found', 'user' => $u, 'nav' => 'settings', 'crumb' => 'Settings'], 'app'); exit; }
        return [$u, $e];
    }

    private static function page(string $key, array $u, array $extra = [], int $status = 200): void {
        $e = self::find($key); $uid = (int)$u['id']; $ws = Workspace::ensureFor($uid);
        $d = ['title' => 'Settings', 'user' => $u, 'nav' => 'settings', 'crumb' => 'Settings', 'crumburl' => '/settings', 'crumb2' => $e[0], 'tab' => $key, 'entry' => $e, 'menu' => self::menu(), 'ws' => $ws, 'wid' => (int)$ws['id'], 'tz' => Workspace::tz($ws)];
        if ($key === 'profile') { $d['profile'] = Account::profile($uid); $d['pending'] = Account::pendingEmail($uid); $d['zones'] = Account::timezones(); $d['formats'] = array_keys(Account::DATE_FORMATS); }
        if ($key === 'sessions') $d['sessions'] = Account::sessions($uid);
        if ($key === 'appearance') $d['prefs'] = Account::prefs($uid);
        if ($key === 'general') { $d['g'] = WsSettings::general((int)$ws['id']); $d['zones'] = Account::timezones(); }
        if ($key === 'roles') $d['counts'] = WsSettings::roleCounts((int)$ws['id']);
        if ($key === 'email') { $d['m'] = WsSettings::mail((int)$ws['id']); $d['serverMail'] = WsSettings::serverMail(); }
        if ($status !== 200) http_response_code($status);
        render('settings/' . ($e[2] === 'admin' || $e[2] === 'owner' ? 'workspace/' : '') . $key, $extra + $d, 'app');
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
    static function roles(): void { [$u] = self::open('roles'); self::page('roles', $u); }
    static function access(): void { [$u] = self::open('access'); self::page('access', $u); }
    static function email(): void { [$u] = self::open('email'); self::page('email', $u); }

    static function saveGeneral(): void {
        [$u] = self::open('general'); $wid = (int)Workspace::ensureFor((int)$u['id'])['id'];
        [$err, $clean] = WsSettings::checkGeneral($_POST);
        if ($err) { self::page('general', $u, ['errors' => $err, 'form' => $clean], 422); return; }
        try { WsSettings::saveGeneral($wid, (int)$u['id'], $clean); }
        catch (Throwable $e) { error_log((string)$e); self::page('general', $u, ['errors' => ['_' => 'Your changes were not saved. Check your connection and try again. Your input has been kept.'], 'form' => $clean], 500); return; }
        flash('ok', 'Workspace defaults were saved. Saved records and timestamps are not changed.', 'Workspace updated'); self::go('general');
    }

    static function saveEmail(): void {
        [$u] = self::open('email'); $wid = (int)Workspace::ensureFor((int)$u['id'])['id'];
        [$err, $clean] = WsSettings::checkMail($_POST, WsSettings::mail($wid));
        if ($err) { unset($clean['smtp_pass']); self::page('email', $u, ['errors' => $err, 'form' => $clean], 422); return; }
        try { WsSettings::saveMail($wid, (int)$u['id'], $clean); }
        catch (Throwable $e) { error_log((string)$e); unset($clean['smtp_pass']); self::page('email', $u, ['errors' => ['_' => 'Your changes were not saved. Check your connection and try again. Your input has been kept.'], 'form' => $clean], 500); return; }
        flash('ok', 'Email settings were saved.', 'Email settings saved'); self::go('email');
    }

    static function testEmail(): void {
        [$u] = self::open('email'); $err = WsSettings::sendTest((int)Workspace::ensureFor((int)$u['id'])['id'], $u);
        $err === '' ? flash('ok', 'A test email was sent to ' . $u['email'] . '.', 'Test email sent') : flash('error', $err, 'Test email failed'); self::go('email');
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
