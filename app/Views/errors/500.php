<div class="w-full rounded-card border border-line bg-surface p-7.5 shadow-card">
  <div class="mb-4.25 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand">
    <?= icon('shield') ?>
  </div>
  <h1>Something went wrong</h1>
  <p class="text-muted">
    Please try again in a moment.
  </p>
  <?php if (!empty($detail)): ?>
    <div class="mt-3.5 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-xs text-danger">
      <?= e($detail) ?>
    </div>
  <?php endif; ?>
</div>
