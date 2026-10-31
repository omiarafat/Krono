<div class="w-full rounded-[20px] border border-line bg-surface p-7.5 shadow-card">
  <div class="mb-4.25 flex h-11.75 w-11.75 items-center justify-center rounded-[14px] bg-soft text-brand">
    <?= icon('dashboard') ?>
  </div>
  <h1>Opening Krono Desktop</h1>
  <p class="text-muted">Signing in as <?= e($user['email'] ?? '') ?> on the desktop app.</p>
  <p class="mt-3.5 text-[11px] text-muted">If nothing happens, press the button below. This link works once and expires in 1 minute. The app must be installed on this computer.</p>
  <a class="mt-4.5 inline-flex h-10.5 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand shadow-[0_3px_7px_rgba(4,120,87,.10)] font-[inherit] hover:bg-brand-hover" href="<?= e($link) ?>">Open Krono Desktop</a>
  <p class="mt-3.5 mb-0 text-center text-[11px] text-muted"><a class="font-semibold text-brand hover:underline" href="<?= url('/dashboard') ?>">Back to dashboard</a></p>
</div>
<script>setTimeout(function () { window.location.href = <?= json_encode($link, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; }, 400);</script>
