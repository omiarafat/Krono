<div class="w-full rounded-[20px] border border-line bg-surface p-[30px] shadow-card">
  <div class="mb-[17px] flex h-[47px] w-[47px] items-center justify-center rounded-[14px] bg-soft text-brand"><?= icon('user') ?></div>
  <h1>Welcome back</h1><p class="text-muted">Sign in to continue with Krono</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/login') ?>" novalidate class="mt-[18px]"><?= csrf_field() ?>
    <div class="mb-5 flex flex-col gap-3.5">
      <label class="block">Email address<input class="flex h-10 w-full items-center rounded-[10px] border border-line bg-surface px-3 text-[13px] font-medium text-ink outline-none [font-family:inherit] focus:border-brand focus:ring-[3px] focus:ring-soft" type="email" name="email" value="<?= old('email') ?>" autocomplete="username" placeholder="name@example.com" required autofocus></label>
      <label class="block">Password<input class="flex h-10 w-full items-center rounded-[10px] border border-line bg-surface px-3 text-[13px] font-medium text-ink outline-none [font-family:inherit] focus:border-brand focus:ring-[3px] focus:ring-soft" type="password" name="password" autocomplete="current-password" placeholder="••••••••••" required></label>
    </div>
    <button class="inline-flex h-10.5 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand shadow-[0_3px_7px_rgba(4,120,87,.10)] font-[inherit] hover:bg-brand-hover" type="submit">Sign in</button>
  </form>
  <p class="mt-[17px] mb-0 text-center text-[11px] text-muted">Forgot password? &nbsp;·&nbsp; <a class="font-semibold text-brand hover:underline" href="<?= url('/register') ?>">Create account</a></p>
</div>
