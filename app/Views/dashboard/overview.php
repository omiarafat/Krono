<?php
/* Dashboard > Overview (Owner / Admin; Manager and custom roles limited to their data scope). Vars: $o (Overview::data) $failed $range $ws */
$heading = 'Dashboard';
$when = ['today' => 'today', 'week' => 'this week', 'month' => 'this month'][$range];
$sub = 'Workspace overview — how ' . ($ws['name'] ?? 'your workspace') . ' is doing ' . $when . ($o && $o['scope'] !== 'all' ? ' · you and ' . max(0, $o['size'] - 1) . ' direct report' . ($o['size'] === 2 ? '' : 's') : '');
$btn = 'inline-flex h-9 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-line bg-surface px-3.5 text-tiny font-bold text-ink no-underline hover:border-brand hover:text-brand';
$actions = '<div class="flex flex-wrap items-center justify-end gap-2.5">' . Overview::tabsHtml('overview');
$actions .= '<form method="get" action="' . url('/dashboard') . '" class="m-0"><input type="hidden" name="tab" value="overview"><select name="range" aria-label="Range" data-kauto data-ksmall onchange="this.form.submit()" class="h-9 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny text-muted">';
foreach (Overview::RANGES as $k => $l) $actions .= '<option value="' . $k . '"' . ($range === $k ? ' selected' : '') . '>' . e($l) . '</option>';
$actions .= '</select><noscript><button class="ml-2 h-9 rounded-control border border-line bg-surface px-3 text-tiny" type="submit">Go</button></noscript></form>';
if ($o && $o['can']['invite']) $actions .= '<a class="' . $btn . '" href="' . url('/teams') . '">' . icon('plus', 15) . ' Invite member</a>';
if ($o && $o['can']['export']) $actions .= '<a class="' . $btn . '" href="' . url('/reports/pdf?from=' . $o['from'] . '&to=' . $o['to']) . '">' . icon('download', 15) . ' Generate report</a>';
$actions .= '</div>';
include ROOT . '/app/Views/partials/page-heading.php';

$link = 'text-tiny font-bold text-brand no-underline hover:underline'; $note = 'm-0 text-tiny text-muted';
$tone = ['Working' => 'green', 'On break' => 'amber', 'Signed out' => 'gray', 'Absent' => 'red', 'Leave' => 'violet', 'Non-working day' => 'blue'];
$tint = ['red' => 'bg-tag-red/15 text-tag-red', 'amber' => 'bg-tag-amber/15 text-tag-amber', 'green' => 'bg-soft text-brand'];
$h = fn(int $s) => $s >= 3600 ? round($s / 3600) . 'h' : ($s > 0 ? max(1, round($s / 60)) . 'm' : '');
$err = fn(string $what) => '<p class="' . $note . '">We couldn\'t load ' . e($what) . '. <a class="' . $link . '" href="' . e(url('/dashboard?tab=overview&range=' . $range)) . '">Try again</a></p>';
$ok = fn($w) => is_array($w) && empty($w['error']);
?>
<div class="flex flex-col gap-4.5 pb-6.5">
<?php if ($failed): ?>
  <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert"><div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div><h2 class="text-base">Connection interrupted</h2><p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the overview. Please try again.</p><a class="btn btn-primary" href="<?= url('/dashboard?tab=overview') ?>">Try again</a></div>
<?php else:
  $a = $o['attendance']; $t = $o['time']; $k = $o['tasks'];
  $cards = [];
  if ($a !== null) $cards[] = ['attendance', 'Working now', $ok($a) ? '<span>' . ($a['working'] + $a['break']) . '</span><span class="text-ui font-semibold text-muted"> / ' . $a['expected'] . '</span>' : '—', $ok($a) ? $a['break'] . ' on break · ' . $a['absent'] . ' absent today' : 'Unavailable'];
  if ($t !== null) $cards[] = ['time', 'Team tracked time', $ok($t) ? e(fmt_hm($t['total'])) : '—', $ok($t) ? ($t['pct'] === null ? $o['rangeLabel'] : ($t['pct'] >= 0 ? '▲ ' : '▼ ') . abs($t['pct']) . '% vs previous') : 'Unavailable'];
  if ($k !== null) { $cards[] = ['tasks', 'Open tasks', $ok($k) ? sprintf('%02d', $k['open']) : '—', $ok($k) ? $k['overdue'] . ' overdue' : 'Unavailable', $ok($k) && $k['overdue'] > 0 ? 'text-tag-red' : 'text-brand'];
    $cards[] = ['eye', 'Waiting for review', $ok($k) ? sprintf('%02d', $k['review']) : '—', $ok($k) ? $k['reviewOld'] . ' waiting more than ' . Overview::REVIEW_DAYS . ' days' : 'Unavailable']; }
