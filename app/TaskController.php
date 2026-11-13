<?php
/** Tasks page and the task forms (used from the Tasks page and from Project Details). */
final class TaskController {
    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return [$u, Workspace::ensureFor((int)$u['id'])]; }

    /** Where a form came from. Only our own pages are accepted. @return array{0:string,1:array} [redirect path, ['project', id] or ['task', id] or ['tasks', filters]] */
    private static function back(): array {
        $b = (string)($_POST['back'] ?? '');
        if (preg_match('/^\/project\?id=(\d+)$/', $b, $m)) return [$b, ['project', (int)$m[1]]];
        if (preg_match('/^\/task\?id=(\d+)(&tab=(logs|activity))?$/', $b, $m)) return [$b, ['task', (int)$m[1]]];
        parse_str((string)parse_url($b, PHP_URL_QUERY), $q);
        $f = Task::cleanFilters(str_starts_with($b, '/tasks') ? $q : []);
        return [Task::path($f), ['tasks', $f]];
    }

    /** The tab a task form was sent from (its "back" address). */
    private static function tabOf(): string { return preg_match('/&tab=(logs|activity)$/', (string)($_POST['back'] ?? ''), $m) ? $m[1] : 'overview'; }

    // ---------- list ----------

    /** $form = a task form that came back with errors (its modal reopens with the values kept). */
    private static function page(array $u, array $ws, array $f, ?array $form = null): void {
        $uid = (int)$u['id']; $list = null; $projects = $addable = []; $failed = false;
        try { $list = Task::listing($uid, $ws, $f); $projects = Task::projectNames($uid, $ws); $addable = Task::addable($uid, $ws); }
        catch (Throwable $e) { error_log((string)$e); $failed = true; }
        render('tasks/index', ['title' => 'Tasks', 'user' => $u, 'nav' => 'tasks', 'crumb' => 'Tasks', 'ws' => $ws, 'tz' => Workspace::tz($ws), 'f' => $f, 'list' => $list,
            'projects' => $projects, 'addable' => $addable, 'failed' => $failed, 'taskForm' => $form, 'back' => Task::path($f)], 'app');
    }

    static function index(): void { [$u, $ws] = self::who(); self::page($u, $ws, Task::cleanFilters($_GET)); }

    /** Shows the page a form came from again, with the form (and its errors) reopened. */
    private static function rerender(array $u, array $ws, array $ctx, array $form): void {
        $uid = (int)$u['id'];
        if ($ctx[0] === 'project' && ($pr = Project::find($ctx[1], $uid, $ws))) ProjectController::details($u, $ws, $pr, 'all', 1, null, $form);
        elseif ($ctx[0] === 'task' && ($t = Task::full($ctx[1], $uid, $ws))) self::details($u, $ws, $t, self::tabOf(), $form);
        else self::page($u, $ws, $ctx[0] === 'tasks' ? $ctx[1] : Task::cleanFilters([]), $form);
    }

    // ---------- create / edit ----------

    /** Create (no id) or edit (id). Owner / Admin / Manager, or the project's leader / creator. */
    static function save(): void {
        [$u, $ws] = self::who(); $uid = (int)$u['id']; [$back, $ctx] = self::back();
        $id = (int)($_POST['id'] ?? 0); $old = null;
        if ($id) { $old = Task::find($id, $uid, $ws); $p = $old ? Task::projectOf($old) : null; }
        elseif ((int)($_POST['project_id'] ?? 0)) $p = Project::find((int)$_POST['project_id'], $uid, $ws);
        else {   // a new task without a project: show the form again
            [$v, $err] = Task::validate($_POST, []); $err['project_id'] = 'This field is required.'; $v['assignee_id'] = 0; unset($err['assignee_id']);
            http_response_code(422); $form = ['id' => 0, 'values' => $v + ['project_id' => ''], 'errors' => $err];
            self::rerender($u, $ws, $ctx, $form); return;
        }
        if (!$p) self::denied($back, 'That task is not available.');
        if ($p['archived_at'] || !Task::canAdd($ws, $p, $uid)) self::denied($back);
        $pid = (int)$p['id'];
        [$v, $err] = Task::validate($_POST, array_keys(Project::people($pid)));
        if ($old && $v['status'] !== $old['status'] && Task::timerActive($id)) $err['status'] = "Status can't be changed while this task's timer is active.";
        $fail = function (array $extra) use ($u, $ws, $ctx, $id, $pid, $v, $err) {
            self::rerender($u, $ws, $ctx, ['id' => $id, 'values' => $v + ['project_id' => $pid], 'errors' => $err] + $extra);
        };
        if ($err) { http_response_code(422); $fail([]); return; }
        try {
            if ($old) {
                Task::update($old, $v); Activity::log((int)$ws['id'], $pid, $uid, $v['status'] === 'completed' && $old['status'] !== 'completed' ? 'task_completed' : 'task_updated', $v['title']);
                foreach (Task::changes($old, $v, Project::people($pid)) as [$type, $from, $to]) Activity::logTask($id, $uid, $type, $from, $to);
            }
            else { Task::create($pid, $uid, $v); Activity::log((int)$ws['id'], $pid, $uid, 'task_created', $v['title']); }
        } catch (Throwable $e) { error_log((string)$e); http_response_code(500); $fail(['failed' => true]); return; }
        $old ? flash('ok', 'Changes to “' . $v['title'] . '” were saved.', 'Task updated') : flash('ok', '“' . $v['title'] . '” was added to ' . $p['name'] . '.', 'Task created successfully');
        redirect($back . ($ctx[0] === 'project' ? '#tasks' : ''));
    }

    // ---------- quick complete / delete ----------

    /** The round checkbox on a row: complete the task, or put it back to To Do. */
    static function toggle(): void {
        [$u, $ws] = self::who(); $uid = (int)$u['id']; [$back] = self::back();
        $t = Task::find((int)($_POST['id'] ?? 0), $uid, $ws);
        if (!$t || $t['project_archived'] || !Task::canAdd($ws, $t, $uid)) self::denied($back);
        if (Task::timerActive((int)$t['id'])) { flash('error', "Status can't be changed while this task's timer is active.", 'Could not save changes'); redirect($back); }
        $done = $t['status'] !== 'completed';
        try { Task::update($t, ['title' => $t['title'], 'status' => $done ? 'completed' : 'todo', 'priority' => $t['priority'], 'assignee_id' => (int)$t['assignee_id'], 'due_date' => (string)$t['due_date']]);
              Activity::log((int)$ws['id'], (int)$t['project_id'], $uid, $done ? 'task_completed' : 'task_updated', $t['title']);
              Activity::logTask((int)$t['id'], $uid, 'status', Task::STATUS[$t['status']], Task::STATUS[$done ? 'completed' : 'todo']); }
        catch (Throwable $e) { error_log((string)$e); flash('error', 'Your changes were not saved. Check your connection and try again.', 'Could not save changes'); redirect($back); }
        flash('ok', $done ? '“' . $t['title'] . '” is marked as completed.' : '“' . $t['title'] . '” is back in To Do.', $done ? 'Task completed' : 'Task reopened');
        redirect($back);
    }

    /** Delete is only for tasks without saved time logs. Tasks with logs keep their history. */
    static function delete(): void {
        [$u, $ws] = self::who(); $uid = (int)$u['id']; [$back] = self::back();
        $t = Task::find((int)($_POST['id'] ?? 0), $uid, $ws);
        if (!$t || $t['project_archived'] || !Task::canAdd($ws, $t, $uid)) self::denied($back);
        if (Task::logs((int)$t['id'])[0] > 0) self::denied($back, 'This task has saved time logs, so it cannot be deleted. Saved history is never removed.', 'Task kept');
        try { Task::delete($t); Activity::log((int)$ws['id'], (int)$t['project_id'], $uid, 'task_deleted', $t['title']); }
        catch (Throwable $e) { error_log((string)$e); flash('error', 'Your changes were not saved. Check your connection and try again.', 'Could not delete task'); redirect($back); }
        flash('ok', '“' . $t['title'] . '” was deleted.', 'Task deleted'); redirect($back);   // from Task Details the form sends the project page as "back"
    }

    private static function denied(string $to, string $msg = "You don't have permission to manage this resource. Contact a workspace administrator if you think this is a mistake.", string $title = 'Permission denied'): never {
        flash('error', $msg, $title); redirect($to);
    }

    // ---------- Task Details ----------

    /** $form = the edit-task form (or details form, with 'kind' => 'details') that came back with errors. */
    private static function details(array $u, array $ws, array $t, string $tab, ?array $form = null): void {
        $uid = (int)$u['id']; $tz = Workspace::tz($ws); $tab = in_array($tab, ['logs', 'activity'], true) ? $tab : 'overview';
        $manage = Task::canAdd($ws, Task::projectOf($t), $uid) && !$t['project_archived']; $failed = false; $data = [];
        try {
            $pid = (int)$t['project_id']; $people = Project::people($pid);
            $counts = Task::counts($pid);
            $role = $t['assignee_id'] ? (string)Db::q('SELECT role FROM workspace_members WHERE workspace_id = ? AND user_id = ?', [(int)$ws['id'], (int)$t['assignee_id']])->fetchColumn() : '';
            $data = ['people' => $people, 'counts' => $counts, 'role' => $role,
                'logs' => Task::logsPage((int)$t['id'], $tab === 'logs' ? max(1, (int)($_GET['lpage'] ?? 1)) : 1, $tab === 'logs' ? 12 : 3),
                'feed' => $tab === 'activity' ? Activity::taskFeed($t, max(1, (int)($_GET['apage'] ?? 1)), 10) : null];
        } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        render('tasks/show', ['title' => $t['title'], 'user' => $u, 'nav' => 'tasks', 'crumb' => 'Projects', 'crumb2' => $t['project_name'], 'crumb2url' => url('/project?id=' . (int)$t['project_id']), 'crumb3' => $t['title'],
            'ws' => $ws, 't' => $t, 'tz' => $tz, 'tab' => $tab, 'canEdit' => $manage, 'failed' => $failed, 'info' => $data, 'taskForm' => $form && ($form['kind'] ?? '') !== 'details' ? $form : null,
            'detailForm' => $form && ($form['kind'] ?? '') === 'details' ? $form : null, 'back' => '/task?id=' . (int)$t['id'] . ($tab === 'overview' ? '' : '&tab=' . $tab)], 'app');
    }

    /** Task Details. Unknown id, another workspace and "no access" all give the same not-found page. */
    static function show(): void {
        [$u, $ws] = self::who();
        $t = Task::full((int)($_GET['id'] ?? 0), (int)$u['id'], $ws);
        if (!$t) { http_response_code(404); render('tasks/not-found', ['title' => 'Task not found', 'user' => $u, 'nav' => 'tasks', 'crumb' => 'Tasks'], 'app'); return; }
        self::details($u, $ws, $t, (string)($_GET['tab'] ?? 'overview'));
    }

    /** "Edit description": description, notes and estimate. */
    static function saveDetails(): void {
        [$u, $ws] = self::who(); $uid = (int)$u['id']; [$back, $ctx] = self::back();
        $t = Task::full((int)($_POST['id'] ?? 0), $uid, $ws);
        if (!$t || $ctx[0] !== 'task') self::denied('/tasks', 'That task is not available.');
        if ($t['project_archived'] || !Task::canAdd($ws, Task::projectOf($t), $uid)) self::denied($back);
        [$v, $err] = Task::validateDetails($_POST);
        if ($err) { http_response_code(422); self::details($u, $ws, $t, self::tabOf(), ['kind' => 'details', 'values' => $v, 'errors' => $err]); return; }
        try {
            foreach (Task::saveDetails($t, $v, $uid) as $type) {
                if ($type === 'estimate') Activity::logTask((int)$t['id'], $uid, 'estimate', $t['estimate_minutes'] ? Ui::minutes((int)$t['estimate_minutes']) : '', $v['estimate'] ? Ui::minutes($v['estimate']) : '');
                else Activity::logTask((int)$t['id'], $uid, $type);
            }
            Activity::log((int)$ws['id'], (int)$t['project_id'], $uid, 'task_updated', $t['title']);
        } catch (Throwable $e) {
            error_log((string)$e); http_response_code(500); self::details($u, $ws, $t, self::tabOf(), ['kind' => 'details', 'values' => $v, 'errors' => [], 'failed' => true]); return;
        }
        flash('ok', 'Details of “' . $t['title'] . '” were saved.', 'Task updated'); redirect($back);
    }
}
