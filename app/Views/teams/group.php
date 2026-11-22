<?php /* One team. Vars: $g (id,name,people,candidates,is_leader,can_admin,can_people) */
$heading = $g['name']; $sub = count($g['people']) . ' ' . (count($g['people']) === 1 ? 'person' : 'people') . ' in this team.';
$actions = '<a class="btn" href="' . e(url('/teams?tab=groups')) . '">Back to teams</a>';
include ROOT . '/app/Views/partials/page-heading.php';
$post = fn(string $do, int $uid, string $label, string $cls = 'btn') => '<form method="post" action="' . e(url('/teams/group/person')) . '" class="m-0">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$g['id'] . '"><input type="hidden" name="user_id" value="' . $uid . '"><input type="hidden" name="do" value="' . $do . '"><button class="' . $cls . ' h-8 px-3" type="submit">' . $label . '</button></form>';
?>
<?= flash_html() ?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="gp-title">
    <h2 class="m-0 text-base" id="gp-title">People</h2>
    <p class="mt-1 mb-4 text-xs text-muted"><?= $g['can_admin'] ? 'You can add people, remove them and choose Team Leaders.' : ($g['is_leader'] ? 'You are a Team Leader: you can add and remove people of this team.' : 'You are a member of this team.') ?></p>
    <?php if (!$g['people']): ?><p class="m-0 rounded-xl border border-dashed border-line px-4 py-8 text-center text-tiny text-muted">Nobody is in this team yet.</p>
    <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line"><table class="min-w-135"><thead><tr><th>Person</th><th>Email</th><th>In team</th><?php if ($g['can_people']): ?><th class="w-0">Actions</th><?php endif; ?></tr></thead><tbody>
      <?php foreach ($g['people'] as $p): $lead = $p['role'] === 'leader'; ?>
        <tr><td><div class="flex items-center gap-2.5"><?= Ui::avatar($p['name'], 'h-8 w-8') ?><span class="truncate text-xs font-bold"><?= e($p['name']) ?></span></div></td>
          <td class="max-w-52 truncate"><?= e($p['email']) ?></td>
          <td><span class="tag tag-<?= $lead ? 'green' : 'gray' ?>"><?= $lead ? 'Team Leader' : 'Member' ?></span></td>
          <?php if ($g['can_people']): ?><td><div class="flex gap-2">
            <?php if ($g['can_admin']) echo $lead ? $post('member', (int)$p['id'], 'Remove leader role') : $post('leader', (int)$p['id'], 'Make leader'); ?>
            <?php if ($g['can_admin'] || !$lead) echo $post('remove', (int)$p['id'], 'Remove'); ?>
          </div></td><?php endif; ?></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <?php if ($g['can_people']): ?>
      <form method="post" action="<?= url('/teams/group/person') ?>" class="mt-4 flex gap-2.5 max-sm:flex-col" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="do" value="add">
        <select class="field-input min-w-0 flex-1" name="user_id" aria-label="Add a person" required><option value="">Add a person…</option><?php foreach ($g['candidates'] as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> · <?= e($c['email']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary" type="submit"><?= icon('plus', 16) ?> Add to team</button></form>
    <?php endif; ?>
  </section>
  <?php if ($g['can_admin']): ?>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="gs-title">
    <h2 class="m-0 text-base" id="gs-title">Team settings</h2>
    <form method="post" action="<?= url('/teams/group/update') ?>" class="mt-4 flex gap-2.5 max-sm:flex-col" novalidate><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="do" value="rename">
      <input class="field-input min-w-0 flex-1" type="text" name="name" maxlength="<?= Groups::NAME_MAX ?>" value="<?= e($g['name']) ?>" aria-label="Team name" required><button class="btn btn-primary" type="submit">Rename</button></form>
    <form method="post" action="<?= url('/teams/group/update') ?>" class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-start" onsubmit="return confirm('Delete this team? People stay in the workspace.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="do" value="delete">
      <p class="m-0 text-micro text-muted">Deleting a team does not remove anyone from the workspace.</p><button class="btn" type="submit"><?= icon('trash', 16) ?> Delete team</button></form>
  </section>
  <?php endif; ?>
</div>
