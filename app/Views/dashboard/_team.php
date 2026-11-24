<?php
/* Team overview for Owner / Admin / Manager. Vars: $team (Dashboard::team). Cards and lists only appear when the person may open the page behind them. */
$tone = ['Working' => 'green', 'On break' => 'amber', 'Signed out' => 'gray', 'Absent' => 'red', 'Leave' => 'violet', 'Days off' => 'blue'];
$link = 'text-tiny font-bold text-brand no-underline hover:underline'; $note = 'm-0 text-tiny text-muted';
$cards = [];
if ($team['canAttend'] && !$team['alone']) {
    $cards[] = ['attendance', 'Working now', (string)$team['working'], 'of ' . $team['size'] . ' people'];
    $cards[] = ['alert', 'Absent today', (string)$team['absent'], $team['onLeave'] ? $team['onLeave'] . ' on approved leave' : 'No leave today'];
}
if ($team['requests'] > 0 || $team['canTeam']) $cards[] = ['file', 'Pending requests', (string)$team['requests'], $team['requests'] ? 'Waiting for you' : 'All handled'];
if ($team['canTime'] && !$team['alone']) $cards[] = ['time', 'Team time this week', fmt_hm($team['hoursTotal']), 'Saved time logs'];
?>
<section class="flex flex-col gap-4.5" aria-labelledby="team-ov">
  <div><h2 id="team-ov" class="m-0 text-base">Team overview</h2><p class="mt-1 mb-0 text-tiny text-muted">Today across the people you can see<?= $team['alone'] ? '' : ' · ' . (int)$team['size'] . ' in your data scope' ?></p></div>

  <?php if ($team['alone']): ?>
    <div class="panel-card flex flex-wrap items-center justify-between gap-4 px-5.5 py-5">
      <div class="flex items-center gap-3.5"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon('team', 20) ?></span>
        <div><strong class="text-sm">It's just you here so far</strong><p class="mt-1 mb-0 text-tiny text-muted">Invite your team to see who is working, who is away and where the time goes.</p></div></div>
      <?php if ($team['canInvite']): ?><a class="btn btn-primary" href="<?= url('/teams') ?>">Invite members</a><?php endif; ?>
    </div>
  <?php else: ?>
    <?php if ($cards): ?>
    <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
      <?php foreach ($cards as [$ic, $label, $value, $meta]): ?>
        <div class="panel-card flex min-h-27 items-center gap-3 px-4.25 py-4.5">
          <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
          <div class="min-w-0"><div class="text-tiny text-muted"><?= e($label) ?></div><div class="mt-1 text-stat font-extrabold tracking-stat"><?= e($value) ?></div><div class="mt-0.75 text-micro font-semibold text-brand"><?= e($meta) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dash-two-col">
      <?php if ($team['canAttend']): ?>
      <section class="panel-card min-w-0 px-5.5 py-5">
        <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Today's attendance</h2><p class="text-tiny text-muted">Who is working right now</p></div><a class="<?= $link ?>" href="<?= url('/attendance') ?>">View all →</a></div>
        <?php foreach (array_slice($team['rows'], 0, 8) as $r): ?>
          <div class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0">
            <?= Ui::avatar($r['name'], 'h-8 w-8') ?>
            <div class="min-w-0 flex-1"><div class="truncate text-xs font-bold"><?= e($r['name']) ?></div><div class="text-micro text-muted"><?= $r['since'] !== '' ? 'Signed in ' . e($r['since']) . ' · net ' . e(fmt_hm($r['net'])) : '—' ?></div></div>
            <span class="tag tag-<?= $tone[$r['status']] ?>"><?= e($r['status']) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if (count($team['rows']) > 8): ?><p class="<?= $note ?> mt-3">and <?= count($team['rows']) - 8 ?> more · <a class="<?= $link ?>" href="<?= url('/attendance') ?>">see everyone</a></p><?php endif; ?>
      </section>
      <?php endif; ?>

      <div class="flex min-w-0 flex-col gap-4.5">
        <?php if ($team['requests'] > 0): ?>
        <section class="panel-card px-5.5 py-5">
          <div class="flex items-center justify-between gap-4"><div><h2>Needs your attention</h2><p class="text-tiny text-muted"><?= (int)$team['requests'] ?> leave or days-off <?= $team['requests'] === 1 ? 'request is' : 'requests are' ?> waiting</p></div><a class="btn btn-primary h-9" href="<?= url('/teams') ?>">Review</a></div>
        </section>
        <?php endif; ?>
        <?php if ($team['canTime']): ?>
        <section class="panel-card px-5.5 py-5">
          <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Team time this week</h2><p class="text-tiny text-muted">Top people by saved time</p></div><a class="<?= $link ?>" href="<?= url('/reports') ?>">Reports →</a></div>
          <?php if (!$team['hours']): ?><p class="<?= $note ?>">No saved time this week yet.</p><?php endif; $top = max(1, ...array_column($team['hours'], 'seconds') ?: [1]); ?>
          <?php foreach ($team['hours'] as $h): ?>
            <div class="flex items-center gap-3 py-1.75"><span class="w-28 shrink-0 truncate text-tiny font-semibold"><?= e($h['name']) ?></span>
              <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-soft"><i class="block h-full rounded-full bg-brand" style="width:<?= max(3, (int)round(100 * $h['seconds'] / $top)) ?>%"></i></div>
              <span class="w-14 text-right text-tiny tabular-nums"><?= e(fmt_hm($h['seconds'])) ?></span></div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php if ($team['canTasks'] && $team['overdue']): ?>
        <section class="panel-card px-5.5 py-5">
          <div class="mb-3 flex items-center justify-between gap-4"><div><h2>Overdue tasks</h2><p class="text-tiny text-muted"><?= (int)$team['overdueCount'] ?> past their due date</p></div><a class="<?= $link ?>" href="<?= url('/tasks') ?>">All tasks →</a></div>
          <?php foreach ($team['overdue'] as $t): ?>
            <div class="flex items-center gap-3 border-b border-line py-2.25 last:border-b-0"><div class="min-w-0 flex-1"><div class="truncate text-xs font-bold"><?= e($t['title']) ?></div><div class="truncate text-micro text-muted"><?= e($t['project']) ?> · <?= e($t['assignee']) ?></div></div><span class="text-tiny font-semibold text-tag-amber">Due <?= e($t['due']) ?></span></div>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="border-t border-line pt-4.5"><h2 class="m-0 text-base">Your own work</h2><p class="mt-1 mb-0 text-tiny text-muted">Your projects, tasks and saved time</p></div>
</section>
