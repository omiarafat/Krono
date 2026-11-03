<div class="w-full rounded-card border border-line bg-surface p-7.5 shadow-card">
  <div class="mb-4.25 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('user') ?></div>
  <h1>Create your account</h1><p class="text-muted">Manage your time and work in one place</p>
  <?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/register') ?>" novalidate class="mt-4.5"><?= csrf_field() ?>
    <div class="mb-5 flex flex-col gap-3.5">
      <label class="block">Full name<input class="flex h-10 w-full items-center rounded-control border border-line bg-surface px-3 text-ui font-medium text-ink outline-none focus:border-brand focus:ring-3 focus:ring-soft" name="name" value="<?= old('name') ?>" autocomplete="name" required autofocus></label>
      <label class="block">Email address<input class="flex h-10 w-full items-center rounded-control border border-line bg-surface px-3 text-ui font-medium text-ink outline-none focus:border-brand focus:ring-3 focus:ring-soft" type="email" name="email" value="<?= old('email') ?>" autocomplete="email" placeholder="name@example.com" required></label>
      <label class="block">Password<input class="flex h-10 w-full items-center rounded-control border border-line bg-surface px-3 text-ui font-medium text-ink outline-none focus:border-brand focus:ring-3 focus:ring-soft" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required></label>
      <label class="block">Confirm password<input class="flex h-10 w-full items-center rounded-control border border-line bg-surface px-3 text-ui font-medium text-ink outline-none focus:border-brand focus:ring-3 focus:ring-soft" type="password" name="password_confirm" autocomplete="new-password" required></label>
    </div>
    <button class="inline-flex h-10.5 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand shadow-button hover:bg-brand-hover" type="submit">Create account</button>
  </form>
  <p class="mt-4.25 mb-0 text-center text-tiny text-muted">Already have an account? <a class="font-semibold text-brand hover:underline" href="<?= url('/login') ?>">Sign in</a></p>
</div>
