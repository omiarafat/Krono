<?php
final class ProjectController {
    private static function who(): array { $u = Auth::user(); if (!$u) redirect('/login'); return [$u, Workspace::ensureFor((int)$u['id'])]; }

    /** The list page. $form is set when a create/edit form came back with errors (the modal reopens with the values kept). */
    private static function page(array $u, array $ws, array $f, ?array $form = null): void {
        $list = null; $failed = false;
        try { $list = Project::listing((int)$u['id'], $ws, $f); } catch (Throwable $e) { error_log((string)$e); $failed = true; }
        render('projects/index', ['title' => 'Projects', 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects', 'ws' => $ws, 'canManage' => Project::canManage($ws),
            'f' => $f, 'list' => $list, 'failed' => $failed, 'form' => $form], 'app');
    }

    static function index(): void { [$u, $ws] = self::who(); self::page($u, $ws, Project::cleanFilters($_GET)); }

    /** Create (no id) or edit (id). Only Owner / Admin / Manager. */
    static function save(): void {
        [$u, $ws] = self::who(); $f = Project::cleanFilters([]);
        if (!Project::canManage($ws)) self::denied();
        $id = (int)($_POST['id'] ?? 0); $existing = null;
        if ($id) { $existing = Project::find($id, (int)$u['id'], $ws); if (!$existing || $existing['archived_at']) self::denied('That project is not available.'); }
        [$v, $err] = Project::validate($_POST);
        if ($err) { http_response_code(422); self::page($u, $ws, $f, ['id' => $id, 'values' => $v, 'errors' => $err]); return; }
        try {
            if ($existing) { Project::update($id, $v); flash('ok', $v['name'] . ' was updated.', 'Changes saved'); }
            else { Project::create((int)$u['id'], $ws, $v); flash('ok', $v['name'] . ' is ready. You can now add tasks to this project.', 'Project created successfully'); }
        } catch (Throwable $e) {
            error_log((string)$e); http_response_code(500);
            self::page($u, $ws, $f, ['id' => $id, 'values' => $v, 'errors' => [], 'failed' => true]); return;
        }
        redirect('/projects');
    }

    static function archive(): void {
        [$u, $ws] = self::who();
        if (!Project::canManage($ws)) self::denied();
        $p = Project::find((int)($_POST['id'] ?? 0), (int)$u['id'], $ws);
        if (!$p || $p['archived_at']) self::denied('That project is not available.');
        try { $n = Project::archive((int)$p['id']); }
        catch (Throwable $e) { error_log((string)$e); flash('error', 'Your changes were not saved. Check your connection and try again.', 'Could not save changes'); redirect('/projects'); }
        flash('ok', $p['name'] . ' is hidden from active projects. ' . $n['tasks'] . ' task' . ($n['tasks'] === 1 ? '' : 's') . ' and ' . $n['logs'] . ' saved time log' . ($n['logs'] === 1 ? '' : 's') . ' were kept.', 'Project archived');
        redirect('/projects');
    }

    /** Project Details is a later page. The card link already works and checks access. */
    static function details(): void {
        [$u, $ws] = self::who();
        $p = Project::find((int)($_GET['id'] ?? 0), (int)$u['id'], $ws);
        if (!$p) { http_response_code(404); render('errors/404', ['title' => 'Project not found']); return; }
        render('pages/soon', ['title' => $p['name'], 'user' => $u, 'nav' => 'projects', 'crumb' => 'Projects', 'label' => 'Project details', 'icon' => 'projects'], 'app');
    }

    private static function denied(string $msg = "You don't have permission to manage this resource. Contact a workspace administrator if you think this is a mistake."): never {
        flash('error', $msg, 'Permission denied'); redirect('/projects');
    }
}
