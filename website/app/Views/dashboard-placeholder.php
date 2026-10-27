<div class="w-full rounded-[20px] border border-line bg-surface p-7.5 shadow-card">
  <div class="mb-4.25 flex h-11.75 w-11.75 items-center justify-center rounded-[14px] bg-soft text-brand">
    <?= icon('dashboard') ?>
  </div>
  <h1>You are signed in</h1>
  <p class="text-muted">
    <?= e($user['name'] ?? '') ?> · <?= e($user['email'] ?? '') ?>
  </p>
  <p class="mt-3.5 text-[11px] text-muted">The Dashboard page is built in the next stage. This temporary page only confirms that login works.</p>
  <form method="post" action="<?= url('/logout') ?>" class="mt-4.5"><?= csrf_field() ?>
    <button class="inline-flex h-10.5 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand shadow-[0_3px_7px_rgba(4,120,87,.10)] font-[inherit] hover:bg-brand-hover" type="submit">
      Log out
    </button>
  </form>
</div>
