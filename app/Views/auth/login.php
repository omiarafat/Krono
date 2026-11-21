<?php $aside = ['eyebrow' => 'Your work, in one place', 'title' => 'Welcome back to your workspace.', 'text' => 'One account connects your personal work and every company workspace you have joined.', 'items' => [['team', 'Switch between workspaces without another login'], ['shield', 'Access is controlled by each workspace membership'], ['time', 'Your work history stays in its own workspace']]]; ?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('user') ?></div>
  <span class="auth-eyebrow">Welcome back</span>
  <h2 class="text-2xl">Log in to Krono</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">Use the same account to access your personal and company workspaces.</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/login') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
      <div><label class="field-label" for="l-email">Email address</label><input class="field-input" id="l-email" type="email" name="email" value="<?= old('email') ?>" autocomplete="username" placeholder="you@example.com" required autofocus></div>
      <div><label class="field-label" for="l-pass">Password</label><input class="field-input" id="l-pass" type="password" name="password" autocomplete="current-password" placeholder="Your password" required></div>
    <div class="-mt-1 text-right text-tiny"><a class="auth-link" href="<?= url('/forgot') ?>">Forgot password?</a></div>
    <button class="btn btn-primary w-full" type="submit">Log in</button>
  </form>
  <p class="mt-5 mb-0 flex justify-between border-t border-line pt-4 text-tiny text-muted"><span>New to Krono?</span><a class="auth-link" href="<?= url('/register') ?>">Create account</a></p>
  <p class="auth-notice mt-4 mb-0">After login, Krono opens your active workspace directly. If you belong to more than one workspace, you can switch between them anytime.</p>
</div>
