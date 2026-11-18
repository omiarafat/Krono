<?php
/* Teams. Vars: $ws $tz $f $failed $manage $counts $managers $eligible $roles $inviteForm $back  + ($list = members page) or ($tree = manager cards) */
$structure = $f['tab'] === 'structure';
$heading = 'Teams'; $sub = $structure ? 'See who reports to each manager and manage direct assignments.' : 'Manage members, reporting relationships and workspace access.';
$actions = !$manage ? '' : ($structure
    ? '<button type="button" class="btn btn-primary" data-modal-open="assign-modal">' . icon('plus', 16) . ' Assign Member</button>'
    : '<button type="button" class="btn btn-primary" data-modal-open="invite-modal">' . icon('plus', 16) . ' Invite Member</button>');
include ROOT . '/app/Views/partials/page-heading.php';
$c = $counts;
$stats = $structure
    ? [['team', 'Managers', (string)$c['managers'], 'In this workspace'], ['user', 'Assigned employees', (string)$c['assigned'], 'Direct reports only'], ['alert', 'Unassigned members', (string)$c['unassigned'], 'Can be assigned'], ['shield', 'Scope policy', 'Direct', 'No indirect reports']]
    : [['team', 'Total members', (string)$c['total'], $c['active'] . ' active'], ['check', 'Active members', (string)$c['active'], 'Across workspace'], ['file', 'Pending invitations', (string)$c['invited'], 'Awaiting response'], ['team', 'Managers', (string)$c['managers'], 'Direct reports tracked']];
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$link = fn(array $o = []) => url(Team::path($f, $o));
$tabs = ['members' => ['Members', 'team', $c['total']], 'structure' => ['Reporting Structure', 'team', null]];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <div class="grid grid-cols-4 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
    <?php foreach ($stats as [$ic, $lab, $val, $meta]): ?>
      <div class="panel-card flex min-h-24 items-center gap-3 px-4.25 py-4">
        <div class="flex h-10.75 w-10.75 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($ic) ?></div>
        <div class="min-w-0"><div class="text-tiny text-muted"><?= e($lab) ?></div><div class="mt-0.5 truncate text-stat font-extrabold tracking-stat"><?= e($val) ?></div><div class="mt-0.5 text-micro font-semibold text-brand"><?= e($meta) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <section class="panel-card overflow-hidden">
    <?php if ($manage): ?>
    <nav class="flex gap-6 border-b border-line px-5.5 pt-3.5" aria-label="Teams sections">
      <?php foreach ($tabs as $k => [$label, $ic, $n]): $on = $f['tab'] === $k; ?>
        <a class="-mb-px inline-flex items-center gap-2 border-b-2 pt-1 pb-3 text-xs font-bold no-underline <?= $on ? 'border-brand text-brand' : 'border-transparent text-muted hover:text-ink' ?>" href="<?= e(url(Team::path(['tab' => $k, 'q' => '', 'role' => '', 'status' => '', 'manager' => '', 'page' => 1]))) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
          <?= icon($ic, 15) ?> <?= e($label) ?><?php if ($n !== null): ?> <span class="rounded-full bg-soft px-2 py-0.5 text-nano text-brand"><?= (int)$n ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <?php if ($failed): ?>
      <div class="flex flex-col items-center px-10 py-12 text-center" role="alert">
        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
        <h2 class="text-base">Connection interrupted</h2>
        <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Try again in a moment.</p>
        <a class="btn btn-primary" href="<?= e($link()) ?>">Try again</a>
      </div>
    <?php elseif ($structure): ?>
      <div class="p-5.5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2>Manager assignments</h2><p class="mt-1 mb-0 text-tiny text-muted">Managers can access reports for directly assigned employees only.</p></div>
          <form method="get" action="<?= url('/teams') ?>" class="m-0 w-full max-w-72" role="search" data-live data-live-target="team-results"><input type="hidden" name="tab" value="structure">
            <div class="flex h-10 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
              <?= icon('search', 16) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search managers or employees…" aria-label="Search managers or employees" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
            </div>
          </form>
        </div>
        <div id="team-results" data-live-area><?php include __DIR__ . '/_structure.php'; ?></div>
      </div>
    <?php else: ?>
      <div class="p-5.5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div><h2>Workspace members</h2><p class="mt-1 mb-0 text-tiny text-muted"><?= $manage ? 'Manage access, roles and manager assignments.' : 'You and the people who report directly to you.' ?></p></div>
        </div>
        <form method="get" action="<?= url('/teams') ?>" class="mt-4 flex flex-wrap items-center gap-2.5" id="team-filters" role="search" data-live data-live-target="team-results">
          <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
            <?= icon('search', 16) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search name or email…" aria-label="Search name or email" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
          </div>
          <select name="role" aria-label="Role" data-kauto class="<?= $select ?>"><option value="">All roles</option><?php foreach (Team::filterRoles($ws) as $k => $l): ?><option value="<?= e((string)$k) ?>"<?= $f['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <select name="status" aria-label="Status" data-kauto class="<?= $select ?>"><option value="">All statuses</option><?php foreach (['active' => 'Active', 'invited' => 'Invited', 'deactivated' => 'Deactivated'] as $k => $l): ?><option value="<?= $k ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <?php if ($manage): ?><select name="manager" aria-label="Manager" data-kauto class="<?= $select ?>"><option value="">All managers</option><option value="none"<?= $f['manager'] === 'none' ? ' selected' : '' ?>>No manager</option><?php foreach ($managers as $id => $n): ?><option value="<?= (int)$id ?>"<?= $f['manager'] === (string)$id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?php endif; ?>
        </form>
        <div id="team-results" data-live-area><?php include __DIR__ . '/_members.php'; ?></div>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php
if ($manage && !$failed) {
    include __DIR__ . '/_modals.php';
    include ROOT . '/app/Views/projects/_discard.php';
}
include __DIR__ . '/_drawer.php';
include ROOT . '/app/Views/partials/toast.php';
?>
<script src="<?= asset('js/krono-modals.js') ?>" defer></script>
<script src="<?= asset('js/krono-team.js') ?>" defer></script>
