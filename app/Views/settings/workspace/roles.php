<?php
/* Vars: $counts (role => active members). Read-only: the five built-in roles. */
include __DIR__ . '/../_nav.php';
$noteTitle = 'Permission checks run on the server'; $noteText = 'Hiding a page is not the only protection: every request is checked again by the server. Manager access is limited to the people who report to that manager.';
include __DIR__ . '/../_note.php';
$roles = [
    'owner' => ['Full workspace control', 'Workspace-wide', 'Everything in the workspace, including workspace settings and all members.'],
    'admin' => ['Manage workspace members and settings', 'Workspace-wide', 'Members, roles, projects, tasks, reports and workspace settings.'],
    'manager' => ['Manage assigned employees', 'Assigned employees only', 'Their own work and the people who report to them. Can view the team, not change it.'],
    'member' => ['Standard member access', 'Own data', 'Their own tasks, time logs and attendance.'],
    'viewer' => ['Read-only access', 'Own data, read only', 'Can view their own data but cannot create or change records.'],
];
$groups = [['Projects & Tasks', 'projects', 'Create, edit and assign projects and tasks. Needs Manager or higher.'], ['Reports & PDF export', 'reports', 'View reports for the people in your scope, and export them.'], ['Teams & Roles', 'team', 'Invite members and change roles. Owner and Admin only.'], ['Attendance & time logs', 'attendance', 'View attendance and time logs for the people in your scope.']];
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rl-title">
    <h2 class="m-0 text-base" id="rl-title">Roles</h2>
    <p class="mt-1 mb-4 text-xs text-muted">Krono has five built-in roles. Every member has exactly one role in the workspace. Change a person's role in Teams.</p>
    <div class="relative overflow-x-auto"><table class="min-w-170">
      <thead><tr><th>Role</th><th>Type</th><th>Data scope</th><th>Members</th><th class="w-0">Access</th></tr></thead>
      <tbody>
      <?php foreach ($roles as $k => [$sub, $scope, $access]): ?>
        <tr><td><div class="text-xs font-bold"><?= e(Team::ROLE_LABEL[$k]) ?></div><div class="text-micro text-muted"><?= e($sub) ?></div></td>
          <td><span class="pill pill-line">Built-in</span></td><td class="whitespace-nowrap"><?= e($scope) ?></td><td class="font-semibold"><?= (int)($counts[$k] ?? 0) ?></td>
          <td class="min-w-64 text-tiny leading-copy text-muted"><?= e($access) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="pg-title">
    <h2 class="m-0 text-base" id="pg-title">What each area covers</h2>
    <p class="mt-1 mb-4.5 text-xs text-muted">Access is decided by role. The data a person can see is always limited to their own scope.</p>
    <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
      <?php foreach ($groups as [$t, $ic, $text]): ?>
        <div class="flex items-center gap-3 rounded-card border border-line p-3.5"><span class="flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon($ic, 18) ?></span><div class="min-w-0"><div class="text-tiny font-bold"><?= e($t) ?></div><div class="text-micro leading-copy text-muted"><?= e($text) ?></div></div></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
