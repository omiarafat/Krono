<?php
/**
 * Workspaces and roles. One workspace = one company/team; every member has ONE role in it.
 * Roles (strongest first): owner > admin > manager > member > viewer.
 * A new account creates its first (personal) workspace on the setup page, or joins one by invitation.
 */
final class Workspace {
    const ROLES = ['viewer' => 1, 'member' => 2, 'manager' => 3, 'admin' => 4, 'owner' => 5];

    /** True when $role is at least as strong as $min (e.g. atLeast('manager', 'member') is true). */
    static function atLeast(?string $role, string $min): bool { return (self::ROLES[$role ?? ''] ?? 0) >= (self::ROLES[$min] ?? 99); }

    /** The user's current workspace (the one they last joined, else their first active membership) as ['id','name','timezone','role'], or null. */
    static function forUser(int $uid): ?array {
        $sel = 'SELECT w.id, w.name, w.timezone, w.type, m.role, m.custom_role_id FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id';
        $old = 'SELECT w.id, w.name, w.timezone, m.role FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id';
        try {   // current_workspace_id exists after "php database/migrate.php"; until then fall back to the first membership
            $r = Db::q("$sel JOIN users u ON u.id = m.user_id WHERE m.user_id = ? AND m.status = 'active' ORDER BY (CASE WHEN w.id = u.current_workspace_id THEN 0 ELSE 1 END), m.created_at, w.id LIMIT 1", [$uid])->fetch();
        } catch (Throwable $e) {
            $r = Db::q("$old WHERE m.user_id = ? AND m.status = 'active' ORDER BY m.created_at, w.id LIMIT 1", [$uid])->fetch();
        }
        return $r ?: null;
    }

    /** Makes a workspace the user's current one (only if they are an active member of it). @return bool false when they are not a member */
    static function setCurrent(int $uid, int $wid): bool {
        if (!Db::q("SELECT 1 FROM workspace_members WHERE user_id = ? AND workspace_id = ? AND status = 'active'", [$uid, $wid])->fetchColumn()) return false;
        try { Db::q('UPDATE users SET current_workspace_id = ? WHERE id = ?', [$wid, $uid]); } catch (Throwable $e) { error_log((string)$e); }
        return true;
    }

