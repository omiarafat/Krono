<?php
final class ProjectController {
    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return [$u, Workspace::ensureFor((int)$u['id'])]; }

    /** Where a form came from. Only our own two project URLs are accepted. @return array{0:string,1:int} [redirect path, project id or 0] */
    private static function back(): array {
        $b = (string)($_POST['back'] ?? '');
        return preg_match('/^\/project\?id=(\d+)$/', $b, $m) ? [$b, (int)$m[1]] : ['/projects', 0];
    }

    // ---------- list ----------

    /** The list page. $form is set when a create/edit form came back with errors (the modal reopens with the values kept). */
    private static function page(array $u, array $ws, array $f, ?array $form = null): void {
        $list = null; $failed = false;
        try { $list = Project::listing((int)$u['id'], $ws, $f); } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        render('projects/index', ['title' => 'Projects', 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects', 'ws' => $ws, 'canManage' => Project::canManage($ws),
            'f' => $f, 'list' => $list, 'failed' => $failed, 'form' => $form, 'back' => '/projects'], 'app');
    }

    static function index(): void { [$u, $ws] = self::who(); self::page($u, $ws, Project::cleanFilters($_GET)); }

    // ---------- details ----------

    /** The Project Details page. $form / $taskForm = a form that came back with errors (its modal reopens). */
    private static function details(array $u, array $ws, array $p, string $tab, int $actPage, ?array $form = null, ?array $taskForm = null): void {
        $tz = Workspace::tz($ws); $id = (int)$p['id'];
        $uid = (int)$u['id']; $manage = Project::canManage($ws) && !$p['archived_at']; $add = Task::canAdd($ws, $p, $uid) && !$p['archived_at'];
        $failed = false; $s = $tasks = $feed = null;
        try { $s = Project::summary($p); $tasks = Task::forProject($id, $tab); $feed = Activity::feed($id, $actPage, isset($_GET['activity']) ? 12 : 4); }
        catch (Throwable $e) { error_log((string)$e); $failed = true; }
        render('projects/show', ['title' => $p['name'], 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects', 'crumb2' => $p['name'], 'ws' => $ws, 'p' => $p, 's' => $s, 'tasks' => $tasks, 'feed' => $feed,
            'tab' => $tab, 'tz' => $tz, 'canManage' => $manage, 'canAdd' => $add, 'failed' => $failed, 'form' => $form, 'taskForm' => $taskForm,
            'activityAll' => isset($_GET['activity']), 'back' => '/project?id=' . $id], 'app');
    }

    /** Project Details. Unknown id, another workspace and "no access" all give the same not-found page (never reveals that a project exists). */
    static function show(): void {
        [$u, $ws] = self::who();
        $p = Project::find((int)($_GET['id'] ?? 0), (int)$u['id'], $ws);
        if (!$p) { http_response_code(404); render('projects/not-found', ['title' => 'Project not found', 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects'], 'app'); return; }
        $tab = (string)($_GET['tab'] ?? 'all'); if ($tab !== 'all' && !isset(Task::STATUS[$tab])) $tab = 'all';
        self::details($u, $ws, $p, $tab, max(1, (int)($_GET['apage'] ?? 1)));
    }

    // ---------- create / edit / archive ----------

    /** Create (no id) or edit (id). Only Owner / Admin / Manager. */
    static function save(): void {
        [$u, $ws] = self::who(); [$back, $backId] = self::back();
        if (!Project::canManage($ws)) self::denied($back);
        $id = (int)($_POST['id'] ?? 0); $existing = null;
        if ($id) { $existing = Project::find($id, (int)$u['id'], $ws); if (!$existing || $existing['archived_at']) self::denied($back, 'That project is not available.'); }
        [$v, $err] = Project::validate($_POST);
        $fail = function (array $extra) use ($u, $ws, $backId, $id, $v, $err) {
            $form = ['id' => $id, 'values' => $v, 'errors' => $err] + $extra;
            if ($backId && ($p = Project::find($backId, (int)$u['id'], $ws))) self::details($u, $ws, $p, 'all', 1, $form);
            else self::page($u, $ws, Project::cleanFilters([]), $form);
        };
        if ($err) { http_response_code(422); $fail([]); return; }
        try {
            if ($existing) { Project::update($existing, $v, (int)$u['id']); flash('ok', $v['name'] . ' was updated.', 'Changes saved'); }
            else { Project::create((int)$u['id'], $ws, $v); flash('ok', $v['name'] . ' is ready. You can now add tasks to this project.', 'Project created successfully'); }
        } catch (Throwable $e) { error_log((string)$e); http_response_code(500); $fail(['failed' => true]); return; }
        redirect($back);
    }

    static function archive(): void {
        [$u, $ws] = self::who();
        if (!Project::canManage($ws)) self::denied('/projects');
        $p = Project::find((int)($_POST['id'] ?? 0), (int)$u['id'], $ws);
        if (!$p || $p['archived_at']) self::denied('/projects', 'That project is not available.');
        try { $n = Project::archive($p, (int)$u['id']); }
        catch (Throwable $e) { error_log((string)$e); flash('error', 'Your changes were not saved. Check your connection and try again.', 'Could not save changes'); redirect('/projects'); }
        flash('ok', $p['name'] . ' is hidden from active projects. ' . $n['tasks'] . ' task' . ($n['tasks'] === 1 ? '' : 's') . ' and ' . $n['logs'] . ' saved time log' . ($n['logs'] === 1 ? '' : 's') . ' were kept.', 'Project archived');
        redirect('/projects');
    }

    // ---------- add task ----------

    /** "Add Task" on Project Details. */
    static function addTask(): void {
        [$u, $ws] = self::who(); $uid = (int)$u['id'];
        $p = Project::find((int)($_POST['project_id'] ?? 0), $uid, $ws);
        if (!$p) self::denied('/projects', 'That project is not available.');
        $back = '/project?id=' . (int)$p['id'];
        if ($p['archived_at'] || !Task::canAdd($ws, $p, $uid)) self::denied($back);
        $people = array_keys(Project::people((int)$p['id']));
        [$v, $err] = Task::validate($_POST, $people);
        $fail = function (array $extra) use ($u, $ws, $p, $v, $err) { self::details($u, $ws, $p, 'all', 1, null, ['values' => $v, 'errors' => $err] + $extra); };
        if ($err) { http_response_code(422); $fail([]); return; }
        try { Task::create((int)$p['id'], $uid, $v); Activity::log((int)$ws['id'], (int)$p['id'], $uid, 'task_created', $v['title']); }
        catch (Throwable $e) { error_log((string)$e); http_response_code(500); $fail(['failed' => true]); return; }
        flash('ok', '“' . $v['title'] . '” was added to ' . $p['name'] . '.', 'Task created successfully');
        redirect($back . '#tasks');
    }

    /** Task Details is a later page. The row link already works and checks access. */
    static function taskSoon(): void {
        [$u, $ws] = self::who();
        $t = Db::q('SELECT t.title, t.project_id FROM tasks t WHERE t.id = ?', [(int)($_GET['id'] ?? 0)])->fetch();
        if (!$t || !Project::find((int)$t['project_id'], (int)$u['id'], $ws)) { http_response_code(404); render('projects/not-found', ['title' => 'Task not found', 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects'], 'app'); return; }
        render('pages/soon', ['title' => $t['title'], 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects', 'label' => 'Task details', 'icon' => 'tasks'], 'app');
    }

    private static function denied(string $to, string $msg = "You don't have permission to manage this resource. Contact a workspace administrator if you think this is a mistake."): never {
        flash('error', $msg, 'Permission denied'); redirect($to);
    }
}
