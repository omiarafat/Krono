<?php /* Empty state when nothing is saved yet. Needs $dl and $openApp (button html) */ ?>
<section class="panel-card flex min-h-110 flex-col items-center justify-center px-8 py-12 text-center">
  <div class="mb-5 flex h-24 w-24 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon('time', 38) ?></div>
  <h2 class="text-base">Track your work with Krono Desktop</h2>
  <p class="mt-2 mb-5 max-w-lg text-xs leading-copy text-muted">Attendance and task timer controls live in the lightweight Windows app. Once you save your first session, your history and reports will appear here.</p>
  <div class="flex flex-wrap justify-center gap-2.5"><?= $dl ?><?= $openApp ?></div>
  <p class="mt-5 mb-3 text-tiny text-muted">Already installed? Open the app and sign in with the same Krono account.</p>
  <ul class="m-0 flex list-none flex-wrap justify-center gap-5 p-0 text-tiny font-bold text-brand">
    <?php foreach (['Same account', 'Secure sync', 'Saved history'] as $t): ?><li class="inline-flex items-center gap-1.5"><?= icon('check', 14) ?> <?= $t ?></li><?php endforeach; ?>
  </ul>
</section>
