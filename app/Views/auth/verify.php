<?php $aside = ['eyebrow' => 'A clearer way to work', 'title' => 'A verified account keeps work connected.', 'text' => 'One verified identity is shared by the Krono website and the Windows desktop app.']; ?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('shield') ?></div>
  <span class="auth-eyebrow">Account security</span>
  <h2 class="text-2xl">Verify your email</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">Enter the six-digit code sent to <strong class="text-ink"><?= e($email ?? '') ?></strong> to continue.</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/verify') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
    <div><label class="field-label" for="v-code">Verification code</label><div data-otp><input class="field-input" id="v-code" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus></div></div>
    <?php if (Auth::fixedCode()): ?><p class="auth-notice m-0"><strong class="text-ink">Development mode:</strong> use the demo code <strong class="text-ink"><?= e((string)env('FIXED_CODE', '123456')) ?></strong>. It only works on a local setup.</p><?php endif; ?>
    <button class="btn btn-primary w-full" type="submit">Verify email</button>
  </form>
  <form method="post" action="<?= url('/verify/resend') ?>" class="mt-5 mb-0 flex justify-between border-t border-line pt-4 text-tiny text-muted"><?= csrf_field() ?>
    <button class="auth-link cursor-pointer border-0 bg-transparent p-0 text-tiny" type="submit">Resend code</button><a class="auth-link" href="<?= url('/login') ?>">Back to login</a>
  </form>
</div>
