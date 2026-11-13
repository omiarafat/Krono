<?php
/* Task Details. Vars: $t (task) $tz $tab $canEdit $failed $info (people, counts, role, logs, feed) $taskForm $detailForm $back */
$id = (int)$t['id']; $pid = (int)$t['project_id']; $done = $t['status'] === 'completed'; $locked = Task::timerActive($id);
$self = fn(array $q = []) => url('/task?' . http_build_query(['id' => $id] + $q));
$code = 'TK-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
$muted = 'text-tiny text-muted'; $label = 'text-micro text-muted';
$est = $t['estimate_minutes'] ? (int)$t['estimate_minutes'] : 0;
$logged = $info['logs']['seconds'] ?? 0;
$linkBtn = 'cursor-pointer border-0 bg-transparent p-0 text-tiny font-bold text-brand hover:underline';
$editFill = ['id' => $id, 'project_id' => $pid, 'project_name' => $t['project_name'], 'title' => $t['title'], 'assignee_id' => (int)$t['assignee_id'] ?: '', 'priority' => $t['priority'], 'due_date' => (string)$t['due_date'], 'status' => $t['status']];
$notes = array_values(array_filter(preg_split('/\R/u', (string)($t['notes'] ?? '')), fn($l) => trim($l) !== ''));
[$dueText, $late] = Ui::dueIn($t['due_date'], $tz);
$tabs = ['overview' => 'Overview', 'logs' => 'Time Logs', 'activity' => 'Activity'];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <!-- heading -->
  <div class="flex items-start justify-between gap-6 pt-6.5 max-sm:flex-col max-sm:gap-4">
    <div class="min-w-0">
      <div class="mb-1.5 text-nano font-extrabold tracking-eyebrow text-brand">KRONO WORKSPACE / TASKS</div>
      <div class="flex flex-wrap items-center gap-3"><h1 class="m-0 min-w-0 break-words"><?= e($t['title']) ?></h1><?= Ui::tag('task', $t['status']) ?><?= Ui::tag('priority', $t['priority']) ?></div>
      <p class="mt-2 mb-0 text-ui text-muted">Project: <a class="font-bold text-brand no-underline hover:underline" href="<?= url('/project?id=' . $pid) ?>"><?= e($t['project_name']) ?></a> <span class="mx-1.5">·</span> Task ID <?= e($code) ?></p>
    </div>
    <?php if ($canEdit): ?>
    <div class="flex shrink-0 items-center gap-2.5">
      <button type="button" class="btn" data-modal-open="task-modal" data-fill="<?= e(json_encode($editFill)) ?>"><?= icon('settings', 16) ?> Edit Task</button>
      <details class="relative" data-menu>
        <summary class="btn list-none px-3.5 [&::-webkit-details-marker]:hidden" aria-label="More task actions"><?= icon('more', 18) ?></summary>
        <div class="absolute top-full right-0 z-20 mt-1 w-48 rounded-xl border border-line bg-surface p-1.5 shadow-modal">
          <button type="button" class="menu-item text-danger" data-task-delete="<?= $id ?>" data-name="<?= e($t['title']) ?>" data-logs="<?= (int)($info['logs']['total'] ?? 0) ?>" data-time="<?= e(fmt_hm($logged)) ?>" data-href="<?= e($self(['tab' => 'logs'])) ?>">Delete task</button>
        </div>
      </details>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($t['project_archived']): ?><p class="m-0 rounded-xl border border-tag-amber/50 bg-tag-amber/8 px-3.5 py-2.5 text-tiny text-ink"><strong>This task's project is archived.</strong> The task is read-only. Saved time is kept.</p><?php endif; ?>

  <!-- tabs -->
  <nav class="flex gap-6 border-b border-line" aria-label="Task sections">
    <?php foreach ($tabs as $k => $l): ?>
      <a class="-mb-px border-b-2 pt-1 pb-2.5 text-xs font-bold no-underline <?= $tab === $k ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink' ?>" href="<?= e($self($k === 'overview' ? [] : ['tab' => $k])) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($failed): ?>
    <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert">
      <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
      <h2 class="text-base">Connection interrupted</h2>
      <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Please try again in a moment.</p>
      <a class="btn btn-primary" href="<?= e($self($tab === 'overview' ? [] : ['tab' => $tab])) ?>">Try again</a>
    </div>
  <?php else:
    $over = $est && $logged > $est * 60;
    $cards = [
        ['user', 'Assignee', $t['assignee'] ?: 'Unassigned', $t['assignee'] ? ($info['role'] !== '' ? ucfirst($info['role']) : 'Project member') : 'No one is responsible yet', false],
        ['calendar', 'Due Date', Ui::date($t['due_date']), $done ? 'Completed' : $dueText, !$done && $late],
        ['calendar', 'Created', Ui::day($t['created_at'], $tz), 'By ' . ($t['creator'] ?: 'Someone'), false],
        ['time', 'Estimated Time', $est ? Ui::minutes($est) : 'Not set', fmt_hm($logged) . ' logged' . ($over ? ' · over estimate' : ''), $over],
    ]; ?>
  <!-- summary cards -->
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($cards as [$ic, $lab, $val, $meta, $warn]): ?>
      <div class="panel-card flex min-h-21 items-center gap-3 px-4.25 py-3.5">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="<?= $label ?>"><?= e($lab) ?></div><div class="mt-0.5 truncate text-xs font-extrabold"><?= e($val) ?></div><div class="mt-0.5 text-micro <?= $warn ? 'font-bold text-danger' : 'text-muted' ?>"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="detail-grid">
    <div class="flex min-w-0 flex-col gap-4.5">
    <?php if ($tab === 'overview'): ?>
      <!-- description + notes -->
      <section class="panel-card p-5.5" id="description">
        <div class="flex items-start justify-between gap-3"><h2>Description</h2>
          <?php if ($canEdit): ?><button type="button" class="<?= $linkBtn ?>" data-modal-open="details-modal">Edit description</button><?php endif; ?></div>
        <?php if ((string)$t['description'] !== ''): ?><p class="mt-3 mb-0 text-xs leading-copy text-muted whitespace-pre-line"><?= e($t['description']) ?></p>
        <?php else: ?><p class="mt-3 mb-0 text-xs text-muted">No description yet.<?= $canEdit ? ' Use “Edit description” to explain the work.' : '' ?></p><?php endif; ?>
        <?php if ($notes): ?>
          <h3 class="mt-5 mb-2 text-xs">Notes</h3>
          <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-tiny text-muted marker:text-brand"><?php foreach ($notes as $n): ?><li><?= e($n) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($tab === 'overview' || $tab === 'logs'): $lg = $info['logs']; ?>
      <!-- time logs -->
      <section class="panel-card p-5.5" id="logs">
        <div class="flex items-start justify-between gap-3">
          <div><h2>Time Logs</h2><p class="mt-1 mb-0 <?= $muted ?>">Saved time entries linked to this task</p></div>
          <?php if ($tab === 'overview' && $lg['total'] > count($lg['items'])): ?><a class="text-tiny font-bold text-brand no-underline hover:underline" href="<?= e($self(['tab' => 'logs'])) ?>">View all logs →</a><?php endif; ?>
        </div>
        <?php if (!$lg['items']): ?>
          <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-10 text-center">
            <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('time', 26) ?></div>
            <h3 class="m-0 text-sm">No time logged yet</h3>
            <p class="mt-1.5 mb-0 max-w-sm text-tiny leading-copy text-muted">Time saved from the Krono desktop app will appear here.</p>
          </div>
        <?php else: ?>
          <div class="relative mt-4 overflow-x-auto">
            <table class="min-w-120">
              <thead><tr><th>Date</th><th>Duration</th><th>Description</th><th>Added by</th></tr></thead>
              <tbody>
              <?php foreach ($lg['items'] as $l): ?>
                <tr><td class="whitespace-nowrap"><?= e(Ui::day($l['started_at'], $tz)) ?></td><td class="whitespace-nowrap font-bold"><?= e(fmt_hm((int)$l['duration_seconds'])) ?></td>
                  <td class="max-w-64"><?= e($l['work_description']) ?></td><td><span class="inline-flex items-center gap-2"><?= Ui::avatar($l['name'], 'h-6 w-6') ?><?= e($l['name']) ?></span></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($tab === 'logs' && $lg['pages'] > 1): ?>
            <div class="mt-3 flex items-center justify-between text-tiny text-muted"><span>Page <?= $lg['page'] ?> of <?= $lg['pages'] ?></span><span class="flex gap-2">
              <?php if ($lg['page'] > 1): ?><a class="btn h-8" href="<?= e($self(['tab' => 'logs', 'lpage' => $lg['page'] - 1])) ?>">← Newer</a><?php endif; ?>
              <?php if ($lg['page'] < $lg['pages']): ?><a class="btn h-8" href="<?= e($self(['tab' => 'logs', 'lpage' => $lg['page'] + 1])) ?>">Older →</a><?php endif; ?></span></div>
          <?php endif; ?>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3 text-tiny">
            <span class="text-muted">Total logged <strong class="text-ink"><?= e(fmt_hm($lg['seconds'])) ?></strong></span>
            <?php if ($est): $left = $est * 60 - $lg['seconds']; ?><span class="<?= $left < 0 ? 'font-bold text-danger' : 'text-muted' ?>"><?= $left < 0 ? 'Over estimate by ' . e(fmt_hm(-$left)) : 'Remaining <strong class="text-ink">' . e(fmt_hm($left)) . '</strong> of ' . e(Ui::minutes($est)) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($tab === 'activity'): $fd = $info['feed']; ?>
      <!-- task activity -->
      <section class="panel-card p-5.5" id="activity">
        <h2>Task Activity</h2><p class="mt-1 mb-3 <?= $muted ?>">Changes to this task and the time saved on it</p>
        <?php foreach ($fd['items'] as $a): ?>
          <div class="flex gap-3 py-2.5"><i class="mt-1.25 h-2 w-2 shrink-0 rounded-full bg-brand"></i>
            <div class="min-w-0 text-tiny leading-copy"><strong><?= e($a['name']) ?></strong> <?= e($a['text']) ?><?php if ($a['note'] !== ''): ?><span class="block text-muted">“<?= e($a['note']) ?>”</span><?php endif; ?><span class="block text-micro text-muted"><?= e(Ui::when($a['utc'], $tz)) ?></span></div></div>
        <?php endforeach; ?>
        <?php if ($fd['pages'] > 1): ?>
          <div class="mt-2 flex items-center justify-between border-t border-line pt-3 text-tiny text-muted"><span>Page <?= $fd['page'] ?> of <?= $fd['pages'] ?></span><span class="flex gap-2">
            <?php if ($fd['page'] > 1): ?><a class="btn h-8" href="<?= e($self(['tab' => 'activity', 'apage' => $fd['page'] - 1])) ?>">← Newer</a><?php endif; ?>
            <?php if ($fd['page'] < $fd['pages']): ?><a class="btn h-8" href="<?= e($self(['tab' => 'activity', 'apage' => $fd['page'] + 1])) ?>">Older →</a><?php endif; ?></span></div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    </div>

    <aside class="flex flex-col gap-4.5">
      <!-- status -->
      <section class="panel-card p-5.5">
        <h2 class="mb-3.5">Task Status</h2>
        <?php if ($canEdit): ?>
        <form method="post" action="<?= url('/tasks/save') ?>" id="status-form" class="flex flex-col gap-3.5">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="project_id" value="<?= $pid ?>">
          <input type="hidden" name="title" value="<?= e($t['title']) ?>"><input type="hidden" name="assignee_id" value="<?= (int)$t['assignee_id'] ?: '' ?>"><input type="hidden" name="due_date" value="<?= e((string)$t['due_date']) ?>">
          <div><label class="field-label" for="st-status">Current status</label>
            <?php if ($locked): ?><input type="hidden" name="status" value="<?= e($t['status']) ?>"><?php endif; ?>
            <select class="field-input" id="st-status" <?= $locked ? 'disabled' : 'name="status"' ?>><?php foreach (Task::STATUS as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('task', $k) ?>"<?= $t['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <p class="mt-2 mb-0 flex items-center gap-2 rounded-lg <?= $locked ? 'bg-soft px-3 py-2 text-brand' : 'text-muted' ?> text-micro leading-copy"><?php if ($locked): ?><?= icon('shield', 14) ?><?php endif; ?><?= $locked ? "Status can't be changed while this task's timer is active." : 'Changing the status never changes timers or saved time.' ?></p></div>
          <div><label class="field-label" for="st-priority">Priority</label>
            <select class="field-input" id="st-priority" name="priority"><?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('priority', $k) ?>"<?= $t['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
          <noscript><button class="btn btn-primary" type="submit">Save</button></noscript>
        </form>
        <?php else: ?>
          <div class="flex flex-col gap-3.5">
            <div><div class="field-label">Current status</div><?= Ui::tag('task', $t['status']) ?></div>
            <div><div class="field-label">Priority</div><?= Ui::tag('priority', $t['priority']) ?></div>
            <p class="m-0 text-micro leading-copy text-muted">You can view this task. Owners, admins, managers and the project leader can change it.</p>
          </div>
        <?php endif; ?>
      </section>

      <!-- related project -->
      <?php $pc = $info['counts']; $pct = $pc['all'] > 0 ? (int)round(100 * $pc['completed'] / $pc['all']) : 0; ?>
      <section class="panel-card p-5.5">
        <h2 class="mb-3.5">Related Project</h2>
        <div class="flex items-center gap-3 rounded-xl border border-line p-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('projects', 18) ?></div>
          <div class="min-w-0"><a class="block truncate text-xs font-bold text-ink no-underline hover:text-brand" href="<?= url('/project?id=' . $pid) ?>"><?= e($t['project_name']) ?></a><div class="mt-1"><?= Ui::tag('project', $t['project_status']) ?></div></div>
        </div>
        <div class="mt-3.5 flex items-center gap-3"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-soft" role="progressbar" aria-label="Project progress" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i class="block h-full rounded-full bg-brand" style="width:<?= $pct ?>%"></i></div><span class="<?= $muted ?>"><?= $pc['completed'] ?>/<?= $pc['all'] ?> tasks</span></div>
        <div class="mt-1.5 <?= $muted ?>"><?= $pct ?>% complete</div>
        <a class="mt-3 inline-block text-tiny font-bold text-brand no-underline hover:underline" href="<?= url('/project?id=' . $pid) ?>">View project →</a>
      </section>
    </aside>
  </div>
  <?php endif; ?>
</div>

<?php
if ($canEdit && !$failed) {
    $taskProjects = [$pid => ['name' => $t['project_name'], 'tasks' => (int)$info['counts']['all'], 'people' => $info['people']]]; $taskFixed = $pid;
    include __DIR__ . '/_modal.php'; include __DIR__ . '/_details-modal.php'; include ROOT . '/app/Views/projects/_discard.php';
    $back = '/project?id=' . $pid; include __DIR__ . '/_delete.php';   // after deleting, return to the project
}
include ROOT . '/app/Views/partials/toast.php';
?>
<?php if ($canEdit && !$failed): ?>
<script>
/* status and priority save as soon as they change */
(function () { var f = document.getElementById('status-form'); if (!f) return; f.addEventListener('change', function () { f.submit(); }); })();
</script>
<?php endif; ?>
<script src="<?= asset('js/krono-modals.js') ?>" defer></script>
