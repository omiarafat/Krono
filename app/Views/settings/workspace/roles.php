<?php
/* Roles list. Vars: $counts (built-in role => members) $custom (custom roles) $people (token => people). */
$total = count(Perm::keys());
$rows = [];
foreach (Perm::BUILT_IN as $k => [$name, $desc]) $rows[] = ['token' => $k, 'link' => 'role=' . $k, 'name' => $name, 'desc' => $desc, 'custom' => false, 'scope' => Perm::SCOPE_OF[$k], 'perms' => count(Perm::preset($k)), 'members' => (int)($counts[$k] ?? 0), 'invites' => 0, 'id' => 0];
foreach ($custom as $c) $rows[] = ['token' => 'c' . $c['id'], 'link' => 'role=' . $c['id'], 'name' => $c['name'], 'desc' => $c['description'], 'custom' => true, 'scope' => $c['scope'], 'perms' => count($c['permissions']), 'members' => (int)$c['members'], 'invites' => (int)$c['invites'], 'id' => $c['id'], 'editable' => Roles::editable($ws, $c)];
include __DIR__ . '/../_nav.php';
?>
<section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rl-title">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0 flex-1"><h2 class="m-0 text-base" id="rl-title">Roles</h2>
      <p class="mt-1 mb-0 text-xs text-muted">Every member has one role. The five built-in roles are fixed; create your own to fit how your team works. Give roles to people in Teams.</p></div>
    <div class="flex shrink-0 items-center gap-2.5 max-sm:w-full">
      <a class="btn btn-primary max-sm:flex-1" href="<?= e(url('/settings/workspace/roles/edit')) ?>"><?= icon('plus', 16) ?> Add a role</a>
    </div>
  </div>
  <div class="mt-4 max-w-72"><label class="sr-only" for="role-search">Search roles</label><input class="field-input" type="search" id="role-search" placeholder="Search roles" data-role-search autocomplete="off"></div>
  <div class="relative mt-4 overflow-x-auto"><table class="min-w-190">
    <thead><tr><th>Role</th><th>Type</th><th>Data scope</th><th>Permissions</th><th>Members</th><th class="w-0"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $ppl = $people[$r['token']] ?? []; ?>
      <tr data-role-row data-search="<?= e(mb_strtolower($r['name'] . ' ' . $r['desc'])) ?>">
        <td><a class="block text-xs font-bold text-ink no-underline hover:text-brand" href="<?= e(url('/settings/workspace/roles/edit?' . $r['link'])) ?>"><?= e($r['name']) ?></a><div class="text-micro text-muted"><?= e($r['desc'] !== '' ? $r['desc'] : '—') ?></div></td>
        <td><span class="pill <?= $r['custom'] ? '' : 'pill-line' ?>"><?= $r['custom'] ? 'Custom' : 'Built-in' ?></span></td>
        <td class="whitespace-nowrap"><?= e(Perm::SCOPES[$r['scope']]) ?></td>
        <td class="whitespace-nowrap font-semibold"><?= (int)$r['perms'] ?> <span class="font-medium text-muted">of <?= $total ?></span></td>
        <td><?php if ($r['members'] || $r['invites']): ?>
          <div class="flex items-center"><?php foreach (array_slice($ppl, 0, 4) as $i => $p): ?><span class="<?= $i ? '-ml-2' : '' ?> inline-flex rounded-full ring-2 ring-surface" title="<?= e($p['name']) ?>"><?= Avatar::html($p['id'], $p['name'], $p['avatar'], 'h-7 w-7', 'text-micro') ?></span><?php endforeach; ?>
            <span class="ml-2 text-tiny font-semibold"><?= (int)$r['members'] ?><?= $r['invites'] ? ' <span class="font-medium text-muted">+ ' . (int)$r['invites'] . ' invited</span>' : '' ?></span></div>
          <?php else: ?><span class="text-tiny text-muted">No one yet</span><?php endif; ?></td>
        <td class="whitespace-nowrap text-right">
          <a class="icon-button h-7.5 w-7.5 border-transparent bg-transparent" href="<?= e(url('/settings/workspace/roles/edit?' . $r['link'])) ?>" aria-label="<?= $r['custom'] && $r['editable'] ? 'Edit' : 'View' ?> <?= e($r['name']) ?>"><?= icon($r['custom'] && $r['editable'] ? 'settings' : 'eye', 15) ?></a>
          <?php if ($r['custom'] && $r['editable']): ?><button type="button" class="icon-button h-7.5 w-7.5 border-transparent bg-transparent hover:text-danger" data-role-delete="<?= (int)$r['id'] ?>" data-name="<?= e($r['name']) ?>" data-members="<?= (int)$r['members'] ?>" data-invites="<?= (int)$r['invites'] ?>" aria-label="Delete <?= e($r['name']) ?>"><?= icon('trash', 15) ?></button><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="mt-4 mb-0 hidden text-xs text-muted" data-role-none>No role matches your search.</p>
</section>
<dialog id="delete-role-modal" class="modal-box max-w-105" aria-labelledby="dr-title">
  <form method="post" action="<?= url('/settings/workspace/roles/delete') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="">
    <div class="modal-head"><div class="flex-1"><h2 class="m-0 text-base" id="dr-title">Delete this role?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">You are about to delete <strong data-dr-name></strong>.</p></div></div>
    <div class="modal-body">
      <p class="m-0 text-tiny leading-copy text-muted" data-dr-free>Nobody uses this role, so it can be deleted. This cannot be undone.</p>
      <p class="m-0 hidden text-tiny leading-copy text-danger" data-dr-used>This role is still used by <span data-dr-count></span>. Move them to another role in Teams first, then delete it.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>Cancel</button><button type="submit" class="btn btn-danger" data-dr-go>Delete role</button></div>
  </form>
</dialog>
<?php include __DIR__ . '/../_end.php'; ?>
