<?php
/* Attendance Details (read-only). Vars: $s (session or null) $breaks $calc $emp $empId $date $prev $next $today $tz */
$field = 'mt-1 rounded-control border border-line bg-raised px-3 py-2.5 text-xs text-ink';
$label = 'text-micro font-semibold text-muted';
$head = fn(string $ic, string $t) => '<h2 class="m-0 mb-4 flex items-center gap-2.5 text-sm"><span class="text-brand">' . icon($ic, 18) . '</span>' . e($t) . '</h2>';
$nav = 'icon-button h-8 w-8';
$dateShown = DateTimeImmutable::createFromFormat('!Y-m-d', $date)->format('M d, Y');
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <div class="flex items-start justify-between gap-6 pt-6.5 max-sm:flex-col max-sm:gap-4">
    <div class="min-w-0">
      <div class="mb-1.5 text-nano font-extrabold tracking-eyebrow text-brand">KRONO WORKSPACE / ATTENDANCE</div>
      <h1 class="m-0">Attendance Details</h1>
      <p class="mt-1 mb-0 text-ui text-muted">View daily attendance, break records and net work time.</p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <form method="get" action="<?= url('/attendance/details') ?>" data-autosubmit class="m-0">
        <input type="hidden" name="user" value="<?= (int)$empId ?>">
        <input type="date" name="date" value="<?= e($date) ?>" max="<?= e($today) ?>" data-required aria-label="Attendance date">
      </form>
      <?php if ($prev): ?><a class="<?= $nav ?>" href="<?= url('/attendance/details?id=' . $prev) ?>" aria-label="Previous record" title="Previous record"><?= icon('chev-left', 16) ?></a><?php else: ?><span class="<?= $nav ?> cursor-not-allowed opacity-40" aria-disabled="true"><?= icon('chev-left', 16) ?></span><?php endif; ?>
      <?php if ($next): ?><a class="<?= $nav ?>" href="<?= url('/attendance/details?id=' . $next) ?>" aria-label="Next record" title="Next record"><?= icon('chev-right', 16) ?></a><?php else: ?><span class="<?= $nav ?> cursor-not-allowed opacity-40" aria-disabled="true"><?= icon('chev-right', 16) ?></span><?php endif; ?>
    </div>
  </div>

  <div class="pair-grid-a">
    <section class="panel-card p-5.5">
      <?= $head('team', 'Employee Information') ?>
      <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-44 items-center gap-3.5">
          <b class="inline-flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-soft text-sm font-bold text-brand"><?= e(Project::initials($emp['name'])) ?></b>
          <div class="min-w-0"><div class="truncate text-sm font-bold"><?= e($emp['name']) ?></div><div class="text-tiny text-muted"><?= e(ucfirst((string)$emp['role'])) ?></div></div>
        </div>
        <dl class="m-0 grid min-w-48 flex-1 grid-cols-2 gap-x-3.5 gap-y-3 max-sm:grid-cols-1">
          <div class="min-w-0"><dt class="<?= $label ?>">Email</dt><dd class="m-0 truncate <?= $field ?>"><?= e($emp['email']) ?></dd></div>
          <div class="min-w-0"><dt class="<?= $label ?>">Role</dt><dd class="m-0 <?= $field ?>"><?= e(ucfirst((string)($emp['role'] ?? '—'))) ?></dd></div>
          <div class="min-w-0"><dt class="<?= $label ?>">Manager</dt><dd class="m-0 truncate <?= $field ?>"><?= e($emp['manager'] ?: '—') ?></dd></div>
          <div class="min-w-0"><dt class="<?= $label ?>">Timezone</dt><dd class="m-0 <?= $field ?>"><?= e(Attendance::tzLabel($tz)) ?></dd></div>
        </dl>
      </div>
    </section>

    <section class="panel-card p-5.5">
      <?= $head('time', 'Time Records') ?>
      <?php if (!$s): ?>
        <p class="m-0 rounded-xl border border-dashed border-line px-4 py-8 text-center text-xs text-muted">No attendance record for <?= e($dateShown) ?>.</p>
      <?php else: ?>
        <div class="flex items-start gap-3 border-b border-line pb-3.5">
          <span class="mt-0.5 text-brand"><?= icon('check', 16) ?></span>
          <div><div class="flex items-center gap-2 text-xs font-bold">Clock In</div><div class="mt-1 text-tiny text-muted"><?= e(Attendance::dateTime($s['sign_in_at'], $tz)) ?></div></div>
        </div>
        <div class="flex items-start gap-3 border-b border-line py-3.5">
          <span class="mt-0.5 text-brand"><?= icon('time', 16) ?></span>
          <div><div class="flex items-center gap-2 text-xs font-bold">Clock Out <?php if ($calc['active']): ?><span class="tag tag-green">Working</span><?php elseif ($s['auto_closed']): ?><span class="rounded-full bg-raised px-2 py-0.5 text-nano font-bold text-muted">Closed automatically</span><?php endif; ?></div>
            <div class="mt-1 text-tiny text-muted"><?= $calc['active'] ? '— Current attendance is in progress' : e(Attendance::dateTime($s['sign_out_at'], $tz)) ?></div></div>
        </div>
        <div class="mt-3.5 grid grid-cols-2 gap-3">
          <div class="rounded-xl border border-brand/25 bg-soft px-3.5 py-3"><div class="text-micro text-muted">Net Work Time</div><div class="mt-1 text-lg font-extrabold text-brand"><?= e(fmt_hm($calc['net'])) ?></div></div>
          <div class="rounded-xl border border-line bg-raised px-3.5 py-3"><div class="text-micro text-muted">Gross Time</div><div class="mt-1 text-lg font-extrabold"><?= e(fmt_hm($calc['gross'])) ?></div></div>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <?php if ($s): ?>
  <div class="pair-grid-b">
    <section class="panel-card p-5.5">
      <?= $head('time', 'Break Summary') ?>
      <div class="flex flex-wrap items-stretch gap-5">
        <div class="flex w-44 shrink-0 flex-col items-center justify-center rounded-xl border border-line bg-raised px-3 py-5 text-center max-sm:w-full">
          <span class="mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-soft text-brand"><?= icon('time', 18) ?></span>
          <div class="text-lg font-extrabold"><?= e(fmt_break_total($calc['break'])) ?></div><div class="text-micro text-muted">Total break time</div>
        </div>
        <ol class="m-0 min-w-56 flex-1 list-none p-0">
          <?php if (!$breaks): ?><li class="rounded-xl border border-dashed border-line px-4 py-8 text-center text-xs text-muted">No breaks were taken in this session.</li><?php endif; ?>
          <?php foreach ($breaks as $i => $b):
            $open = $b['end_at'] === null; $end = $open ? ($s['sign_out_at'] ?? gmdate('Y-m-d H:i:s')) : $b['end_at'];
            $secs = max(0, strtotime($end . ' UTC') - strtotime($b['start_at'] . ' UTC')); ?>
            <li class="flex items-start gap-3 border-b border-line py-3 first:pt-0 last:border-b-0">
              <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-soft text-nano font-bold text-brand"><?= $i + 1 ?></span>
              <div class="min-w-0 flex-1"><div class="text-xs font-bold">Break #<?= $i + 1 ?></div>
                <div class="mt-1 text-micro text-muted"><?= e(Attendance::dateTime($b['start_at'], $tz)) ?> – <?= $open && !$s['sign_out_at'] ? 'on break now' : e(Attendance::dateTime($end, $tz)) ?></div></div>
              <div class="shrink-0 text-xs font-bold"><?= e(fmt_break($secs)) ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <section class="panel-card p-5.5">
      <?= $head('time', 'Time Breakdown') ?>
      <dl class="m-0 text-xs">
        <div class="flex justify-between border-b border-line py-3"><dt class="text-muted">Gross Time</dt><dd class="m-0 font-bold"><?= e(fmt_hm($calc['gross'])) ?></dd></div>
        <div class="flex justify-between border-b border-line py-3"><dt class="text-muted">Break</dt><dd class="m-0 font-bold">−<?= e(fmt_break_total($calc['break'])) ?></dd></div>
        <div class="flex justify-between py-3 font-bold text-brand"><dt>Net Work Time</dt><dd class="m-0"><?= e(fmt_hm($calc['net'])) ?></dd></div>
      </dl>
    </section>
  </div>
  <?php endif; ?>
</div>
