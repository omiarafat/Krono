<?php /* Shown for an unknown id, another workspace's task and "no access" alike (never reveals that a task exists). */ ?>
<div class="mx-auto flex max-w-xl flex-col items-center px-6 py-24 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 30) ?></div>
  <h1 class="text-lg"><?= e($title) ?></h1>
  <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">It may have been deleted, or you may not have access to it. Check the link or go back to your tasks.</p>
  <a class="btn btn-primary" href="<?= url('/tasks') ?>">Back to tasks</a>
</div>
