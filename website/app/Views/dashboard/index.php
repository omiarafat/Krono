<?php
$a = $dash['attendance']; $max = max(1, ...array_column($dash['days'], 'seconds'));
$weekLabel = $dash['week'] === 'last' ? 'last week' : 'current week';
$stats = [ // icon, label, value, small line
    ['projects', 'Active projects', sprintf('%02d', $dash['projects']['count']), $dash['projects']['new'] > 0 ? '+' . $dash['projects']['new'] . ' this month' : 'None added this month'],
    ['tasks', 'Open tasks', sprintf('%02d', $dash['tasks']['count']), $dash['tasks']['due'] . ' due this week'],
    ['time', 'Saved time', fmt_hm($dash['saved']), 'This month'],
    ['attendance', 'Attendance', fmt_hm($a['seconds']), $a['working'] ? 'Today · working now' : ($a['has'] ? 'Today · net work' : 'Not signed in today')],
];
$heading = 'Dashboard'; $sub = 'A calm overview of your projects, tasks and saved work time';
include ROOT . '/app/Views/partials/page-heading.php';
$note = 'mt-3.5 mb-0 text-[11px] text-muted'; $link = 'text-[11px] font-bold text-brand no-underline hover:underline';
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <div class="grid grid-cols-4 gap-3.5 max-[1150px]:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($stats as [$ic, $label, $value, $meta]): ?>
      <div class="panel-card flex min-h-27 items-center gap-3 px-4.25 py-4.5">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-[13px] bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="text-[11px] text-muted"><?= e($label) ?></div><div class="mt-1 text-[23px] font-extrabold tracking-[-.8px]"><?= e($value) ?></div><div class="mt-0.75 text-[10px] font-semibold text-brand"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid grid-cols-[1.2fr_1fr] gap-4.5 max-[1000px]:grid-cols-1">
    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-4 flex items-center justify-between gap-4">
        <div><h2>Weekly tracked time</h2><p class="text-[11px] text-muted">Saved time logs · <?= e($weekLabel) ?> · <?= e(fmt_hm($dash['weekTotal'])) ?> total</p></div>
        <form method="get" action="<?= url('/dashboard') ?>">
          <select name="week" aria-label="Week" onchange="this.form.submit()" class="h-9 cursor-pointer rounded-[10px] border border-line bg-surface px-3 font-[inherit] text-[11px] text-muted">
            <option value="this"<?= $dash['week'] === 'this' ? ' selected' : '' ?>>This week</option>
            <option value="last"<?= $dash['week'] === 'last' ? ' selected' : '' ?>>Last week</option>
          </select><noscript><button class="ml-2 h-9 rounded-[10px] border border-line bg-surface px-3 text-[11px]" type="submit">Go</button></noscript>
        </form>
      </div>
      <div class="flex h-41.25 items-end justify-around gap-4 rounded-[10px] border-b border-line bg-[repeating-linear-gradient(to_bottom,transparent_0,transparent_39px,var(--border)_40px)] px-3.5 pt-2" role="img" aria-label="Tracked time per day, <?= e($weekLabel) ?>">
        <?php foreach ($dash['days'] as $day): $h = $day['seconds'] > 0 ? max(6, (int)round(128 * $day['seconds'] / $max)) : 2; ?>
          <div class="flex h-full flex-1 flex-col items-center justify-end gap-2 text-[10px] text-muted" title="<?= e($day['label'] . ': ' . fmt_hm($day['seconds'])) ?>">
            <div class="w-[65%] min-w-3.75 rounded-t-[7px] rounded-b-[2px] bg-linear-to-b from-mint/75 to-[#059669]/60 <?= $day['seconds'] > 0 ? 'opacity-75' : 'opacity-35' ?>" style="height:<?= $h ?>px"></div>
            <span><?= e($day['label']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($dash['weekTotal'] === 0): ?><p class="<?= $note ?>">No saved time for this week yet. Time you save in Krono Desktop appears here.</p><?php endif; ?>
    </section>

    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-4 flex items-center justify-between gap-4"><div><h2>Project progress</h2><p class="text-[11px] text-muted">Recent active projects</p></div><a class="<?= $link ?>" href="<?= url('/projects') ?>">View projects →</a></div>
      <?php if (!$dash['progress']): ?><p class="<?= $note ?>">No active projects yet. Projects you create or join will show up here.</p><?php endif; ?>
      <?php foreach ($dash['progress'] as $p): ?>
        <div class="flex items-center gap-2.5 border-b border-line py-3.25 last:border-b-0">
          <div class="h-2 w-2 shrink-0 rounded-full bg-brand"></div>
          <div class="flex min-w-35 flex-1 flex-col gap-0.75"><strong class="text-[11px]"><?= e($p['name']) ?></strong><span class="text-[11px] text-muted"><?= e(fmt_hm($p['tracked'])) ?> tracked</span></div>
          <div class="h-1.5 w-25 shrink-0 overflow-hidden rounded-full bg-soft" role="progressbar" aria-valuenow="<?= $p['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><i class="block h-full rounded-full bg-brand" style="width:<?= $p['percent'] ?>%"></i></div>
          <span class="w-8 text-right text-[11px]"><?= $p['percent'] ?>%</span>
        </div>
      <?php endforeach; ?>
    </section>
  </div>

  <section class="panel-card px-5.5 py-5">
    <div class="mb-4 flex items-center justify-between gap-4"><div><h2>Recent time logs</h2><p class="text-[11px] text-muted">Saved sessions from Krono Desktop</p></div><a class="<?= $link ?>" href="<?= url('/time-tracking') ?>">View all →</a></div>
    <?php if (!$dash['logs']): ?><p class="<?= $note ?>">No saved time logs yet. Start the timer in Krono Desktop, then Stop and Save.</p>
    <?php else: ?>
    <div class="w-full overflow-x-auto"><table>
      <thead><tr><th>Date</th><th>Project</th><th>Task</th><th>Work description</th><th>Duration</th></tr></thead>
      <tbody>
      <?php foreach ($dash['logs'] as $l): ?>
        <tr><td><?= e($l['date']) ?></td><td><?= e($l['project']) ?></td><td><?= e($l['task']) ?></td><td><?= e($l['desc']) ?></td><td><?= e(fmt_hms($l['seconds'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>
</div>
