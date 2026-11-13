<?php
/* Project Details. Vars: $p (project) $s (summary) $tasks $feed $tab $tz $canManage $canAdd $failed $form $taskForm $activityAll $back */
$id = (int)$p['id']; $archived = (bool)$p['archived_at'];
$self = fn(array $q = [], string $hash = '') => url('/project?' . http_build_query(['id' => $id] + $q)) . $hash;
$edit = ['id' => $id, 'name' => $p['name'], 'description' => (string)$p['description'], 'start_date' => (string)$p['start_date'], 'due_date' => (string)$p['due_date'], 'status' => $p['status'], 'priority' => $p['priority']];
$editBtn = 'data-modal-open="project-modal" data-fill="' . e(json_encode($edit)) . '"';
$archiveBtn = 'data-project-archive="' . $id . '" data-name="' . e($p['name']) . '" data-tasks="' . (int)($s['total'] ?? 0) . '" data-logs="' . (int)($s['logs'] ?? 0) . '"';
$muted = 'text-tiny text-muted'; $label = 'text-micro text-muted';
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <!-- heading -->
  <div class="flex items-start justify-between gap-6 pt-6.5 max-sm:flex-col max-sm:gap-4">
    <div class="min-w-0">
      <div class="mb-1.5 text-nano font-extrabold tracking-eyebrow text-brand">KRONO WORKSPACE / PROJECTS</div>
      <div class="flex flex-wrap items-center gap-3"><h1 class="m-0 min-w-0 break-words"><?= e($p['name']) ?></h1><?= $archived ? Ui::tag('project', 'archived', 'Archived') : Ui::tag('project', $p['status']) ?></div>
      <?php if ((string)$p['description'] !== ''): ?><p class="mt-2 mb-0 max-w-3xl text-ui leading-copy text-muted"><?= e(mb_strimwidth($p['description'], 0, 220, '…')) ?></p><?php endif; ?>
    </div>
    <?php if ($canManage): ?>
    <div class="flex shrink-0 items-center gap-2.5">
      <button type="button" class="btn" <?= $editBtn ?>><?= icon('settings', 16) ?> Edit Project</button>
      <details class="relative" data-menu>
        <summary class="btn list-none px-3.5 [&::-webkit-details-marker]:hidden" aria-label="More project actions"><?= icon('more', 18) ?></summary>
        <div class="absolute top-full right-0 z-20 mt-1 w-48 rounded-xl border border-line bg-surface p-1.5 shadow-modal">
          <button type="button" class="menu-item text-danger" <?= $archiveBtn ?>>Archive project</button>
        </div>
      </details>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($archived): ?><p class="m-0 rounded-xl border border-tag-amber/50 bg-tag-amber/8 px-3.5 py-2.5 text-tiny text-ink"><strong>This project is archived.</strong> It is read-only. Tasks and saved time are kept.</p><?php endif; ?>

  <?php if ($failed): ?>
    <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert">
      <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
      <h2 class="text-base">Connection interrupted</h2>
      <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Please try again in a moment.</p>
      <a class="btn btn-primary" href="<?= e($self()) ?>">Try again</a>
    </div>
  <?php else:
    $members = $s['people']; $n = count($members);
    $stats = [
        ['tasks', 'Total Tasks', (string)$s['total'], $s['done'] . ' completed · ' . $s['remaining'] . ' remaining'],
        ['dashboard', 'Progress', $s['percent'] . '%', $s['done'] . ' of ' . $s['total'] . ' tasks completed'],
        ['time', 'Tracked Time', fmt_hm($s['logged']), 'Total time logged'],
        ['team', 'Team', $n . ($n === 1 ? ' Member' : ' Members'), $s['active'] . ' currently active'],
    ]; ?>
  <!-- summary cards -->
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($stats as [$ic, $lab, $val, $meta]): ?>
      <div class="panel-card flex min-h-24 items-center gap-3 px-4.25 py-4">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="<?= $muted ?>"><?= e($lab) ?></div><div class="mt-0.5 truncate text-stat font-extrabold tracking-stat"><?= e($val) ?></div><div class="mt-0.5 text-micro text-muted"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- dates, leader, priority -->
  <div class="panel-card grid grid-cols-4 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <div class="border-r border-line px-5 py-3.5 max-sm:border-r-0 max-sm:border-b"><div class="<?= $label ?>">Start Date</div><div class="mt-1.5 flex items-center gap-2 text-xs font-bold"><span class="text-muted"><?= icon('calendar', 15) ?></span><?= e(Ui::date($p['start_date'])) ?></div></div>
    <div class="border-r border-line px-5 py-3.5 max-laptop:border-r-0 max-sm:border-b"><div class="<?= $label ?>">Due Date</div><div class="mt-1.5 flex items-center gap-2 text-xs font-bold"><span class="text-muted"><?= icon('calendar', 15) ?></span><?= e(Ui::date($p['due_date'])) ?></div></div>
    <div class="border-r border-line px-5 py-3.5 max-sm:border-r-0 max-sm:border-b"><div class="<?= $label ?>">Project Leader</div><div class="mt-1.5 flex items-center gap-2 text-xs font-bold"><?= $s['leader'] ? Ui::avatar($s['leader'], 'h-6.5 w-6.5') . e($s['leader']) : '—' ?></div></div>
    <div class="px-5 py-3.5"><div class="<?= $label ?>">Priority</div><div class="mt-1.5"><?= Ui::tag('priority', $p['priority']) ?></div></div>
  </div>

  <div class="detail-grid">
    <!-- tasks -->
    <section class="panel-card p-5" id="tasks" data-live-area>
      <div class="flex items-start justify-between gap-3">
        <div><h2>Project Tasks</h2><div class="mt-1 <?= $muted ?>"><?= $s['total'] ?> task<?= $s['total'] === 1 ? '' : 's' ?> in this project</div></div>
        <?php if ($canAdd): ?><button type="button" class="btn btn-primary h-9" data-modal-open="task-modal"><?= icon('plus', 15) ?> Add Task</button><?php endif; ?>
      </div>
      <?php if ($s['total'] === 0): ?>
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-10 text-center">
          <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('tasks', 26) ?></div>
          <h3 class="m-0 text-sm">No tasks yet</h3>
          <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $canAdd ? 'Add the first task to start organizing the work in this project.' : 'Tasks will appear here when they are added to this project.' ?></p>
          <?php if ($canAdd): ?><button type="button" class="btn btn-primary" data-modal-open="task-modal"><?= icon('plus', 15) ?> Add Task</button><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="mt-3.5 mb-2.5 flex flex-wrap gap-2" role="tablist" aria-label="Filter tasks">
          <?php foreach (['all' => 'All'] + Task::STATUS as $k => $l): if ($k === 'on_hold' && $s['counts']['on_hold'] === 0 && $tab !== 'on_hold') continue; ?>
            <a class="filter-pill" data-live href="<?= e($self($k === 'all' ? [] : ['tab' => $k], '#tasks')) ?>"<?= $tab === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?> <span>(<?= $s['counts'][$k] ?>)</span></a>
          <?php endforeach; ?>
        </div>
        <?php if (!$tasks): ?>
          <div class="flex flex-col items-center px-6 py-9 text-center"><h3 class="m-0 text-sm">No tasks in this view</h3><p class="mt-1.5 mb-4 text-tiny text-muted">Try another tab to see the rest of the tasks.</p><a class="btn" href="<?= e($self([], '#tasks')) ?>">Show all tasks</a></div>
        <?php else: ?>
        <div class="relative overflow-x-auto rounded-xl border border-line">
          <table class="min-w-150">
            <thead><tr><th>Task</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Due date</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
              <tr>
                <td><a class="block max-w-56 truncate text-xs font-bold text-ink no-underline hover:text-brand" href="<?= url('/task?id=' . (int)$t['id']) ?>"><?= e($t['title']) ?></a><span class="block text-micro text-muted">Updated <?= e(Ui::ago($t['updated_at'])) ?></span></td>
                <td><?= Ui::tag('task', $t['status']) ?></td>
                <td><?= Ui::tag('priority', $t['priority']) ?></td>
                <td><?php if ($t['assignee']): ?><span class="inline-flex items-center gap-2"><?= Ui::avatar($t['assignee'], 'h-6 w-6') ?><?= e(explode(' ', trim($t['assignee']))[0]) ?></span><?php else: ?><span class="text-muted">Unassigned</span><?php endif; ?></td>
                <td class="whitespace-nowrap"><?= e(Ui::date($t['due_date'])) ?></td>
                <td class="whitespace-nowrap"><?= e(fmt_hm((int)$t['logged'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        <div class="pt-3"><a class="text-tiny font-bold text-brand no-underline hover:underline" href="<?= url('/tasks?project=' . $id) ?>">View all project tasks →</a></div>
      <?php endif; ?>
    </section>

    <aside class="flex flex-col gap-4.5">
      <!-- information -->
      <section class="panel-card p-5">
        <div class="mb-3 flex items-center justify-between"><h2>Project Information</h2>
          <?php if ($canManage): ?><details class="relative" data-menu><summary class="icon-button h-7 w-7 list-none [&::-webkit-details-marker]:hidden" aria-label="Project information actions"><?= icon('more', 16) ?></summary>
            <div class="absolute top-full right-0 z-20 mt-1 w-44 rounded-xl border border-line bg-surface p-1.5 shadow-modal"><button type="button" class="menu-item" <?= $editBtn ?>>Edit project</button></div></details><?php endif; ?>
        </div>
        <?php
        $names = array_values($members); $shown = array_slice($names, 0, 4); $more = $n - count($shown);
        $rows = [
            ['file', 'Description', (string)$p['description'] !== '' ? $p['description'] : 'No description yet.'],
            ['calendar', 'Created', Ui::date(substr($p['created_at'], 0, 10)) . ($s['creator'] ? ' by ' . $s['creator'] : '')],
            ['team', 'Project Members', $names ? implode(', ', $shown) . ($more > 0 ? ' +' . $more . ' more' : '') : 'No members yet.'],
        ]; ?>
        <?php foreach ($rows as $i => [$ic, $lab, $txt]): ?>
          <div class="flex gap-3 py-3 <?= $i ? 'border-t border-line' : 'pt-1' ?>"><span class="mt-0.5 shrink-0 text-muted"><?= icon($ic, 16) ?></span>
            <div class="min-w-0"><strong class="text-xs"><?= e($lab) ?></strong><div class="mt-0.5 text-micro leading-copy text-muted <?= $lab === 'Description' ? 'line-clamp-4' : '' ?>"><?= e($txt) ?></div></div></div>
        <?php endforeach; ?>
        <div class="mt-1 flex items-center justify-between"><span class="<?= $muted ?>">Overall progress</span><strong class="text-tiny"><?= $s['percent'] ?>%</strong></div>
        <div class="mt-2 flex items-center gap-3"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-soft" role="progressbar" aria-label="Overall progress" aria-valuenow="<?= $s['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><i class="block h-full rounded-full bg-brand" style="width:<?= $s['percent'] ?>%"></i></div><span class="<?= $muted ?>"><?= $s['done'] ?>/<?= $s['total'] ?> tasks</span></div>
      </section>

      <!-- activity -->
      <section class="panel-card p-5" id="activity" data-live-area>
        <div class="mb-3 flex items-center justify-between"><h2>Recent Activity</h2>
          <?php if ($activityAll): ?><a class="text-tiny font-bold text-brand no-underline hover:underline" data-live href="<?= e($self([], '#activity')) ?>">Show less</a>
          <?php elseif ($feed['total'] > count($feed['items'])): ?><a class="text-tiny font-bold text-brand no-underline hover:underline" data-live href="<?= e($self(['activity' => 1], '#activity')) ?>">View all</a><?php endif; ?>
        </div>
        <?php if (!$feed['items']): ?><p class="m-0 py-3 text-tiny text-muted">No activity yet. Changes and saved time will appear here.</p>
        <?php else: foreach ($feed['items'] as $a): ?>
          <div class="flex gap-3 py-2.5"><i class="mt-1.25 h-2 w-2 shrink-0 rounded-full bg-brand"></i>
            <div class="min-w-0 text-micro leading-copy"><strong><?= e($a['name']) ?></strong> <?= e($a['text']) ?><span class="block text-muted"><?= e(Ui::when($a['utc'], $tz)) ?></span></div></div>
        <?php endforeach; endif; ?>
        <?php if ($activityAll && $feed['pages'] > 1): ?>
          <div class="mt-2 flex items-center justify-between border-t border-line pt-3 text-tiny text-muted"><span>Page <?= $feed['page'] ?> of <?= $feed['pages'] ?></span><span class="flex gap-2">
            <?php if ($feed['page'] > 1): ?><a class="btn h-8" data-live href="<?= e($self(['activity' => 1, 'apage' => $feed['page'] - 1], '#activity')) ?>">← Newer</a><?php endif; ?>
            <?php if ($feed['page'] < $feed['pages']): ?><a class="btn h-8" data-live href="<?= e($self(['activity' => 1, 'apage' => $feed['page'] + 1], '#activity')) ?>">Older →</a><?php endif; ?></span></div>
        <?php endif; ?>
      </section>
    </aside>
  </div>
  <?php endif; ?>
</div>

<?php if ($canManage) include __DIR__ . '/_modals.php'; ?>
<?php if ($canAdd && !$failed) { $taskProjects = [(int)$p['id'] => ['name' => $p['name'], 'tasks' => (int)$s['total'], 'people' => $s['people']]]; $taskFixed = (int)$p['id']; include ROOT . '/app/Views/tasks/_modal.php'; } ?>
<?php if ($canManage || $canAdd) include __DIR__ . '/_discard.php'; ?>
<?php include ROOT . '/app/Views/partials/toast.php'; ?>
<script src="<?= asset('js/krono-modals.js') ?>" defer></script>
