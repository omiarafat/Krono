<?php /* "This workspace | All workspaces" switch. Needs $scopeBase (page path) and $scopeNow ('this' | 'all'); shown only to people in more than one workspace. */
if (Everywhere::count((int)$user['id']) > 1): ?>
<div class="mb-4.5 flex flex-wrap items-center gap-3">
  <div class="inline-flex rounded-xl border border-line bg-raised p-1" role="group" aria-label="Workspace scope">
    <?php foreach (['this' => 'This workspace', 'all' => 'All workspaces'] as $k => $l): ?>
      <a href="<?= url($scopeBase . ($k === 'all' ? '?scope=all' : '')) ?>" class="rounded-lg px-4 py-1.5 text-tiny font-bold no-underline <?= $scopeNow === $k ? 'bg-brand text-on-brand' : 'text-muted hover:text-ink' ?>"<?= $scopeNow === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($scopeNow === 'all'): ?><span class="text-tiny text-muted">Your own work from every workspace you belong to</span><?php endif; ?>
</div>
<?php endif; ?>
