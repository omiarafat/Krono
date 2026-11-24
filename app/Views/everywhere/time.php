<?php
/* All workspaces: my saved time. Vars: $logs (Everywhere::logs) $failed */
$heading = 'Time Tracking'; $sub = 'Your saved sessions in your personal and linked workspaces';
include ROOT . '/app/Views/partials/page-heading.php';
include ROOT . '/app/Views/partials/scope-toggle.php';
$rows = $logs['rows'] ?? []; $week = $logs['week'] ?? []; $names = $logs['names'] ?? [];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
<?php if ($failed): ?>
  <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert"><div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div><h2 class="text-base">Connection interrupted</h2><p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load your time logs. Please try again.</p><a class="btn btn-primary" href="<?= url('/time-tracking') ?>">Try again</a></div>
<?php else: ?>
  <?php if ($week): ?>
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($week as $wid => $sec): ?>
      <div class="panel-card flex min-h-27 items-center gap-3 px-4.25 py-4.5">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon('time') ?></div>
        <div class="min-w-0"><div class="truncate text-tiny text-muted"><?= e($names[$wid]['name']) ?></div><div class="mt-1 text-stat font-extrabold tracking-stat"><?= e(fmt_hm($sec)) ?></div><div class="mt-0.75 text-micro font-semibold text-brand">This week</div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <section class="panel-card px-5.5 py-5">
    <div class="mb-3"><h2>Latest saved time</h2><p class="text-tiny text-muted">Your most recent <?= count($rows) ?> sessions across workspaces</p></div>
    <?php if (!$rows): ?><p class="m-0 text-tiny text-muted">No saved time logs yet. Start the timer in Krono Desktop, then Stop and Save.</p><?php else: ?>
    <div class="w-full overflow-x-auto"><table>
      <thead><tr><th>Date</th><th>Workspace</th><th>Project</th><th>Task</th><th>Work description</th><th>Duration</th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?>
        <tr><td><?= e($r['day']) ?></td><td><?= e($r['ws']) ?></td><td><?= e($r['project']) ?></td><td><?= e($r['task']) ?></td><td><?= e($r['work_description']) ?></td><td><?= e(fmt_hms((int)$r['duration_seconds'])) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</div>
