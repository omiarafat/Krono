<?php /* Shown for an unknown id, another workspace's project and "no access" alike (never reveals that something exists). */ ?>
<div class="mx-auto flex max-w-xl flex-col items-center px-6 py-24 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('search', 30) ?></div>
  <h1 class="text-lg"><?= e($title) ?></h1>
  <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">It may have been archived, or you may not have access to it. Check the link or go back to your projects.</p>
  <a class="btn btn-primary" href="<?= url('/projects') ?>">Back to projects</a>
</div>
