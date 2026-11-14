<?php /* "Managed in Krono Desktop" card. Needs $calloutTitle, $calloutText, $dl (download button html), $openApp (open button html) */ ?>
<section class="panel-card flex flex-wrap items-center gap-4 p-5.5">
  <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-icon bg-soft text-brand"><?= icon($calloutIcon ?? 'time', 24) ?></div>
  <div class="min-w-64 flex-1">
    <h2 class="text-sm"><?= e($calloutTitle) ?></h2>
    <p class="mt-1 mb-0 text-xs leading-copy text-muted"><?= e($calloutText) ?></p>
  </div>
  <div class="flex flex-wrap gap-2.5"><?= $dl ?><?= $openApp ?></div>
</section>
