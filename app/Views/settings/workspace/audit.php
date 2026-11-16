<?php
/* Vars: $f $list $actors $failed $tz. Read-only history of workspace changes. */
include __DIR__ . '/../_nav.php';
$filtered = $f['range'] !== '30' || $f['actor'] || $f['action'] !== '' || $f['result'] !== '';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-xs font-semibold text-ink hover:border-brand/50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$link = fn(array $o = []) => url(WsSettings::auditPath($f, $o));
$tf = Workspace::prefs()['time_format'] === '24' ? 'H:i:s' : 'h:i:s A';
$first = $list['total'] ? ($list['page'] - 1) * WsSettings::PAGE + 1 : 0; $last = $first ? $first + count($list['items']) - 1 : 0;
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="al-title">
    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="m-0 text-base" id="al-title">Audit log</h2><p class="mt-1 mb-0 text-xs text-muted">Track important workspace and permission changes.</p></div>
      <?php if (!$failed && $list['total']): ?><a class="btn" href="<?= e(url('/settings/workspace/audit/export' . (strpos($link(), '?') !== false ? substr($link(), strpos($link(), '?')) : ''))) ?>"><?= icon('download', 15) ?> Export log</a><?php endif; ?></div>
    <form method="get" action="<?= url('/settings/workspace/audit') ?>" class="mt-4.5 flex flex-wrap items-center gap-2.5" role="search" data-live data-live-target="audit-results">
      <select name="range" aria-label="Date range" data-kauto class="<?= $select ?>"><?php foreach (WsSettings::RANGES as $k => $l): ?><option value="<?= e((string)$k) ?>"<?= $f['range'] === (string)$k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <select name="actor" aria-label="Actor" data-kauto class="<?= $select ?>"><option value="">All actors</option><?php foreach ($actors as $id => $n): ?><option value="<?= (int)$id ?>"<?= $f['actor'] === (int)$id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
      <select name="action" aria-label="Action" data-kauto class="<?= $select ?>"><option value="">All actions</option><?php foreach (WsSettings::ACTIONS as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['action'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <select name="result" aria-label="Result" data-kauto class="<?= $select ?>"><option value="">All results</option><option value="success"<?= $f['result'] === 'success' ? ' selected' : '' ?>>Success</option><option value="failed"<?= $f['result'] === 'failed' ? ' selected' : '' ?>>Failed</option></select>
    </form>
    <div id="audit-results" data-live-area>
    <?php if ($failed): ?>
      <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center" role="alert"><h3 class="m-0 text-sm">Connection interrupted</h3><p class="mt-1.5 mb-4 text-tiny text-muted">We couldn't load the audit log.</p><a class="btn btn-primary" href="<?= e($link()) ?>">Try again</a></div>
    <?php elseif (!$list['items']): ?>
      <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
        <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('history', 26) ?></div>
        <h3 class="m-0 text-sm"><?= $filtered ? 'No results found' : 'No changes recorded yet' ?></h3>
        <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted"><?= $filtered ? 'Try a wider date range or clear one of the filters.' : 'Role changes, invitations and workspace updates will appear here.' ?></p>
        <?php if ($filtered): ?><a class="btn btn-primary" href="<?= url('/settings/workspace/audit') ?>" data-live>Clear filters</a><?php endif; ?>
      </div>
    <?php else: ?>
      <div class="relative mt-4 overflow-x-auto"><table class="min-w-170">
        <thead><tr><th>Date &amp; time</th><th>Actor</th><th>Action</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($list['items'] as $r): [$title, $detail] = WsSettings::describe($r); $t = (new DateTimeImmutable($r['created_at'] . ' UTC'))->setTimezone($tz); ?>
          <tr><td class="whitespace-nowrap"><div class="text-xs"><?= e($t->format(Account::df())) ?></div><div class="text-micro text-muted"><?= e($t->format($tf)) ?></div></td>
            <td class="whitespace-nowrap"><?= e($r['actor'] ?: 'System') ?></td>
            <td><div class="text-xs font-bold"><?= e($title) ?></div><?php if ($detail !== ''): ?><div class="text-micro text-muted"><?= e($detail) ?></div><?php endif; ?></td>
            <td><span class="pill <?= $r['result'] === 'failed' ? 'pill-bad' : '' ?>"><?= $r['result'] === 'failed' ? 'Failed' : 'Success' ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <nav class="mt-3.5 flex flex-wrap items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
        <span>Showing <?= $first ?>–<?= $last ?> of <?= (int)$list['total'] ?> event<?= $list['total'] === 1 ? '' : 's' ?></span>
        <?php if ($list['pages'] > 1): $pg = $list['page']; ?><span class="flex gap-2">
          <?php if ($pg > 1): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg - 1])) ?>">← Previous</a><?php endif; ?>
          <span class="inline-flex h-9 items-center px-1">Page <?= $pg ?> of <?= $list['pages'] ?></span>
          <?php if ($pg < $list['pages']): ?><a class="btn h-9" data-live href="<?= e($link(['page' => $pg + 1])) ?>">Next →</a><?php endif; ?>
        </span><?php endif; ?>
      </nav>
    <?php endif; ?>
    </div>
  </section>
  <?php $noteTitle = 'Audit integrity'; $noteText = 'Audit records are read-only here. Passwords, codes and sign-in tokens are never included.'; include __DIR__ . '/../_note.php'; ?>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
