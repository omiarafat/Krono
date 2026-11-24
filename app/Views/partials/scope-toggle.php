<?php /* Personal workspace only: "Personal only | Personal + linked". Needs $scopeBase (page path) and $scopeNow ('this' | 'all'); shown when linked workspaces exist. */
if (Everywhere::active((int)$user['id'])): ?>
<div class="mb-4.5 flex flex-wrap items-center gap-3">
  <div class="inline-flex rounded-xl border border-line bg-raised p-1" role="group" aria-label="Personal workspace view">
    <?php foreach (['all' => 'Personal + linked', 'this' => 'Personal only'] as $k => $l): ?>
      <a href="<?= url($scopeBase . ($k === 'this' ? '?scope=own' : '')) ?>" class="rounded-lg px-4 py-1.5 text-tiny font-bold no-underline <?= $scopeNow === $k ? 'bg-brand text-on-brand' : 'text-muted hover:text-ink' ?>"<?= $scopeNow === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($scopeNow === 'all'): ?><span class="text-tiny text-muted">Your own work from your personal workspace and the workspaces you linked</span><a class="text-tiny font-bold text-brand no-underline hover:underline" href="<?= url('/workspaces') ?>">Manage links</a><?php endif; ?>
</div>
<?php endif; ?>
