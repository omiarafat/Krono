<?php
/* Members table + pagination (the part live filters swap). Vars: $list $f $tz $manage */
$pill = fn(string $role) => '<span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-micro font-bold ' . ($role === 'manager' ? 'border-brand/25 bg-soft text-brand' : 'border-line bg-surface text-ink') . '">' . e(Team::ROLE_LABEL[$role]) . '</span>';
$state = fn(string $s) => '<span class="tag tag-' . ($s === 'active' ? 'green' : ($s === 'invited' ? 'amber' : 'gray')) . '">' . e(ucfirst($s)) . '</span>';
$from = $list['total'] ? ($list['page'] - 1) * Team::PER_PAGE + 1 : 0; $to = $from ? $from + count($list['items']) - 1 : 0;
?>
<p class="m-0 mt-3 text-right text-tiny text-muted">Showing <?= count($list['items']) ?> of <?= (int)$list['total'] ?> member<?= $list['total'] === 1 ? '' : 's' ?></p>
<?php if (!$list['items']): ?>
  <div class="mt-3 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
    <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 26) ?></div>
    <h3 class="m-0 text-sm">No results found</h3>
    <p class="mt-1.5 mb-4 max-w-sm text-tiny leading-copy text-muted">Try changing the search terms or clearing one or more filters.</p>
    <a class="btn btn-primary" href="<?= url('/teams') ?>" data-live>Clear filters</a>
  </div>
<?php else: ?>
  <div class="relative mt-3 overflow-x-auto rounded-xl border border-line">
    <table class="min-w-215">
      <thead><tr><th>Member</th><th>Email</th><th>Role</th><th>Reports to</th><th>Status</th><th>Last active</th><th class="w-0">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($list['items'] as $r):
        $info = $r + ['role_label' => Team::ROLE_LABEL[$r['role']], 'joined_label' => $r['kind'] === 'invite' ? Ui::day($r['joined'], $tz) : Ui::day($r['joined'], $tz), 'last_label' => Team::lastLabel($r['last'], $tz, true), 'expires_label' => !empty($r['expires']) ? Ui::day($r['expires'], $tz) : '']; ?>
        <tr tabindex="0" data-member="<?= e(json_encode($info, JSON_UNESCAPED_UNICODE)) ?>" class="cursor-pointer hover:bg-raised">
          <td><div class="flex items-center gap-2.5"><?= Ui::avatar($r['name'], 'h-8 w-8') ?><div class="min-w-0"><div class="truncate text-xs font-bold"><?= e($r['name']) ?><?= $r['self'] ? ' <span class="font-semibold text-muted">(you)</span>' : '' ?></div><div class="text-micro text-muted"><?= $r['kind'] === 'invite' ? 'Invitation pending' : ($r['role'] === 'owner' ? 'Workspace owner' : 'Workspace ' . strtolower(Team::ROLE_LABEL[$r['role']])) ?></div></div></div></td>
          <td class="max-w-52 truncate"><?= e($r['email']) ?></td>
          <td><?= $pill($r['role']) ?></td>
          <td><?= $r['manager'] ? e($r['manager']) : '—' ?></td>
          <td><?= $state($r['status']) ?></td>
          <td class="whitespace-nowrap"><?= e(Team::lastLabel($r['last'], $tz)) ?></td>
          <td><button type="button" class="icon-button border-transparent bg-transparent" data-team-menu aria-haspopup="menu" aria-label="Actions for <?= e($r['name']) ?>"><?= icon('more', 18) ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-3.5 flex flex-wrap items-center justify-between gap-3 text-tiny text-muted">
    <span>Tip: select a member to view their profile and reporting details.</span>
    <?php if ($list['pages'] > 1): $pg = $list['page']; ?>
      <nav class="flex items-center gap-1.5" aria-label="Pages">
        <?php if ($pg > 1): ?><a class="btn h-8 px-2.5" data-live href="<?= e(url(Team::path($f, ['page' => $pg - 1]))) ?>" aria-label="Previous page">‹</a><?php endif; ?>
        <?php for ($i = 1; $i <= $list['pages']; $i++): ?><a class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold no-underline <?= $i === $pg ? 'bg-soft text-brand' : 'text-muted hover:text-ink' ?>" data-live href="<?= e(url(Team::path($f, ['page' => $i]))) ?>"<?= $i === $pg ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?>
        <?php if ($pg < $list['pages']): ?><a class="btn h-8 px-2.5" data-live href="<?= e(url(Team::path($f, ['page' => $pg + 1]))) ?>" aria-label="Next page">›</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>