?>
  <?php if ($cards): ?>
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($cards as $c): [$ic, $label, $value, $meta] = $c; ?>
      <div class="panel-card flex min-h-27 items-center gap-3 px-4.25 py-4.5">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="text-tiny text-muted"><?= e($label) ?></div><div class="mt-1 text-stat font-extrabold tracking-stat"><?= $value ?></div><div class="mt-0.75 text-micro font-semibold <?= $c[4] ?? 'text-brand' ?>"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="dash-two-col">
    <?php if ($t !== null): ?>
    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-4"><h2>Team tracked time</h2><p class="text-tiny text-muted">Saved time logs from everyone in your scope · <?= e($when) ?></p></div>
      <?php if (!$ok($t)): echo $err('tracked time'); else: $wide = count($t['bars']) > 12; ?>
        <div class="chart-area" role="img" aria-label="Tracked time, <?= e($when) ?>">
          <?php foreach ($t['bars'] as $i => [$label, $sec]): $bh = $sec > 0 ? max(6, (int)round(128 * $sec / $t['max'])) : 2; ?>
            <div class="flex h-full flex-1 flex-col items-center justify-end gap-2 text-micro text-muted" title="<?= e($label . ': ' . fmt_hm($sec)) ?>">
              <?php if (!$wide && $sec > 0): ?><span class="font-semibold text-ink"><?= e($h($sec)) ?></span><?php endif; ?>
              <div class="chart-bar <?= $sec > 0 ? 'opacity-75' : 'opacity-35' ?>" style="height:<?= $bh ?>px"></div>
              <span><?= $wide && $i % 3 !== 0 ? '&nbsp;' : e($label) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($t['total'] === 0): ?><p class="mt-3.5 <?= $note ?>">No saved time for this range yet. Time saved in Krono Desktop appears here.</p><?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-3"><h2>Needs attention</h2><p class="text-tiny text-muted">Things only someone like you can move forward</p></div>
      <?php if (!$ok($o['attention'])): echo $err('this list'); elseif (!$o['attention']): ?><p class="<?= $note ?>">Nothing needs your attention.</p>
      <?php else: foreach ($o['attention'] as [$ic, $title, $why, $count, $to, $tn]): ?>
        <a class="flex items-center gap-3 border-b border-line py-3 text-ink no-underline last:border-b-0 hover:text-brand" href="<?= url($to) ?>">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-icon <?= $tint[$tn] ?>"><?= icon($ic, 18) ?></span>
          <span class="min-w-0 flex-1"><strong class="block truncate text-xs"><?= e($title) ?></strong><span class="block truncate text-micro text-muted"><?= e($why) ?></span></span>
          <strong class="text-sm"><?= (int)$count ?></strong><span class="text-muted" aria-hidden="true">›</span>
        </a>
      <?php endforeach; endif; ?>
    </section>
  </div>

  <div class="dash-two-col">
    <?php if ($a !== null): ?>
    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Who's working now</h2><p class="text-tiny text-muted">Today's attendance · updated <?= e($o['now']) ?></p></div><a class="<?= $link ?>" href="<?= url('/attendance') ?>">View all attendance →</a></div>
      <?php if (!$ok($a)): echo $err('attendance'); else: ?>
      <div class="w-full overflow-x-auto"><table>
        <thead><tr><th>Member</th><th>Status</th><th>Sign in</th><th>Net today</th></tr></thead>
        <tbody><?php foreach (array_slice($a['rows'], 0, 8) as $r): ?>
          <tr><td><div class="flex items-center gap-2.5"><?= Ui::avatar($r['name'], 'h-8 w-8') ?><div class="min-w-0"><div class="truncate text-xs font-bold"><?= e($r['name']) ?></div><div class="text-micro text-muted"><?= e($r['role']) ?></div></div></div></td>
            <td><span class="tag tag-<?= $tone[$r['status']] ?>"><?= e($r['status']) ?></span></td><td><?= $r['in'] !== '' ? e($r['in']) : '—' ?></td><td><?= $r['net'] ? e(fmt_hm($r['net'])) : '—' ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php if (count($a['rows']) > 8): ?><p class="mt-3 <?= $note ?>">and <?= count($a['rows']) - 8 ?> more · <a class="<?= $link ?>" href="<?= url('/attendance') ?>">see everyone</a></p><?php endif; ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($o['projects'] !== null): $p = $o['projects']; ?>
    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Project progress</h2><p class="text-tiny text-muted">Completed tasks ÷ all tasks</p></div><a class="<?= $link ?>" href="<?= url('/projects') ?>">View projects →</a></div>
      <?php if (!$ok($p)): echo $err('projects'); elseif (!$p['rows']): ?><p class="<?= $note ?>">No active projects yet. <a class="<?= $link ?>" href="<?= url('/projects') ?>">Create a project</a></p>
      <?php else: foreach ($p['rows'] as $r): ?>
        <a class="flex items-center gap-3 border-b border-line py-3 text-ink no-underline last:border-b-0 hover:text-brand" href="<?= url('/project?id=' . (int)$r['id']) ?>">
          <span class="min-w-0 flex-1"><strong class="block truncate text-xs"><?= e($r['name']) ?></strong><span class="block text-micro text-muted"><?= isset($r['tracked']) ? e(fmt_hm($r['tracked'])) . ' tracked · ' : '' ?><?= $r['late'] ? '<span class="font-semibold text-tag-red">due date passed</span>' : ($r['overdue'] ? '<span class="font-semibold text-tag-red">' . (int)$r['overdue'] . ' overdue</span>' : 'no overdue') ?></span></span>
          <span class="h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-soft"><i class="block h-full rounded-full bg-brand" style="width:<?= max(3, (int)$r['pct']) ?>%"></i></span><span class="w-10 text-right text-tiny tabular-nums"><?= (int)$r['pct'] ?>%</span>
        </a>
      <?php endforeach; endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <div class="dash-two-col">
    <?php if ($o['workload'] !== null): $w = $o['workload']; ?>
    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Member workload</h2><p class="text-tiny text-muted">Counts only — not a performance score</p></div><?php if ($o['can']['team']): ?><a class="<?= $link ?>" href="<?= url('/teams') ?>">View team →</a><?php endif; ?></div>
      <?php if (!$ok($w)): echo $err('workload'); else: ?>
      <div class="w-full overflow-x-auto"><table>
        <thead><tr><th>Member</th><th>Open</th><th>In review</th><th>Overdue</th><th>Tracked</th></tr></thead>
        <tbody><?php foreach ($w as $r): ?>
          <tr><td><div class="flex items-center gap-2.5"><?= Ui::avatar($r['name'], 'h-8 w-8') ?><strong class="text-xs"><?= e($r['name']) ?></strong></div></td><td><?= (int)$r['open'] ?></td><td><?= (int)$r['review'] ?></td>
            <td><?= $r['overdue'] ? '<span class="font-semibold text-tag-red">' . (int)$r['overdue'] . '</span>' : '0' ?></td><td><?= $r['tracked'] ? e(fmt_hm($r['tracked'])) : '—' ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="panel-card min-w-0 px-5.5 py-5">
      <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Recent activity</h2><p class="text-tiny text-muted">Latest changes across the workspace</p></div><?php if ($o['can']['audit']): ?><a class="<?= $link ?>" href="<?= url('/settings/workspace/audit') ?>">View audit log →</a><?php endif; ?></div>
      <?php if (!$ok($o['activity'])): echo $err('activity'); elseif (!$o['activity']): ?><p class="<?= $note ?>">Nothing has happened yet.</p>
      <?php else: foreach ($o['activity'] as $ev): ?>
        <div class="flex items-center gap-3 border-b border-line py-2.75 last:border-b-0"><?= Ui::avatar($ev['who'], 'h-8 w-8') ?>
          <div class="min-w-0 flex-1 text-xs"><strong><?= e($ev['who']) ?></strong> <?= e($ev['what']) ?><?= $ev['what2'] !== '' ? ' <strong>' . e($ev['what2']) . '</strong>' : '' ?></div><span class="shrink-0 text-micro text-muted"><?= e($ev['ago']) ?></span></div>
      <?php endforeach; endif; ?>
    </section>
  </div>
<?php endif; ?>
</div>
