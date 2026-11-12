<?php /* Shows the message left by flash() once (success fades, errors stay). Include once per page. */
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
if ($flash): [$ft, $fm, $ftitle] = $flash + [2 => '']; $ok = $ft !== 'error'; ?>
<div class="pointer-events-none fixed right-5 bottom-5 z-50 flex w-full max-w-sm flex-col gap-3 max-sm:right-4 max-sm:left-4 max-sm:max-w-none" aria-live="polite">
  <div class="toast" role="<?= $ok ? 'status' : 'alert' ?>" data-toast="<?= $ok ? 'auto' : 'stay' ?>">
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full <?= $ok ? 'bg-soft text-brand' : 'bg-danger/15 text-danger' ?>"><?= icon($ok ? 'check' : 'alert', 15) ?></span>
    <div class="min-w-0 flex-1"><h4 class="m-0 mb-1 text-xs"><?= e($ftitle ?: ($ok ? 'Done' : 'Something went wrong')) ?></h4><p class="m-0 text-tiny leading-copy text-muted"><?= e($fm) ?></p></div>
    <button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-muted hover:text-ink" data-toast-close aria-label="Dismiss"><?= icon('close', 16) ?></button>
  </div>
</div>
<?php endif; ?>
