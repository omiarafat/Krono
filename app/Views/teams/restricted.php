<?php /* Members and Viewers have no access to Teams. */ ?>
<div class="mx-auto flex max-w-xl flex-col items-center px-6 py-24 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('shield', 30) ?></div>
  <h1 class="text-lg">Teams is for managers and admins</h1>
  <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">Your role does not include member management. Ask a workspace admin if you need a change.</p>
  <a class="btn btn-primary" href="<?= url('/dashboard') ?>">Back to dashboard</a>
</div>
