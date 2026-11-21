<?php
$aside = ['eyebrow' => 'Almost there', 'title' => 'Set up your first workspace.', 'text' => 'A personal workspace is your private space for projects and time tracking. Company workspaces and invitations can come later.',
          'items' => [['user', 'Free to start, no payment details'], ['time', 'Edit name and timezone any time'], ['team', 'Create or join company workspaces later']]];
$bad = $bad ?? []; $mark = fn($k) => isset($bad[$k]) ? ' aria-invalid="true"' : '';
?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <ol class="setup-steps" aria-label="Setup progress">
    <li class="setup-step is-done"><span class="setup-dot"><?= icon('check', 12) ?></span>Account</li>
    <li class="setup-step is-now"><span class="setup-dot">2</span>Workspace</li>
    <li class="setup-step"><span class="setup-dot">3</span>Start</li>
  </ol>
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon('user') ?></div>
  <span class="auth-eyebrow">Your first workspace</span>
  <h2 class="text-2xl">Set up your personal workspace</h2>
  <p class="mt-2 mb-0 text-xs leading-copy text-muted">This is your private space for personal projects and time tracking. You can create company workspaces or accept invitations later.</p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/workspace/setup') ?>" novalidate class="mt-5 flex flex-col gap-4"><?= csrf_field() ?>
    <div><label class="field-label" for="w-name">Workspace name</label><input class="field-input" id="w-name" name="name" maxlength="120" value="<?= e($form['name']) ?>" required autofocus<?= $mark('name') ?>></div>
    <div class="flex flex-col gap-4">
      <div><label class="field-label" for="w-tz">Time zone</label>
        <select class="field-input" id="w-tz" name="timezone"<?= $mark('timezone') ?>><?php foreach ($zones as $id => $label): ?><option value="<?= e($id) ?>"<?= $form['timezone'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div><label class="field-label" for="w-cc">Country <span class="font-normal text-muted">(optional)</span></label>
        <select class="field-input" id="w-cc" name="country"<?= $mark('country') ?>><option value="">Not set</option><?php foreach (Workspace::COUNTRIES as $c => $n): ?><option value="<?= e($c) ?>"<?= $form['country'] === $c ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    </div>
    <p class="auth-notice m-0">A personal workspace is free to start. No payment details are required.</p>
    <button class="btn btn-primary w-full" type="submit">Create personal workspace</button>
    <div class="flex items-center justify-between text-tiny text-muted"><span>You can change these later.</span><button class="auth-link cursor-pointer border-0 bg-transparent p-0 text-tiny" type="submit" name="skip" value="1" formnovalidate>Skip optional details</button></div>
  </form>
  <form method="post" action="<?= url('/logout') ?>" class="mt-5 m-0 border-t border-line pt-4 text-tiny text-muted"><?= csrf_field() ?>Signed in as <strong class="text-ink"><?= e($u['email'] ?? '') ?></strong> · <button class="auth-link cursor-pointer border-0 bg-transparent p-0 text-tiny" type="submit">Sign out</button></form>
</div>
