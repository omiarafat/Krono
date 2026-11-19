<?php
/* Add / edit / view one role. Vars: $mode (new|edit|view) $role (id name description scope permissions builtin) $errors $mine (keys the person has) $myScope */
$view = $mode === 'view'; $have = array_flip($role['permissions']); $total = count(Perm::keys());
$errors ??= []; $err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$icons = ['Projects' => 'projects', 'Tasks' => 'tasks', 'Time & Attendance' => 'time', 'Reports' => 'reports', 'Teams' => 'team', 'Workspace' => 'settings'];
$copyKey = $role['builtin'] !== '' ? $role['builtin'] : (string)$role['id'];
$actions = $view
    ? '<a class="btn btn-primary" href="' . e(url('/settings/workspace/roles/edit?copy=' . $copyKey)) . '">Copy as new role</a>'
    : '<div class="flex items-center gap-2.5"><a class="btn" href="' . e(url('/settings/workspace/roles')) . '" data-guard-link>Cancel</a><button type="submit" form="role-form" class="btn btn-primary" data-save disabled>' . ($mode === 'new' ? 'Add role' : 'Save changes') . '</button></div>';
include __DIR__ . '/../_nav.php';
?>
<form method="post" action="<?= url('/settings/workspace/roles/save') ?>" id="role-form" novalidate<?= $view ? '' : ' data-guard data-required="name"' . ($mode === 'new' ? ' data-new' : '') ?> class="flex flex-col gap-4.5"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$role['id'] ?>">
  <a class="inline-flex w-fit items-center gap-1.5 text-tiny font-bold text-muted no-underline hover:text-ink" href="<?= url('/settings/workspace/roles') ?>" data-guard-link><?= icon('chevron-left', 14) ?> All roles</a>
  <?php if (!empty($errors['_'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save the role.</strong> <?= e($errors['_']) ?></div><?php endif; ?>
  <?php if ($view): ?><div class="rounded-card border border-line bg-surface px-4.5 py-3.5 text-tiny leading-copy text-muted" role="note"><?= $role['builtin'] !== '' ? 'Built-in roles are fixed so Krono always behaves the same way. Use “Copy as new role” to start a custom role from this one.' : 'This role gives more access than you have, so you can look at it but not change it.' ?></div><?php endif; ?>

  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rd-title">
    <h2 class="m-0 text-base" id="rd-title">Role details</h2>
    <p class="mt-1 mb-4.5 text-xs text-muted">The name appears in Teams and on invitations.</p>
    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
      <div><label class="field-label" for="r-name">Role name <span class="text-danger">*</span></label><input class="field-input" id="r-name" name="name" maxlength="40" value="<?= e($role['name']) ?>"<?= $view ? ' disabled' : '' ?><?= $bad('name') ?> autocomplete="off"><?= $err('name') ?></div>
      <div><label class="field-label" for="r-desc">Short description</label><input class="field-input" id="r-desc" name="description" maxlength="120" value="<?= e($role['description']) ?>"<?= $view ? ' disabled' : '' ?><?= $bad('description') ?> autocomplete="off"><?= $err('description') ?></div>
    </div>
  </section>

  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rs-title">
    <h2 class="m-0 text-base" id="rs-title">Data scope</h2>
    <p class="mt-1 mb-4.5 text-xs text-muted">Whose data people with this role can see in Time Tracking, Attendance and Reports.</p>
    <div class="grid grid-cols-3 gap-3.5 max-sm:grid-cols-1">
      <?php foreach ([['own', 'Own data only', 'Only their own time, attendance and tasks.'], ['team', 'Own data and direct reports', 'Their own data plus the people who report to them.'], ['all', 'Whole workspace', 'Everyone in the workspace.']] as [$k, $label, $text]):
        $off = $view || (Perm::SCOPE_RANK[$k] > Perm::SCOPE_RANK[$myScope] && $k !== $role['scope']); ?>
        <label class="relative flex items-start gap-3 rounded-card border border-line bg-surface p-4 has-checked:border-brand has-checked:bg-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand <?= $off ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-brand/50' ?>">
          <input type="radio" name="scope" value="<?= $k ?>" class="mt-0.5 h-4 w-4 shrink-0 accent-brand"<?= $role['scope'] === $k ? ' checked' : '' ?><?= $off ? ' disabled' : '' ?>>
          <span class="min-w-0"><span class="block text-xs font-bold"><?= e($label) ?></span><span class="mt-0.5 block text-tiny leading-copy text-muted"><?= e($text) ?></span></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= $err('scope') ?>
  </section>

  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="rp-title">
    <div class="flex flex-wrap items-start justify-between gap-2"><div><h2 class="m-0 text-base" id="rp-title">Permissions</h2>
      <p class="mt-1 mb-0 text-xs text-muted">Turn on what people with this role may do. A switch that needs another one turns it on too.</p></div>
      <span class="pill pill-line" data-perm-total="<?= $total ?>"><span><span data-perm-count><?= count($role['permissions']) ?></span>&nbsp;of <?= $total ?> on</span></span></div>
    <?= $err('perm') ?>
    <div class="mt-4 flex flex-col gap-4">
      <?php foreach (Perm::CATALOG as $group => $items): ?>
        <div class="rounded-card border border-line">
          <div class="flex items-center gap-2.5 border-b border-line px-4 py-3"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-soft text-brand"><?= icon($icons[$group] ?? 'shield', 16) ?></span><h3 class="m-0 text-xs font-extrabold"><?= e($group) ?></h3></div>
          <?php foreach ($items as $key => [$label, $help]):
            $lack = !$view && !in_array($key, $mine, true); $needs = Perm::NEEDS[$key] ?? ''; ?>
            <div class="flex items-center justify-between gap-4 border-t border-line px-4 py-3 first-of-type:border-t-0">
              <div class="min-w-0"><label for="p-<?= e($key) ?>" class="block text-xs font-bold <?= $view || $lack ? '' : 'cursor-pointer' ?>"><?= e($label) ?></label>
                <p class="m-0 mt-0.5 text-tiny leading-copy text-muted"><?= e($help) ?><?= $lack ? ' You do not have this permission yourself, so you cannot give it.' : '' ?></p></div>
              <input type="checkbox" role="switch" class="switch" id="p-<?= e($key) ?>" name="perm[]" value="<?= e($key) ?>" data-perm="<?= e($key) ?>"<?= $needs ? ' data-needs="' . e($needs) . '"' : '' ?><?= isset($have[$key]) && !($lack && $mode === 'new') ? ' checked' : '' ?><?= $view || $lack ? ' disabled' : '' ?>>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</form>
<?php include __DIR__ . '/../_end.php'; ?>
