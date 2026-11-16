<?php /* Shown instead of any Settings page the person's role may not open (the page's existence is not revealed). */ ?>
<div class="mx-auto flex max-w-xl flex-col items-center px-6 py-24 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('file', 30) ?></div>
  <h1 class="text-lg">Page not found</h1>
  <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">This page does not exist.</p>
  <a class="btn btn-primary" href="<?= url('/settings/profile') ?>">Back to settings</a>
</div>
