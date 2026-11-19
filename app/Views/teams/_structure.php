<?php
/* Manager cards (the part the search swaps). Vars: $tree [[manager, reports]] $manage $tz $f */
$pill = fn(array $r) => '<span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-micro font-bold ' . ($r['role'] === 'manager' ? 'border-brand/25 bg-soft text-brand' : 'border-line bg-surface text-ink') . '\">' . e($r['role_name']) . '</span>';
$info = fn(array $r) => e(json_encode($r + ['role_label' => $r['role_name'], 'joined_label' => Ui::day($r['joined'], $tz), 'last_label' => Team::lastLabel($r['last'], $tz, true), 'expires_label' => ''], JSON_UNESCAPED_UNICODE));
?>
<?php if (!$tree): ?>
  <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
    <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon($f['q'] !== '' ? 'search' : 'team', 26) ?></div>
    <h3 class="m-0 text-sm"><?= $f['q'] !== '' ? 'No results found' : 'No managers yet' ?></h3>
    <p class="mt-1.5 mb-0 max-w-sm text-tiny leading-copy text-muted"><?= $f['q'] !== '' ? 'Try a different name or email.' : 'Give someone the Manager role to start building reporting lines.' ?></p>
  </div>
<?php else: ?>
  <div class="mt-4 grid grid-cols-2 items-start gap-4 max-laptop:grid-cols-1">
    <?php foreach ($tree as $node): $m = $node['manager']; $n = count($node['reports']); ?>
      <article class="rounded-xl border border-line p-4">
        <div class="flex items-start gap-3">
          <?= Ui::avatar($m['name'], 'h-10 w-10') ?>
          <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold"><?= e($m['name']) ?></div><div class="truncate text-micro text-muted"><?= e($m['email']) ?></div></div>
          <span class="tag tag-green">Manager</span>
        </div>
        <div class="mt-3.5 flex items-center justify-between gap-3">
          <span class="text-tiny text-muted"><?= $n ?> direct report<?= $n === 1 ? '' : 's' ?></span>
          <?php if ($manage): ?><button type="button" class="btn h-9" data-modal-open="assign-modal" data-fill="<?= e(json_encode(['manager_id' => $m['id'], 'user_id' => ''])) ?>"><?= icon('plus', 14) ?> Assign Member</button><?php endif; ?>
        </div>
        <div class="mt-3 ml-4 flex flex-col gap-2 border-l border-line pl-4">
          <?php if (!$node['reports']): ?><p class="m-0 py-2 text-tiny text-muted">No assigned employees yet.</p><?php endif; ?>
          <?php foreach ($node['reports'] as $r): ?>
            <div class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line px-3 py-2.5 hover:bg-raised" data-member="<?= $info($r) ?>">
              <?= Ui::avatar($r['name'], 'h-7 w-7') ?>
              <div class="min-w-0 flex-1"><div class="truncate text-xs font-bold"><?= e($r['name']) ?></div><div class="truncate text-micro text-muted"><?= e($r['email']) ?></div></div>
              <?= $pill($r) ?>
              <button type="button" class="icon-button border-transparent bg-transparent" data-team-menu aria-haspopup="menu" aria-label="Actions for <?= e($r['name']) ?>"><?= icon('more', 18) ?></button>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <p class="mt-4 mb-0 flex items-center gap-2 rounded-xl border border-brand/20 bg-soft px-3.5 py-2.5 text-tiny text-brand"><?= icon('alert', 14) ?> Changing a manager assignment affects future access scope. Saved attendance and time records remain unchanged.</p>
<?php endif; ?>
