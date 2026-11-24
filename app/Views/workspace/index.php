<?php /* Vars: $list (id,name,type,role,members) $currentId */
$heading = 'Choose a workspace'; $sub = 'You belong to ' . (count($list) === 1 ? 'one workspace' : 'more than one workspace') . '. Select one to continue.';
include ROOT . '/app/Views/partials/page-heading.php';
$n = count($list);
?>
<section class="panel-card mb-6 p-5.5 max-sm:p-4" aria-labelledby="ws-list">
  <div class="mb-4 flex items-start justify-between gap-3"><div><h2 class="m-0 text-base" id="ws-list">Your workspaces</h2><p class="mt-1 mb-0 text-xs text-muted">Choose where you want to work. Your account and login stay the same.</p></div><span class="pill"><?= $n ?> <?= $n === 1 ? 'membership' : 'memberships' ?></span></div>
  <?= flash_html() ?>
  <div class="grid grid-cols-2 gap-3.5 max-sm:grid-cols-1">
    <?php foreach ($list as $w): $on = (int)$w['id'] === $currentId; ?>
      <form method="post" action="<?= url('/workspaces/switch') ?>" class="ws-card<?= $on ? ' is-active' : '' ?>"><?= csrf_field() ?><input type="hidden" name="workspace_id" value="<?= (int)$w['id'] ?>">
        <span class="ws-card-icon"><?= icon($w['type'] === 'company' ? 'team' : 'user', 20) ?></span>
        <span class="min-w-0 flex-1"><strong class="block truncate text-xs"><?= e($w['name']) ?></strong><span class="block text-micro text-muted"><?= $w['type'] === 'company' ? 'Company' : 'Personal' ?> · <?= e(ucfirst($w['role'])) ?> · <?= (int)$w['members'] ?> <?= (int)$w['members'] === 1 ? 'member' : 'members' ?></span></span>
        <?php if ($on): ?><span class="pill">Active</span><?php else: ?><button class="btn" type="submit">Switch</button><?php endif; ?>
      </form>
    <?php endforeach; ?>
    <a class="ws-card ws-card-new no-underline" href="<?= url('/workspaces/new') ?>"><span class="ws-card-icon"><?= icon('plus', 20) ?></span><span class="min-w-0 flex-1"><strong class="block text-xs">Create a workspace</strong><span class="block text-micro text-muted">Start a company workspace or upgrade your personal one.</span></span></a>
  </div>
  <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-start"><div><strong class="block text-xs">Workspace context</strong><span class="text-micro text-muted">Projects, teams, permissions and reports are separate in each workspace.</span></div><span class="pill">No account switch required</span></div>
</section>
<?php if (!empty($links)): ?>
<section class="panel-card mb-6 p-5.5 max-sm:p-4" aria-labelledby="ws-links">
  <h2 class="m-0 text-base" id="ws-links">Show other workspaces in Personal</h2>
  <p class="mt-1 mb-4 text-xs text-muted">Choose workspaces whose work you want to see together with your personal workspace. Only your own tasks, projects and time are shown, and you can remove a workspace at any time.</p>
  <div class="flex flex-col">
    <?php foreach ($links as $w): ?>
      <form method="post" action="<?= url('/workspaces/link') ?>" class="flex items-center gap-3 border-b border-line py-3 last:border-b-0"><?= csrf_field() ?><input type="hidden" name="workspace_id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="do" value="<?= $w['linked'] ? 'remove' : 'add' ?>">
        <span class="ws-card-icon"><?= icon($w['type'] === 'company' ? 'team' : 'user', 20) ?></span>
        <span class="min-w-0 flex-1"><strong class="block truncate text-xs"><?= e($w['name']) ?></strong><span class="block text-micro text-muted"><?= $w['type'] === 'company' ? 'Company' : 'Personal' ?> · <?= e(ucfirst($w['role'])) ?></span></span>
        <?php if ($w['linked']): ?><span class="pill">Added</span><button class="btn" type="submit">Remove</button><?php else: ?><button class="btn btn-primary" type="submit">Add to Personal</button><?php endif; ?>
      </form>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
