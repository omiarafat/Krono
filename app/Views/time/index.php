<?php
/* Time Tracking: read-only history of saved sessions. Vars: $ws $tz $f $list (or null) $opt (projects, tasks) $people $any $failed $appInstalled $downloadUrl */
$filtered = TimeLog::filtered($f);
$multi = count($people) > 1;
$openApp = '<form method="post" action="' . url('/open-app') . '" class="m-0">' . csrf_field() . '<button type="submit" class="btn">Open Krono Desktop</button></form>';
$dl = '<a class="btn btn-primary" href="' . e($downloadUrl) . '">' . icon('download', 16) . ' Download for Windows</a>';
if ($any || $failed) { $heading = 'Time Tracking'; $sub = 'Review saved work sessions; run the timer in Krono Desktop'; }
else { $heading = 'Track time with Krono Desktop'; $sub = 'Install the lightweight Windows app to control attendance and task timers. Your saved history will appear here.'; $crumb = 'Track time with Krono Desktop'; }
$actions = $dl;
include ROOT . '/app/Views/partials/page-heading.php';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$link = fn(array $o = []) => url(TimeLog::path($f, $o));
$tw = fn(int $n, string $w) => $n . ' ' . $w . ($n === 1 ? '' : 's');
?>
<div class="flex flex-col gap-4.5 pb-6.5">
<?php if ($failed): ?>
  <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert">
    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
    <h2 class="text-base">Connection interrupted</h2>
    <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Your current form input has been kept.</p>
    <a class="btn btn-primary" href="<?= e($link()) ?>">Try again</a>
  </div>
<?php elseif (!$any): include ROOT . '/app/Views/partials/desktop-empty.php'; ?>
<?php else: ?>
  <?php $calloutTitle = 'Track from Krono Desktop'; $calloutText = 'Start, pause and stop timers in the Windows app. This page shows saved sessions and never controls a live timer.'; include ROOT . '/app/Views/partials/desktop-callout.php'; ?>

  <form method="get" action="<?= url('/time-tracking') ?>" class="flex flex-wrap items-center gap-2.5" id="time-filters" role="search" data-live data-live-target="time-results">
    <div class="krange" data-krange data-today="<?= e($f['default'][1]) ?>">
      <input type="hidden" name="from" value="<?= e($f['from']) ?>" data-default="<?= e($f['default'][0]) ?>" data-live-change>
      <input type="hidden" name="to" value="<?= e($f['to']) ?>" data-default="<?= e($f['default'][1]) ?>" data-live-change>
    </div>
    <select name="project" aria-label="Project" data-kauto class="<?= $select ?>">
      <option value="">All projects</option>
      <?php foreach ($opt['projects'] as $id => $p): ?><option value="<?= (int)$id ?>" data-meta="<?= e($tw($p['logs'], 'log')) ?>"<?= $f['project'] === (int)$id ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="task" aria-label="Task" data-kauto data-depends="project" class="<?= $select ?>">
      <option value="">All tasks</option>
      <?php foreach ($opt['tasks'] as $id => $t): ?><option value="<?= (int)$id ?>" data-project="<?= (int)$t['project'] ?>"<?= $f['task'] === (int)$id ? ' selected' : '' ?>><?= e($t['title']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($multi): ?>
    <select name="member" aria-label="Member" data-kauto class="<?= $select ?>">
      <option value="">All members</option>
      <?php foreach ($people as $id => $name): ?><option value="<?= (int)$id ?>"<?= $f['member'] === (int)$id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
      <?= icon('search', 16) ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search descriptions…" aria-label="Search descriptions" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
    </div>
    <button type="submit" class="btn h-10">Apply filters</button>
  </form>

  <div id="time-results" data-live-area>
  <section class="panel-card p-5.5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><h2>Saved time logs</h2><p class="mt-1 mb-0 text-tiny text-muted">Only submitted sessions appear here</p></div>
      <div class="flex items-center gap-2.5">
        <?php if ($list['total']): ?><span class="text-tiny text-muted">Total <b class="text-ink"><?= e(fmt_hm($list['seconds'])) ?></b></span><?php endif; ?>
        <span class="rounded-full bg-soft px-2.5 py-1 text-nano font-bold text-brand"><?= $list['total'] === 1 ? '1 entry' : $list['total'] . ' entries' ?></span>
      </div>
    </div>

    <?php if (!$list['items']): ?>
      <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
        <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 26) ?></div>
        <h3 class="m-0 text-sm">No results found</h3>
        <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $filtered ? 'Try changing the search terms or clearing one or more filters.' : 'No saved sessions in the last 30 days. Choose a wider date range to see older work.' ?></p>
        <?php if ($filtered): ?><a class="btn btn-primary" href="<?= url('/time-tracking') ?>" data-live>Clear filters</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="relative mt-4 overflow-x-auto">
        <table class="min-w-180">
          <thead><tr><th>Date</th><?php if ($multi): ?><th>Member</th><?php endif; ?><th>Project</th><th>Task</th><th>Work description</th><th>Duration</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($list['items'] as $r): ?>
            <tr>
              <td class="whitespace-nowrap"><?= e(TimeLog::dateLabel($r['started_at'], $tz)) ?></td>
              <?php if ($multi): ?><td><?= Ui::avatar($r['member'], 'h-6.5 w-6.5') ?></td><?php endif; ?>
              <td><a class="text-ink no-underline hover:text-brand" href="<?= url('/project?id=' . (int)$r['project_id']) ?>"><?= e($r['project_name']) ?></a></td>
              <td><a class="block max-w-52 truncate font-bold text-ink no-underline hover:text-brand" href="<?= url('/task?id=' . (int)$r['task_id']) ?>" title="<?= e($r['task_title']) ?>"><?= e($r['task_title']) ?></a></td>
              <td><span class="block max-w-80 truncate" title="<?= e($r['work_description']) ?>"><?= e($r['work_description']) ?></span></td>
              <td class="whitespace-nowrap font-semibold tabular-nums"><?= e(fmt_hms((int)$r['duration_seconds'])) ?></td>
              <td><span class="inline-flex rounded-full bg-soft px-2.5 py-1 text-nano font-bold text-brand">Saved</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($list['pages'] > 1): $pg = $list['page']; ?>
        <nav class="mt-3.5 flex items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
          <span>Page <?= $pg ?> of <?= $list['pages'] ?> · <?= $list['total'] === 1 ? '1 entry' : $list['total'] . ' entries' ?></span>
          <span class="flex gap-2">
            <?php if ($pg > 1): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg - 1])) ?>">← Previous</a><?php endif; ?>
            <?php if ($pg < $list['pages']): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg + 1])) ?>">Next →</a><?php endif; ?>
          </span>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
  </div>
<?php endif; ?>
</div>