    /** Every workspace the person is an active member of (for the switcher): id, name, type, role, members. */
    static function mine(int $uid): array {
        return Db::q("SELECT w.id, w.name, w.type, m.role, (SELECT COUNT(*) FROM workspace_members x WHERE x.workspace_id = w.id AND x.status = 'active') AS members
            FROM workspace_members m JOIN workspaces w ON w.id = m.workspace_id WHERE m.user_id = ? AND m.status = 'active' ORDER BY w.name, w.id", [$uid])->fetchAll();
    }
    const MAX_PER_USER = 20;
    const INDUSTRIES = ['software' => 'Software & IT', 'agency' => 'Agency & creative', 'consulting' => 'Consulting', 'education' => 'Education', 'finance' => 'Finance', 'healthcare' => 'Healthcare',
        'manufacturing' => 'Manufacturing', 'retail' => 'Retail & e-commerce', 'nonprofit' => 'Non-profit', 'other' => 'Other'];

    /** Validates the company form. $upgrade: the name may stay empty (keeps the current one). @return array [errors, clean values] */
    static function checkCompany(array $in, bool $upgrade): array {
        [$err, $v] = self::checkSetup($in + ['name' => '']);
        if ($upgrade && trim((string)($in['name'] ?? '')) === '') unset($err['name']);
        $co = trim(preg_replace('/\s+/u', ' ', (string)($in['company_name'] ?? ''))); $ind = (string)($in['industry'] ?? '');
        if (mb_strlen($co) > 160) $err['company_name'] = 'Keep the company name under 160 characters.';
        if ($ind !== '' && !isset(self::INDUSTRIES[$ind])) $err['industry'] = 'Choose an industry from the list.';
        return [$err, $v + ['company_name' => $co, 'industry' => $ind]];
    }

    /** Turns a Personal Workspace the person owns into a Company Workspace in place (same id, all data stays). @return string '' or an error message */
    static function upgrade(int $uid, int $wid, array $v): string {
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            $w = Db::q("SELECT w.name, w.type FROM workspaces w JOIN workspace_members m ON m.workspace_id = w.id WHERE w.id = ? AND m.user_id = ? AND m.status = 'active' AND m.role = 'owner'", [$wid, $uid])->fetch();
            if (!$w) { $pdo->rollBack(); return 'Only the Owner of a workspace can upgrade it.'; }
            if ($w['type'] !== 'personal') { $pdo->rollBack(); return 'That workspace is already a company workspace.'; }
            Db::q("UPDATE workspaces SET type = 'company', name = ?, company_name = ?, industry = ? WHERE id = ? AND type = 'personal'", [$v['name'] !== '' ? $v['name'] : $w['name'], $v['company_name'] === '' ? null : $v['company_name'], $v['industry'] === '' ? null : $v['industry'], $wid]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        Team::audit($wid, $uid, $uid, 'workspace_upgraded', 'Personal → Company: ' . mb_substr($v['name'] !== '' ? $v['name'] : (string)$w['name'], 0, 120));
        return '';
    }

    /** The user's current workspace. A person with none yet is sent to the setup page (or to a waiting invitation) - nothing is created silently. */
    static function ensureFor(int $uid): array {
        $ws = self::forUser($uid); if ($ws) return $ws;
        if (is_api_request()) throw new RuntimeException('No workspace yet.');
        redirect(TeamController::pendingInvite() ?? '/workspace/setup');
    }

    /** Short country list for the setup form: ISO code => name. */
    const COUNTRIES = ['AR' => 'Argentina', 'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BR' => 'Brazil', 'CA' => 'Canada', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
        'DK' => 'Denmark', 'EG' => 'Egypt', 'FI' => 'Finland', 'FR' => 'France', 'DE' => 'Germany', 'GR' => 'Greece', 'HK' => 'Hong Kong', 'IN' => 'India', 'ID' => 'Indonesia', 'IE' => 'Ireland',
        'IL' => 'Israel', 'IT' => 'Italy', 'JP' => 'Japan', 'JO' => 'Jordan', 'KE' => 'Kenya', 'KW' => 'Kuwait', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'MX' => 'Mexico', 'NP' => 'Nepal',
        'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar',
        'RO' => 'Romania', 'RU' => 'Russia', 'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam'];

    /** Country code guessed from a timezone name ('' when unknown or not in the list). */
    static function countryOf(string $tz): string {
        foreach (array_keys(self::COUNTRIES) as $c) if (in_array($tz, DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $c), true)) return $c;
        return '';
    }

    /** Validates the setup form. @return array [errors by field, clean values] */
    static function checkSetup(array $in): array {
        $err = []; $name = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')));
        $tz = trim((string)($in['timezone'] ?? '')); $cc = strtoupper(trim((string)($in['country'] ?? '')));
        if (mb_strlen($name) < 2) $err['name'] = 'Enter a workspace name (at least 2 characters).';
        elseif (mb_strlen($name) > 120) $err['name'] = 'Keep the name under 120 characters.';
        if ($tz === '') $tz = (string)env('APP_TIMEZONE', 'Asia/Dhaka');
        elseif (!in_array($tz, DateTimeZone::listIdentifiers(), true)) $err['timezone'] = 'Choose a timezone from the list.';
        if ($cc !== '' && !isset(self::COUNTRIES[$cc])) $err['country'] = 'Choose a country from the list.';
        return [$err, ['name' => $name, 'timezone' => $tz, 'country' => $cc]];
    }

    /** Creates a workspace and makes the person its Owner, all or nothing. @return int new workspace id */
    static function create(int $uid, string $name, string $tz, string $country = '', string $type = 'personal', string $company = '', string $industry = ''): int {
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            Db::q('INSERT INTO workspaces (name, timezone, country, type, company_name, industry, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', [mb_substr($name, 0, 120), $tz, $country === '' ? null : $country, $type === 'company' ? 'company' : 'personal', $company === '' ? null : mb_substr($company, 0, 160), $industry === '' ? null : $industry, $uid]);
            $id = (int)$pdo->lastInsertId();
            Db::q("INSERT INTO workspace_members (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')", [$id, $uid]);
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        self::setCurrent($uid, $id);
        Team::audit($id, $uid, $uid, 'workspace_created', ucfirst($type === 'company' ? 'company' : 'personal') . ' workspace: ' . mb_substr($name, 0, 120));
        return $id;
    }

    /** The signed-in person's current workspace row (looked up once per request), or null. */
    static function current(): ?array { static $c = false; if ($c === false) { $u = Auth::user(); $c = $u ? self::forUser((int)$u['id']) : null; } return $c; }

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
