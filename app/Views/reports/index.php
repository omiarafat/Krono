<?php
/* Reports: live filters, summary cards, charts, saved entries. Vars: $ws $tz $f $r (result or null) $opt $people $any $failed $appInstalled $downloadUrl */
$multi = count($people) > 1; $filtered = Report::filtered($f); $pre = Report::presets($tz);
$heading = 'Reports'; $sub = 'Review saved time, filter live results and generate a report';
$openApp = '<form method="post" action="' . url('/open-app') . '" class="m-0">' . csrf_field() . '<button type="submit" class="btn">Open Krono Desktop</button></form>';
$dl = '<a class="btn btn-primary" href="' . e($downloadUrl) . '">' . icon('download', 16) . ' Download for Windows</a>';
include ROOT . '/app/Views/partials/page-heading.php';
$link = fn(array $o = []) => url(Report::path($f, $o));
$dayLink = fn(string $k) => url(Report::path($f, ['from' => $pre[$k][1], 'to' => $pre[$k][2], 'page' => 1]));
$lab = 'mb-1.5 block text-nano font-bold text-muted';
$select = 'h-10 w-full cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$shades = [100, 70, 48, 32, 20];   // theme-aware greens for the donut: brand mixed with the card colour
$shade = fn(int $i) => $i >= 5 ? 'color-mix(in srgb, var(--color-muted) 55%, var(--color-surface))' : 'color-mix(in srgb, var(--color-brand) ' . $shades[$i] . '%, var(--color-surface))';
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
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rf-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><h2 class="m-0 text-base" id="rf-title">Report filters</h2><p class="mt-1 mb-0 text-xs text-muted">Results update automatically as you change filters</p></div>
      <span class="inline-flex items-center gap-1.5 rounded-full bg-soft px-2.5 py-1 text-nano font-bold text-brand"><span class="h-1.5 w-1.5 rounded-full bg-brand"></span> Live filters</span>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3" id="report-presets" data-live-for="report-results">
      <div class="inline-flex rounded-xl border border-line bg-raised p-1" role="group" aria-label="Quick range">
        <?php foreach ($pre as $k => $p): ?><a data-live href="<?= e($dayLink($k)) ?>" class="rounded-lg px-4 py-1.5 text-tiny font-bold no-underline <?= $f['preset'] === $k ? 'bg-brand text-on-brand' : 'text-muted hover:text-ink' ?>"<?= $f['preset'] === $k ? ' aria-current="true"' : '' ?>><?= e($p[0]) ?></a><?php endforeach; ?>
      </div>
      <span class="text-tiny text-muted"><?= e(Report::caption($f, $tz)) ?></span>
    </div>
    <form method="get" action="<?= url('/reports') ?>" id="report-filters" role="search" data-live data-live-target="report-results" data-live-also="report-count report-presets" class="mt-4">
      <div class="grid grid-cols-4 gap-x-4 gap-y-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
        <div class="col-span-2 max-sm:col-span-1"><span class="<?= $lab ?>">Date range</span>
          <div class="krange" data-krange data-today="<?= e($f['default'][1]) ?>">
            <input type="hidden" name="from" value="<?= e($f['from']) ?>" data-default="<?= e($f['default'][0]) ?>" data-live-change>
            <input type="hidden" name="to" value="<?= e($f['to']) ?>" data-default="<?= e($f['default'][1]) ?>" data-live-change>
          </div></div>
        <div><label class="<?= $lab ?>" for="rf-project">Project</label>
          <select id="rf-project" name="project" data-kauto class="<?= $select ?>"><option value="">All projects</option>
            <?php foreach ($opt['projects'] as $id => $p): ?><option value="<?= (int)$id ?>" data-meta="<?= (int)$p['logs'] ?> log<?= $p['logs'] === 1 ? '' : 's' ?>"<?= $f['project'] === (int)$id ? ' selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
        <div><label class="<?= $lab ?>" for="rf-task">Task</label>
          <select id="rf-task" name="task" data-kauto data-depends="project" class="<?= $select ?>"><option value="">All tasks</option>
            <?php foreach ($opt['tasks'] as $id => $t): ?><option value="<?= (int)$id ?>" data-project="<?= (int)$t['project'] ?>"<?= $f['task'] === (int)$id ? ' selected' : '' ?>><?= e($t['title']) ?></option><?php endforeach; ?></select></div>
        <?php if ($multi): ?><div><label class="<?= $lab ?>" for="rf-member">Team member</label>
          <select id="rf-member" name="member" data-kauto class="<?= $select ?>"><option value="">All members</option>
            <?php foreach ($people as $id => $name): ?><option value="<?= (int)$id ?>"<?= $f['member'] === (int)$id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div><label class="<?= $lab ?>" for="rf-q">Work description</label>
          <input class="field-input" type="search" id="rf-q" name="q" value="<?= e($f['q']) ?>" maxlength="100" placeholder="Search descriptions…" autocomplete="off"></div>
        <div><label class="<?= $lab ?>" for="rf-status">Status</label>
          <select id="rf-status" name="status" data-kauto class="<?= $select ?>"><option value="">All statuses</option>
            <?php foreach (Task::STATUS as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('task', $k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="<?= $lab ?>" for="rf-priority">Priority</label>
          <select id="rf-priority" name="priority" data-kauto class="<?= $select ?>"><option value="">All priorities</option>
            <?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('priority', $k) ?>"<?= $f['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="<?= $lab ?>" for="rf-location">Work location</label>
          <select id="rf-location" name="location" data-kauto class="<?= $select ?>"><option value="">All work locations</option>
            <?php foreach (Report::LOCATIONS as $k => $l): ?><option value="<?= $k ?>"<?= $f['location'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
    </form>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3.5" id="report-count" data-live-for="report-results">
      <span class="text-tiny text-muted"><?= $r['total'] === 1 ? '1 entry' : (int)$r['total'] . ' entries' ?> · Automatically filtered</span>
      <span class="flex flex-wrap items-center gap-2">
        <?php if ($filtered): ?><a class="btn h-9" data-live href="<?= url('/reports') ?>"><?= icon('refresh', 15) ?> Reset filters</a><?php endif; ?>
        <?php if (Perm::can($ws, 'reports.export')): ?><a class="btn btn-primary h-9" href="<?= e(url('/reports/pdf' . substr(Report::path($f, ['page' => 1]), strlen('/reports')))) ?>"><?= icon('download', 15) ?> Generate Report</a><?php endif; ?>
      </span>
    </div>
  </section>

  <div id="report-results" data-live-area class="flex flex-col gap-4.5">
    <?php if ($r['truncated']): ?><div class="rounded-card border border-tag-amber/40 bg-tag-amber/10 px-4 py-3 text-tiny text-ink" role="status">This range has more than <?= number_format(Report::MAX_ROWS) ?> entries, so only the newest are counted. Choose a shorter range for exact totals.</div><?php endif; ?>
    <div class="grid grid-cols-4 gap-4 max-laptop:grid-cols-2 max-sm:grid-cols-1">
      <?php foreach ([['time', 'Total work time', fmt_hm($r['seconds']), 'Filtered period'], ['file', 'Time entries', (string)$r['total'], 'Saved logs'], ['tasks', 'Tasks worked', (string)$r['tasks'], 'Across selected projects'],
                      ['attendance', 'Attendance days', (string)$r['attDays'], $r['attDays'] ? 'Net work ' . fmt_hm($r['net']) : 'No sign-ins']] as [$ic, $t, $v, $c]): ?>
        <div class="panel-card flex items-center gap-4 p-5">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon($ic, 20) ?></span>
          <div class="min-w-0"><div class="text-tiny text-muted"><?= e($t) ?></div><div class="text-xl leading-tight font-extrabold tabular-nums"><?= e($v) ?></div><div class="text-nano font-semibold text-brand"><?= e($c) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-2 gap-4.5 max-laptop:grid-cols-1">
      <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rc-day">
        <div class="flex items-center justify-between"><h2 class="m-0 text-base" id="rc-day">Time by day</h2><span class="text-tiny text-muted">Hours</span></div>
        <?php $bars = $r['bars']['bars']; $max = max(1, $r['bars']['max']); $dense = count($bars) > 12; ?>
        <div class="mt-5 flex h-52 items-end gap-<?= $dense ? '1' : '3' ?> border-b border-line" role="img" aria-label="Hours worked per day">
          <?php foreach ($bars as $b): $pct = $b['seconds'] ? max(4, (int)round($b['seconds'] / $max * 100)) : 0; ?>
            <div class="group relative flex h-full min-w-0 flex-1 flex-col items-center justify-end" title="<?= e($b['title'] . ' · ' . fmt_hm($b['seconds'])) ?>">
              <?php if ($b['seconds'] && !$dense): ?><span class="mb-1 text-nano font-bold text-muted tabular-nums"><?= e(fmt_hm($b['seconds'])) ?></span><?php endif; ?>
              <div class="w-full max-w-10 rounded-t-md <?= $b['seconds'] ? 'bg-brand' : 'bg-line' ?>" style="height: <?= $b['seconds'] ? $pct . '%' : '2px' ?>"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-1.5 flex gap-<?= $dense ? '1' : '3' ?>"><?php foreach ($bars as $i => $b): ?><span class="min-w-0 flex-1 truncate text-center text-nano text-muted"><?= $dense && $i % 2 ? '' : e($b['label']) ?></span><?php endforeach; ?></div>
      </section>

      <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rc-proj">
        <h2 class="m-0 text-base" id="rc-proj">Time by project</h2>
        <?php if (!$r['projects']): ?><p class="mt-8 mb-0 text-center text-tiny text-muted">No tracked time for these filters.</p>
        <?php else:
          $tot = max(1, $r['seconds']); $segs = array_slice($r['projects'], 0, 5); $rest = array_sum(array_column(array_slice($r['projects'], 5), 1)); if ($rest) $segs[] = ['Other projects', $rest]; $off = 0; ?>
          <div class="mt-4 flex items-center gap-8 max-sm:flex-col">
            <div class="relative h-44 w-44 shrink-0">
              <svg viewBox="0 0 42 42" class="h-full w-full -rotate-90" role="img" aria-label="Share of time per project">
                <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--color-line)" stroke-width="5.5"/>
                <?php foreach ($segs as $i => [$n, $s]): $len = $s / $tot * 100; ?><circle cx="21" cy="21" r="15.915" fill="none" stroke="<?= $shade($i) ?>" stroke-width="5.5" stroke-dasharray="<?= round(max(0, $len - (count($segs) > 1 ? 0.6 : 0)), 3) ?> <?= round(100 - max(0, $len - (count($segs) > 1 ? 0.6 : 0)), 3) ?>" stroke-dashoffset="<?= round(-$off, 3) ?>"/><?php $off += $len; endforeach; ?>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center"><b class="text-xl"><?= e(fmt_hm($r['seconds'])) ?></b><span class="text-nano text-muted">tracked</span></div>
            </div>
            <ul class="m-0 flex min-w-0 flex-1 list-none flex-col gap-2.5 p-0">
              <?php foreach ($segs as $i => [$n, $s]): ?><li class="flex items-center gap-2.5 text-xs"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: <?= $shade($i) ?>"></span><span class="min-w-0 flex-1 truncate" title="<?= e($n) ?>"><?= e($n) ?></span><b class="tabular-nums"><?= e(fmt_hm($s)) ?></b><span class="w-9 text-right text-tiny text-muted tabular-nums"><?= (int)round($s / $tot * 100) ?>%</span></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rt-title">
      <div class="flex items-start justify-between gap-3"><h2 class="m-0 text-base" id="rt-title">Detailed time entries</h2><span class="text-tiny text-muted">Saved entries only</span></div>
      <?php if (!$r['items']): ?>
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
          <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 26) ?></div>
          <h3 class="m-0 text-sm">No results found</h3>
          <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $filtered ? 'Try changing the search terms or clearing one or more filters.' : 'Nothing has been saved today yet. Pick This Week or This Month to see more.' ?></p>
          <?php if ($filtered): ?><a class="btn btn-primary" href="<?= url('/reports') ?>" data-live>Clear filters</a><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="relative mt-4 overflow-x-auto"><table class="min-w-190">
          <thead><tr><th>Date</th><?php if ($multi): ?><th>Member</th><?php endif; ?><th>Project</th><th>Task</th><th>Work description</th><th>Status</th><th>Priority</th><th>Duration</th></tr></thead>
          <tbody><?php foreach ($r['items'] as $x): ?>
            <tr>
              <td class="whitespace-nowrap"><?= e(TimeLog::dateLabel($x['started_at'], $tz)) ?></td>
              <?php if ($multi): ?><td><?= Ui::avatar($x['member'], 'h-6.5 w-6.5') ?></td><?php endif; ?>
              <td><?= e($x['project_name']) ?></td>
              <td><span class="block max-w-48 truncate font-bold" title="<?= e($x['task_title']) ?>"><?= e($x['task_title']) ?></span></td>
              <td><span class="block max-w-72 truncate" title="<?= e($x['work_description']) ?>"><?= e($x['work_description']) ?></span></td>
              <td><?= Ui::tag('task', $x['status']) ?></td><td><?= Ui::tag('priority', $x['priority']) ?></td>
              <td class="whitespace-nowrap font-semibold tabular-nums"><?= e(fmt_hm((int)$x['duration_seconds'])) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php $pg = $r['page']; $from = ($pg - 1) * Report::PER_PAGE + 1; $to = min($r['total'], $pg * Report::PER_PAGE); ?>
        <nav class="mt-3.5 flex flex-wrap items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
          <span>Showing <?= $from ?>–<?= $to ?> of <?= (int)$r['total'] ?></span>
          <?php if ($r['pages'] > 1): ?><span class="flex flex-wrap gap-2">
            <?php if ($pg > 1): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg - 1])) ?>">Previous</a><?php else: ?><span class="btn h-9 opacity-50" aria-disabled="true">Previous</span><?php endif; ?>
            <?php for ($i = 1; $i <= $r['pages']; $i++): if ($r['pages'] > 7 && $i !== 1 && $i !== $r['pages'] && abs($i - $pg) > 1) { if (abs($i - $pg) === 2) echo '<span class="px-1 self-center">…</span>'; continue; } ?>
              <a class="btn h-9 min-w-9 justify-center <?= $i === $pg ? 'btn-primary' : '' ?>" data-live href="<?= e($link(['page' => $i])) ?>"<?= $i === $pg ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?>
            <?php if ($pg < $r['pages']): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg + 1])) ?>">Next</a><?php else: ?><span class="btn h-9 opacity-50" aria-disabled="true">Next</span><?php endif; ?>
          </span><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>
</div>
