<?php
/** First workspace of a new account: a short setup page after the email is verified. Nothing is created until the person submits it. */
final class WorkspaceSetupController {
    private static function user(): array {
        $u = Auth::user(); if (!$u) redirect('/login');
        if (Workspace::forUser((int)$u['id'])) redirect('/dashboard');   // already has a workspace: nothing to set up
        return $u;
    }
    private static function defaults(array $u): array {
        $tz = (string)($u['timezone'] ?? ''); if ($tz === '') $tz = (string)env('APP_TIMEZONE', 'Asia/Dhaka');
        $first = trim((string)strtok((string)$u['name'], ' '));
        return ['name' => ($first !== '' ? $first : 'My') . "'s Workspace", 'timezone' => $tz, 'country' => Workspace::countryOf($tz)];
    }
    static function show(): void {
        $u = self::user(); $d = self::defaults($u);
        render('workspace/setup', ['title' => 'Set up your workspace', 'form' => $d, 'errors' => [], 'zones' => Account::timezones(), 'u' => $u], 'auth');
    }
    static function save(): void {
        $u = self::user(); $d = self::defaults($u);
        $in = isset($_POST['skip']) ? $d : $_POST;     // "Skip optional details" keeps the suggested name, timezone and country
        if (isset($_POST['skip'])) $in['country'] = '';
        [$err, $v] = Workspace::checkSetup($in);
        if ($err) { render('workspace/setup', ['title' => 'Set up your workspace', 'form' => $v, 'errors' => array_values($err), 'bad' => $err, 'zones' => Account::timezones(), 'u' => $u], 'auth'); return; }
        try { Workspace::create((int)$u['id'], $v['name'], $v['timezone'], $v['country'], 'personal'); }
        catch (Throwable $e) {
            error_log((string)$e);
            render('workspace/setup', ['title' => 'Set up your workspace', 'form' => $v, 'errors' => ['We could not create the workspace. Please try again.'], 'zones' => Account::timezones(), 'u' => $u], 'auth'); return;
        }
        flash('ok', 'Your workspace is ready.'); redirect('/dashboard');
    }
}
