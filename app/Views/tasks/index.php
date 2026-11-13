<?php
/* Tasks page. Vars: $ws $tz $f (filters) $list (or null) $projects (id => name) $addable $failed $taskForm $back */
$filtered = $f['q'] !== '' || $f['project'] || $f['status'] !== 'all' || $f['priority'] !== 'all';
$canAdd = (bool)$addable;
$heading = 'Tasks'; $sub = 'Plan, assign and review tasks across projects';
$actions = $canAdd ? '<button type="button" class="btn btn-primary" data-modal-open="task-modal">' . icon('plus', 16) . ' Add task</button>' : '';
include ROOT . '/app/Views/partials/page-heading.php';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$link = fn(array $o = []) => url(Task::path($f, $o));
$tw = fn(int $n, string $w) => $n . ' ' . $w . ($n === 1 ? '' : 's');
$any = false; foreach ($list['items'] ?? [] as $r) if ($r['can_edit']) $any = true;
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <?php if (!$canAdd): ?>
    <p class="m-0 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-tiny text-muted">You can see tasks of the projects you belong to. Owners, admins, managers and project leaders add and edit tasks.</p>
  <?php endif; ?>

  <form method="get" action="<?= url('/tasks') ?>" class="flex flex-wrap items-center gap-2.5" id="task-filters" role="search" data-live data-live-target="task-results">
    <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
      <?= icon('search', 16) ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search tasks…" aria-label="Search tasks" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
    </div>
    <select name="project" aria-label="Project" data-kauto class="<?= $select ?>">
      <option value="">All projects</option>
      <?php foreach ($projects as $id => $name): ?><option value="<?= (int)$id ?>"<?= $f['project'] === (int)$id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <select name="status" aria-label="Status" data-kauto class="<?= $select ?>">
      <option value="all">All status</option>
      <?php foreach (Task::STATUS as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('task', $k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <select name="priority" aria-label="Priority" data-kauto class="<?= $select ?>">
      <option value="all">All priority</option>
      <?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('priority', $k) ?>"<?= $f['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">Apply</button></noscript>
  </form>

  <div id="task-results" data-live-area>
  <?php if ($failed): ?>
    <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert">
      <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
      <h2 class="text-base">Connection interrupted</h2>
      <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Your current form input has been kept.</p>
      <a class="btn btn-primary" href="<?= e($link()) ?>">Try again</a>
    </div>
  <?php else: ?>
  <section class="panel-card p-5.5">
    <div class="flex items-start justify-between gap-3">
      <div><h2>All tasks</h2><p class="mt-1 mb-0 text-tiny text-muted"><?= $tw($list['total'], 'task') ?> across <?= $tw($list['projects'], 'project') ?></p></div>
      <div class="inline-flex rounded-control border border-line bg-raised p-0.75 text-tiny font-bold" role="group" aria-label="View">
        <span class="rounded-lg bg-surface px-3 py-1.5 text-brand shadow-card" aria-current="true">List</span>
        <span class="cursor-not-allowed px-3 py-1.5 text-muted" aria-disabled="true" title="Board view is coming later">Board</span>
      </div>
    </div>

    <?php if (!$list['items']): ?>
      <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
        <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon($filtered ? 'search' : 'tasks', 26) ?></div>
        <?php if ($filtered): ?>
          <h3 class="m-0 text-sm">No results found</h3>
          <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted">Try changing the search terms or clearing one or more filters.</p>
          <a class="btn btn-primary" href="<?= url('/tasks') ?>" data-live>Clear filters</a>
        <?php else: ?>
          <h3 class="m-0 text-sm">No tasks yet</h3>
          <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $canAdd ? 'Add your first task to start planning the work.' : 'Tasks will appear here when they are added to your projects.' ?></p>
          <?php if ($canAdd): ?><button type="button" class="btn btn-primary" data-modal-open="task-modal"><?= icon('plus', 15) ?> Add task</button><?php endif; ?>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="relative mt-4 overflow-x-auto">
        <table class="min-w-170">
          <thead><tr><th>Task</th><th>Project</th><th>Status</th><th>Priority</th><th>Due date</th><th>Owner</th><?php if ($any): ?><th class="w-20"><span class="sr-only">Actions</span></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($list['items'] as $t):
            $done = $t['status'] === 'completed'; [$due, $late] = Ui::due($t['due_date'], $tz, $done);
            $edit = ['id' => (int)$t['id'], 'project_id' => (int)$t['project_id'], 'project_name' => $t['project_name'], 'title' => $t['title'], 'assignee_id' => (int)$t['assignee_id'] ?: '', 'priority' => $t['priority'], 'due_date' => (string)$t['due_date'], 'status' => $t['status']];
          ?>
            <tr>
              <td><div class="flex items-center gap-2.5">
                <?php if ($t['can_edit']): ?>
                  <form method="post" action="<?= url('/tasks/toggle') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                    <button type="submit" class="flex h-4.5 w-4.5 cursor-pointer items-center justify-center rounded-md border p-0 <?= $done ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-transparent hover:border-brand hover:text-brand' ?> focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand" aria-label="<?= $done ? 'Mark as not completed: ' : 'Mark as completed: ' ?><?= e($t['title']) ?>"><?= icon('check', 12) ?></button></form>
                <?php else: ?>
                  <span class="flex h-4.5 w-4.5 items-center justify-center rounded-md border <?= $done ? 'border-brand bg-brand text-on-brand' : 'border-line bg-surface text-transparent' ?>" role="img" aria-label="<?= $done ? 'Completed' : 'Not completed' ?>"><?= icon('check', 12) ?></span>
                <?php endif; ?>
                <a class="max-w-64 truncate text-xs font-bold text-ink no-underline hover:text-brand <?= $done ? 'line-through decoration-muted' : '' ?>" href="<?= url('/task?id=' . (int)$t['id']) ?>"><?= e($t['title']) ?></a>
              </div></td>
              <td><a class="text-ink no-underline hover:text-brand" href="<?= url('/project?id=' . (int)$t['project_id']) ?>"><?= e($t['project_name']) ?></a></td>
              <td><?= Ui::tag('task', $t['status']) ?></td>
              <td><?= Ui::tag('priority', $t['priority']) ?></td>
              <td class="whitespace-nowrap<?= $late ? ' font-bold text-danger' : '' ?>"><?php if ($late): ?><span class="inline-flex items-center gap-1"><?= icon('alert', 13) ?><span class="sr-only">Overdue: </span><?= e($due) ?></span><?php else: ?><?= e($due) ?><?php endif; ?></td>
              <td><?= $t['assignee'] ? Ui::avatar($t['assignee'], 'h-6.5 w-6.5') : '<span class="text-muted">—</span>' ?></td>
              <?php if ($any): ?><td class="whitespace-nowrap text-right">
                <?php if ($t['can_edit']): ?>
                  <button type="button" class="icon-button h-7.5 w-7.5 border-transparent bg-transparent" data-modal-open="task-modal" data-fill="<?= e(json_encode($edit)) ?>" aria-label="Edit <?= e($t['title']) ?>" title="Edit task"><?= icon('edit', 15) ?></button>
                  <button type="button" class="icon-button h-7.5 w-7.5 border-transparent bg-transparent hover:text-danger" data-task-delete="<?= (int)$t['id'] ?>" data-name="<?= e($t['title']) ?>" data-logs="<?= (int)$t['logs'] ?>" data-time="<?= e(fmt_hm((int)$t['logged'])) ?>" data-href="<?= url('/task?id=' . (int)$t['id']) ?>" aria-label="Delete <?= e($t['title']) ?>" title="Delete task"><?= icon('trash', 15) ?></button>
                <?php endif; ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($list['pages'] > 1): $pg = $list['page']; ?>
        <nav class="mt-3.5 flex items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
          <span>Page <?= $pg ?> of <?= $list['pages'] ?> · <?= $tw($list['total'], 'task') ?></span>
          <span class="flex gap-2">
            <?php if ($pg > 1): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg - 1])) ?>">← Previous</a><?php endif; ?>
            <?php if ($pg < $list['pages']): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg + 1])) ?>">Next →</a><?php endif; ?>
          </span>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
</div>

<?php
if ($canAdd) { $taskProjects = $addable; $taskFixed = 0; $taskDefaultProject = isset($addable[$f['project']]) ? $f['project'] : ''; include __DIR__ . '/_modal.php'; include ROOT . '/app/Views/projects/_discard.php'; }
if ($canAdd) include __DIR__ . '/_delete.php';
include ROOT . '/app/Views/partials/toast.php';
?>
<script src="<?= asset('js/krono-modals.js') ?>" defer></script>
