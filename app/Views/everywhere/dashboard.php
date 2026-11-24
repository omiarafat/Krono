<?php
/* All workspaces: my own numbers per workspace. Vars: $rows (Everywhere::summary) $failed $scopeBase $scopeNow */
$heading = 'Dashboard'; $sub = 'Your own work across all your workspaces';
include ROOT . '/app/Views/partials/page-heading.php';
include ROOT . '/app/Views/partials/scope-toggle.php';
$sum = fn(string $k) => array_sum(array_map(fn($r) => (int)($r[$k] ?? 0), $rows ?? []));
$note = 'm-0 text-tiny text-muted'; $dash = '—';
$cards = [['projects', 'Active projects', sprintf('%02d', $sum('projects')), 'In all workspaces'], ['tasks', 'Open tasks', sprintf('%02d', $sum('open')), $sum('overdue') . ' overdue'], ['time', 'Saved time', fmt_hm($sum('month')), 'This month, all workspaces'], ['team', 'Workspaces', sprintf('%02d', count($rows ?? [])), 'You belong to']];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
<?php if ($failed): ?>
  <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert"><div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div><h2 class="text-base">Connection interrupted</h2><p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load your workspaces. Please try again.</p><a class="btn btn-primary" href="<?= url('/dashboard?scope=all') ?>">Try again</a></div>
<?php else: ?>
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($cards as [$ic, $label, $value, $meta]): ?>
      <div class="panel-card flex min-h-27 items-center gap-3 px-4.25 py-4.5">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="text-tiny text-muted"><?= e($label) ?></div><div class="mt-1 text-stat font-extrabold tracking-stat"><?= e($value) ?></div><div class="mt-0.75 text-micro font-semibold text-brand"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <section class="panel-card px-5.5 py-5">
    <div class="mb-3"><h2>By workspace</h2><p class="text-tiny text-muted">Only your own projects, tasks and saved time</p></div>
    <div class="w-full overflow-x-auto"><table>
      <thead><tr><th>Workspace</th><th>Your role</th><th>Active projects</th><th>Open tasks</th><th>Overdue</th><th>Saved this month</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $w = $r['ws']; ?>
        <tr>
          <td><div class="flex items-center gap-2"><strong class="text-xs"><?= e($w['name']) ?></strong><span class="rounded-full bg-soft px-2 py-0.5 text-nano font-bold text-brand"><?= $w['type'] === 'company' ? 'Company' : 'Personal' ?></span></div></td>
          <td><?= e(Perm::label((string)$w['role'], $w['custom_role_id'] ?? null)) ?></td>
          <td><?= $r['projects'] === null ? $dash : (int)$r['projects'] ?></td>
          <td><?= $r['open'] === null ? $dash : (int)$r['open'] ?></td>
          <td><?= $r['overdue'] === null ? $dash : ($r['overdue'] > 0 ? '<span class="font-semibold text-tag-amber">' . (int)$r['overdue'] . '</span>' : '0') ?></td>
          <td><?= $r['month'] === null ? $dash : e(fmt_hm($r['month'])) ?></td>
          <td class="text-right"><?= Everywhere::openForm((int)$w['id'], '/dashboard', 'Open') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
<?php endif; ?>
</div>
