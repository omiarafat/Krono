<?php /* Information banner at the top of a settings card. Vars: $noteTitle $noteText */ ?>
<div class="mb-4 flex items-start gap-3 rounded-card border border-brand/25 bg-soft px-4.5 py-3.5" role="note">
  <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand text-nano font-extrabold text-on-brand" aria-hidden="true">i</span>
  <div class="min-w-0"><div class="text-tiny font-bold"><?= e($noteTitle) ?></div><p class="m-0 mt-0.5 text-tiny leading-copy text-muted"><?= e($noteText) ?></p></div>
</div>
