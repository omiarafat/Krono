<?php /* needs $heading and $sub; optional $actions = HTML for the right side (buttons) */ ?>
<div class="flex items-end justify-between gap-6 pt-7.5 pb-6 max-sm:flex-col max-sm:items-start max-sm:gap-4">
  <div>
    <div class="mb-1.5 text-nano font-extrabold tracking-eyebrow text-brand">KRONO WORKSPACE</div>
    <h1><?= e($heading) ?></h1>
    <p class="m-0 text-ui text-muted"><?= e($sub) ?></p>
  </div>
  <?php if (!empty($actions)): ?><div class="flex shrink-0 flex-col items-end gap-1.5 max-sm:items-start"><?= $actions ?></div><?php endif; ?>
</div>
