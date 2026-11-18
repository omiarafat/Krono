<?php /* Invitation link. $state: invalid | signin | accept | other. Vars: $inv $user $token */
$btn = 'inline-flex h-10.5 w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand no-underline shadow-button hover:bg-brand-hover';
$ghost = 'inline-flex h-10.5 w-full cursor-pointer items-center justify-center rounded-xl border border-line bg-surface px-4 text-xs font-bold text-ink no-underline hover:bg-raised';
?>
<div class="w-full rounded-card border border-line bg-surface p-7.5 shadow-card">
  <div class="mb-4.25 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon($state === 'invalid' ? 'alert' : 'team') ?></div>
  <?php if ($state === 'invalid'): ?>
    <h1>Invitation not available</h1>
    <p class="text-muted">This link is not valid any more. It may have expired, been cancelled or already been used. Ask the person who invited you to send a new one.</p>
    <a class="<?= $btn ?> mt-4.5" href="<?= url('/login') ?>">Go to login</a>
  <?php else: $role = strtolower(Perm::label($inv['role'], $inv['custom_role_id'] ?? null, !empty($inv['custom_role_id']) ? (Perm::custom((int)$inv['workspace_id'], (int)$inv['custom_role_id'])['name'] ?? null) : null)); ?>
    <h1>Join <?= e($inv['workspace']) ?></h1>
    <p class="text-muted"><?= e($inv['inviter'] ?: 'An admin') ?> invited <strong><?= e($inv['email']) ?></strong> to join as <?= e($role) ?>.</p>
    <?= flash_html() ?>
    <?php if ($state === 'signin'): ?>
      <p class="text-tiny text-muted">Sign in or create an account with <strong><?= e($inv['email']) ?></strong> to accept.</p>
      <div class="mt-4.5 flex flex-col gap-2.5"><a class="<?= $btn ?>" href="<?= url('/login') ?>">Sign in</a><a class="<?= $ghost ?>" href="<?= url('/register') ?>">Create account</a></div>
    <?php elseif ($state === 'accept'): ?>
      <form method="post" action="<?= url('/invite/accept') ?>" class="mt-4.5"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><button class="<?= $btn ?>" type="submit">Accept invitation</button></form>
    <?php else: ?>
      <div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert">You are signed in as <?= e($user['email']) ?>. This invitation is for <?= e($inv['email']) ?>. Sign out and use that account to accept it.</div>
      <form method="post" action="<?= url('/logout') ?>" class="mt-4.5"><?= csrf_field() ?><button class="<?= $ghost ?>" type="submit">Sign out</button></form>
    <?php endif; ?>
  <?php endif; ?>
</div>
