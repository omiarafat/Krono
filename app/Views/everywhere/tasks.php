<?php
/* All workspaces: my open tasks grouped by workspace. Vars: $groups (Everywhere::tasks) $failed */
$heading = 'Tasks'; $sub = 'Your tasks in your personal and linked workspaces';
include ROOT . '/app/Views/partials/page-heading.php';
include ROOT . '/app/Views/partials/scope-toggle.php';
$total = array_sum(array_map(fn($g) => count($g['tasks']), $groups ?? []));
?>
<div class="flex flex-col gap-4.5 pb-6.5">
<?php if ($failed): ?>
  <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert"><div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div><h2 class="text-base">Connection interrupted</h2><p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load your tasks. Please try again.</p><a class="btn btn-primary" href="<?= url('/tasks') ?>">Try again</a></div>
<?php elseif (!$groups || $total === 0): ?>
  <div class="panel-card px-6 py-10 text-center"><h2 class="text-base">No open tasks assigned to you</h2><p class="mt-1.5 mb-0 text-xs text-muted">Tasks assigned to you in any workspace will show up here.</p></div>
<?php else: foreach ($groups as $g): $w = $g['ws']; if (!$g['tasks']) continue; ?>
  <section class="panel-card px-5.5 py-5">
    <div class="mb-3 flex items-center justify-between gap-4"><div class="flex items-center gap-2"><h2><?= e($w['name']) ?></h2><span class="rounded-full bg-soft px-2 py-0.5 text-nano font-bold text-brand"><?= $w['type'] === 'company' ? 'Company' : 'Personal' ?></span><span class="text-tiny text-muted"><?= count($g['tasks']) ?> open</span></div><?= Everywhere::openForm((int)$w['id'], '/tasks', 'Open workspace') ?></div>
    <div class="w-full overflow-x-auto"><table>
      <thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Priority</th><th>Due</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($g['tasks'] as $t): $late = $t['due_date'] && $t['due_date'] < $g['today']; ?>
        <tr><td><strong class="text-xs"><?= e($t['title']) ?></strong></td><td><?= e($t['project']) ?></td><td><?= Ui::tag('task', $t['status']) ?></td><td><?= Ui::tag('priority', $t['priority']) ?></td>
          <td class="<?= $late ? 'font-semibold text-tag-amber' : '' ?>"><?= $t['due_date'] ? e((new DateTimeImmutable($t['due_date']))->format(Account::df())) : '—' ?></td>
          <td class="text-right"><?= Everywhere::openForm((int)$w['id'], '/task?id=' . (int)$t['id'], 'Open') ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
<?php endforeach; endif; ?>
</div>
