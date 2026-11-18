<?php
/** Pages that are not built yet. They keep the menu working (same shell) until their stage. */
final class PageController {
    const SOON = [ // path => [nav key, label, icon]
        '/projects' => ['projects', 'Projects', 'projects'],
        '/reports' => ['reports', 'Reports', 'reports'], '/calendar' => ['calendar', 'Calendar', 'calendar'],
    ];
    static function soon(string $path): void {
        $u = Auth::user(); if (!$u) redirect('/login');
        [$key, $label, $icon] = self::SOON[$path];
        if ($key === 'reports') Perm::gate(Workspace::ensureFor((int)$u['id']), 'reports.view', $u, 'reports', 'Reports');
        render('pages/soon', ['title' => $label, 'user' => $u, 'nav' => $key, 'crumb' => $label, 'label' => $label, 'icon' => $icon], 'app');
    }
}
