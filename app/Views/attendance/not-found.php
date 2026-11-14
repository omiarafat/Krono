<?php /* Unknown id, or a person the viewer may not see: the same page for both (never reveals that a record exists). */ ?>
<div class="mx-auto flex max-w-xl flex-col items-center px-6 py-24 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 30) ?></div>
  <h1 class="text-lg"><?= e($title) ?></h1>
  <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">It may not exist, or you may not have access to it. Check the link or go back to attendance.</p>
  <a class="btn btn-primary" href="<?= url('/attendance') ?>">Back to attendance</a>
</div>
