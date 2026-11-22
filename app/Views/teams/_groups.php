<?php /* Teams (groups) tab. Vars: $groups (id,name,members,leaders,mine) $canAdmin */ ?>
<div class="p-5.5">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div><h2>Teams</h2><p class="mt-1 mb-0 text-tiny text-muted">A team is a group of people inside this workspace. A Team Leader can manage the people of their own team only.</p></div>
    <?php if ($canAdmin): ?>
    <form method="post" action="<?= url('/teams/groups/create') ?>" class="m-0 flex gap-2.5 max-sm:w-full max-sm:flex-col" novalidate><?= csrf_field() ?>
      <input class="field-input w-56 max-sm:w-full" type="text" name="name" maxlength="<?= Groups::NAME_MAX ?>" placeholder="New team name" aria-label="New team name" required>
      <button class="btn btn-primary" type="submit"><?= icon('plus', 16) ?> Create team</button>
    </form>
    <?php endif; ?>
  </div>
  <?php if (!$groups): ?>
    <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-line px-6 py-12 text-center">
      <div class="mb-3.5 flex h-16 w-16 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('team', 26) ?></div>
      <h3 class="m-0 text-sm">No teams yet</h3>
      <p class="mt-1.5 mb-0 max-w-sm text-tiny leading-copy text-muted"><?= $canAdmin ? 'Create your first team with the box above, for example Design or Development.' : 'You are not in any team yet. An Owner or Admin can add you to one.' ?></p>
    </div>
  <?php else: ?>
    <div class="mt-4 grid grid-cols-3 gap-3.5 max-laptop:grid-cols-2 max-sm:grid-cols-1">
      <?php foreach ($groups as $g): ?>
        <a class="ws-card no-underline hover:border-brand" href="<?= url('/teams/group?id=' . (int)$g['id']) ?>">
          <span class="ws-card-icon"><?= icon('team', 20) ?></span>
          <span class="min-w-0 flex-1"><strong class="block truncate text-xs"><?= e($g['name']) ?></strong>
            <span class="block truncate text-micro text-muted"><?= (int)$g['members'] ?> <?= $g['members'] === 1 ? 'person' : 'people' ?> · <?= $g['leaders'] ? 'Leader: ' . e(implode(', ', $g['leaders'])) : 'No leader yet' ?></span></span>
          <?php if ($g['mine'] === 'leader'): ?><span class="pill">You lead</span><?php elseif ($g['mine']): ?><span class="pill pill-line">You</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
