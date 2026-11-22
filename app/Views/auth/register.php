<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('user') ?></div>
  <span class="auth-eyebrow">Create your account</span>
  <h2 class="text-2xl">Start with one account</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">Create your Krono identity. <?= !empty($lockedEmail) ? 'You will join the invited workspace after email verification.' : 'Your first personal workspace is set up after email verification.' ?></p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/register') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
      <div><label class="field-label" for="r-name">Full name</label><input class="field-input" id="r-name" type="text" name="name" value="<?= old('name') ?>" autocomplete="name" placeholder="Your full name" required autofocus></div>
      <div><label class="field-label" for="r-email">Email address</label><input class="field-input" id="r-email" type="email" name="email" value="<?= !empty($lockedEmail) ? e($lockedEmail) : old('email') ?>" autocomplete="email" placeholder="you@example.com" required<?= !empty($lockedEmail) ? ' readonly aria-readonly="true"' : '' ?>><?php if (!empty($lockedEmail)): ?><p class="mt-1.5 mb-0 text-micro text-muted">This email comes from your invitation and cannot be changed.</p><?php endif; ?></div>
      <div><label class="field-label" for="r-pass">Password</label><input class="field-input" id="r-pass" type="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required></div>
      <div><label class="field-label" for="r-pass2">Confirm password</label><input class="field-input" id="r-pass2" type="password" name="password_confirm" autocomplete="new-password" placeholder="Enter password again" required></div>
    <button class="btn btn-primary w-full" type="submit">Create account</button>
  </form>
  <p class="mt-5 mb-0 flex justify-between border-t border-line pt-4 text-tiny text-muted"><span>Already have an account?</span><a class="auth-link" href="<?= url('/login') ?>">Log in</a></p>
  <?php if (empty($lockedEmail)): ?><p class="auth-notice mt-4 mb-0">You start with a <strong class="text-ink">Personal Workspace</strong>. You can create or join company workspaces later using the same account.</p><?php endif; ?>
</div>
