<?php $aside = ['eyebrow' => 'Account recovery', 'title' => 'Get back into your account.', 'text' => 'Reset your password with a one-time code. Your workspaces and work history stay exactly as they are.']; ?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('key') ?></div>
  <span class="auth-eyebrow">Account recovery</span>
  <h2 class="text-2xl">Forgot your password?</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">Enter your account email. We will send a six-digit code to reset your password.</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/forgot') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
      <div><label class="field-label" for="f-email">Email address</label><input class="field-input" id="f-email" type="email" name="email" value="<?= old('email') ?>" autocomplete="username" placeholder="you@example.com" required autofocus></div>
    <button class="btn btn-primary w-full" type="submit">Send code</button>
  </form>
  <p class="mt-5 mb-0 flex justify-between border-t border-line pt-4 text-tiny text-muted"><span>Remembered it?</span><a class="auth-link" href="<?= url('/login') ?>">Back to login</a></p>
</div>
