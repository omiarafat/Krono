<?php $heading = $label; $sub = 'This page is built in a later stage.'; include ROOT . '/app/Views/partials/page-heading.php'; ?>
<div class="panel-card mb-6.5 flex min-h-100 flex-col items-center justify-center px-10 py-10 text-center">
  <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon($icon, 30) ?></div>
  <h2 class="text-lg"><?= e($label) ?> is coming soon</h2>
  <p class="max-w-md text-xs leading-relaxed text-muted">We are building Krono one page at a time. The Dashboard is ready; this page will appear in an upcoming update.</p>
  <a class="mt-5 inline-flex h-10.5 items-center rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand no-underline hover:bg-brand-hover" href="<?= url('/dashboard') ?>">Back to Dashboard</a>
</div>
