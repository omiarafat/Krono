<?php
/* Attendance history (read-only). Vars: $ws $tz $f $list (or null) $people $any $failed $downloadUrl */
$filtered = Attendance::filtered($f); $multi = count($people) > 1; $all = $f['member'] === 0;
$openApp = '<form method="post" action="' . url('/open-app') . '" class="m-0">' . csrf_field() . '<button type="submit" class="btn">Open Krono Desktop</button></form>';
$dl = '<a class="btn btn-primary" href="' . e($downloadUrl) . '">' . icon('download', 16) . ' Download for Windows</a>';
if ($any || $failed) { $heading = 'Attendance'; $sub = 'Review attendance sessions, breaks and net work time'; }
else { $heading = 'Track time with Krono Desktop'; $sub = 'Install the lightweight Windows app to control attendance and task timers. Your saved history will appear here.'; $crumb = 'Track time with Krono Desktop'; }
$actions = $dl;
include ROOT . '/app/Views/partials/page-heading.php';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$link = fn(array $o = []) => url(Attendance::path($f, $o));
$entries = fn(int $n) => $n === 1 ? '1 session' : $n . ' sessions';
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
  <?php $calloutIcon = 'attendance'; $calloutTitle = 'Attendance is managed in Krono Desktop'; $calloutText = 'Use the Windows app to sign in, start/end breaks and sign out. Review full history here.'; include ROOT . '/app/Views/partials/desktop-callout.php'; ?>

  <form method="get" action="<?= url('/attendance') ?>" class="flex flex-wrap items-center gap-2.5" id="att-filters" role="search" data-live data-live-target="att-results">
    <div class="krange" data-krange data-today="<?= e($f['default'][1]) ?>">
      <input type="hidden" name="from" value="<?= e($f['from']) ?>" data-default="<?= e($f['default'][0]) ?>" data-live-change>
      <input type="hidden" name="to" value="<?= e($f['to']) ?>" data-default="<?= e($f['default'][1]) ?>" data-live-change>
    </div>
    <?php if ($multi): ?>
    <select name="member" aria-label="Member" data-kauto class="<?= $select ?>">
      <option value="<?= (int)$f['uid'] ?>"<?= $f['member'] === $f['uid'] ? ' selected' : '' ?>>Member: Me</option>
      <option value="everyone"<?= $all ? ' selected' : '' ?>>All members</option>
      <?php foreach ($people as $id => $name): if ((int)$id === $f['uid']) continue; ?><option value="<?= (int)$id ?>"<?= $f['member'] === (int)$id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
      <?= icon('search', 16) ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Find a date… (for example Oct 19)" aria-label="Find a date" maxlength="40" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
    </div>
    <button type="submit" class="btn h-10">Apply filters</button>
  </form>

  <div id="att-results" data-live-area>
  <section class="panel-card p-5.5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div><h2>Attendance history</h2><p class="mt-1 mb-0 text-tiny text-muted">Select a row to view breaks and daily calculation</p></div>
      <span class="rounded-full bg-soft px-2.5 py-1 text-nano font-bold text-brand"><?= e($entries($list['total'])) ?></span>
    </div>

    <?php if (!$list['items']): ?>
      <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
        <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 26) ?></div>
        <h3 class="m-0 text-sm">No results found</h3>
        <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $filtered ? 'Try changing the search terms or clearing one or more filters.' : 'No attendance recorded this month yet. Choose a wider date range to see earlier days.' ?></p>
        <?php if ($filtered): ?><a class="btn btn-primary" href="<?= url('/attendance') ?>" data-live>Clear filters</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="relative mt-4 overflow-x-auto">
        <table class="min-w-170">
          <thead><tr><th>Date</th><?php if ($all): ?><th>Member</th><?php endif; ?><th>Sign in</th><th>Sign out</th><th>Breaks</th><th>Net work time</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($list['items'] as $r): $href = url('/attendance/details?id=' . (int)$r['id']); ?>
            <tr data-href="<?= e($href) ?>">
              <td class="whitespace-nowrap"><a class="text-ink no-underline hover:text-brand hover:underline" href="<?= e($href) ?>"><?= e(Ui::day($r['sign_in_at'], $tz)) ?></a></td>
              <?php if ($all): ?><td><?= Ui::avatar($r['member'], 'h-6.5 w-6.5') ?></td><?php endif; ?>
              <td class="whitespace-nowrap"><?= e(Attendance::clock($r['sign_in_at'], $tz)) ?></td>
              <td class="whitespace-nowrap"><?= $r['active'] ? '<span class="text-muted">Working</span>' : e(Attendance::outLabel($r, $tz)) ?></td>
              <td><?= (int)$r['count'] ?></td>
              <td class="whitespace-nowrap font-semibold"><?= e(fmt_hm($r['net'])) ?></td>
              <td><?php if ($r['active']): ?><span class="tag tag-green">In progress</span><?php else: ?><span class="inline-flex rounded-full bg-soft px-2.5 py-1 text-nano font-bold text-brand"<?= $r['auto_closed'] ? ' title="Closed automatically"' : '' ?>>Recorded</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($list['pages'] > 1): $pg = $list['page']; ?>
        <nav class="mt-3.5 flex items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
          <span>Page <?= $pg ?> of <?= $list['pages'] ?> · <?= e($entries($list['total'])) ?></span>
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
