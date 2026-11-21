<?php $aside = ['eyebrow' => 'Account recovery', 'title' => 'Choose a new password.', 'text' => 'Changing your password signs you out of other browsers and the desktop app for your safety.']; ?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('lock') ?></div>
  <span class="auth-eyebrow">Account recovery</span>
  <h2 class="text-2xl">Set a new password</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">Enter the six-digit code sent to <strong class="text-ink"><?= e($email ?? '') ?></strong> and choose a new password.</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/reset') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
    <div>
      <label class="field-label" for="p-code">Reset code</label>
      <div data-otp>
        <input class="field-input" id="p-code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus>
      </div>
    </div>
      <div><label class="field-label" for="p-pass">New password</label><input class="field-input" id="p-pass" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required></div>
      <div><label class="field-label" for="p-pass2">Confirm new password</label><input class="field-input" id="p-pass2" type="password" name="password_confirm" autocomplete="new-password" placeholder="Enter password again" required></div>
    <?php if (Auth::fixedCode()): ?><p class="auth-notice m-0"><strong class="text-ink">Development mode:</strong> use the demo code <strong class="text-ink"><?= e((string)env('FIXED_CODE', '123456')) ?></strong>. It only works on a local setup.</p><?php endif; ?>
    <button class="btn btn-primary w-full" type="submit">Change password</button>
  </form>
  <form method="post" action="<?= url('/reset/resend') ?>" class="mt-5 mb-0 flex justify-between border-t border-line pt-4 text-tiny text-muted"><?= csrf_field() ?>
    <button class="auth-link cursor-pointer border-0 bg-transparent p-0 text-tiny" type="submit">Resend code</button><a class="auth-link" href="<?= url('/login') ?>">Back to login</a>
  </form>
</div>
