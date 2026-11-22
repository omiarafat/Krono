<?php /* Invitation link. $state: invalid | signin | accept | other. Vars: $inv $user $token $exists (an account already uses the invited email) */
$aside = ['eyebrow' => 'Workspace invitation', 'title' => $state === 'invalid' ? 'This invitation is not available.' : 'Join your team on Krono.', 'text' => 'One Krono account works across personal and company workspaces. Your login stays the same.',
          'items' => [['shield', 'The link only works for the invited email'], ['team', 'Your role is set by the workspace'], ['time', 'Invitations expire after ' . Team::INVITE_DAYS . ' days']]];
?>
<div class="w-full rounded-card border border-line bg-surface p-8 shadow-card max-sm:p-5.5">
  <div class="mb-4 flex h-11.75 w-11.75 items-center justify-center rounded-tile bg-soft text-brand"><?= icon($state === 'invalid' ? 'alert' : 'team') ?></div>
  <?php if ($state === 'invalid'): ?>
    <span class="auth-eyebrow">Invitation</span>
    <h2 class="text-2xl">Invitation not available</h2>
    <p class="mt-2 text-xs leading-copy text-muted">This link is not valid any more. It may have expired, been cancelled or already been used. Ask the person who invited you to send a new one.</p>
    <a class="btn btn-primary mt-5 w-full" href="<?= url('/login') ?>">Go to login</a>
  <?php else:
    $role = Perm::label($inv['role'], $inv['custom_role_id'] ?? null, !empty($inv['custom_role_id']) ? (Perm::custom((int)$inv['workspace_id'], (int)$inv['custom_role_id'])['name'] ?? null) : null);
    $mine = $state === 'accept'; $shown = $mine ? $inv['email'] : Team::maskEmail($inv['email']);
    $rows = [['Workspace', $inv['workspace']], ['Role', $role], ['Invited by', $inv['inviter'] ?: 'A workspace admin'], ['Invited email', $shown], ['Expires', date(Account::df(), strtotime($inv['expires_at'] . ' UTC'))]]; ?>
    <span class="auth-eyebrow">You are invited</span>
    <h2 class="text-2xl">Join <?= e($inv['workspace']) ?></h2>
    <?= flash_html() ?>
    <dl class="mt-4 mb-0 flex flex-col gap-2.5 rounded-xl border border-line bg-soft/40 p-4">
      <?php foreach ($rows as [$k, $v]): ?><div class="flex justify-between gap-4 text-xs"><dt class="text-muted"><?= e($k) ?></dt><dd class="m-0 truncate text-right font-semibold"><?= e($v) ?></dd></div><?php endforeach; ?>
    </dl>
    <?php if ($state === 'signin'): ?>
      <p class="mt-4 mb-0 text-tiny text-muted"><?= $exists ? 'Sign in with the invited email to accept.' : 'Create an account with the invited email to accept. The email is filled in for you and verified with a code.' ?></p>
      <div class="mt-4 flex flex-col gap-2.5">
        <?php if ($exists): ?><a class="btn btn-primary w-full" href="<?= url('/login') ?>">Sign in to accept</a>
        <?php else: ?><a class="btn btn-primary w-full" href="<?= url('/register') ?>">Create account to accept</a><a class="btn w-full" href="<?= url('/login') ?>">I already have an account</a><?php endif; ?>
      </div>
    <?php elseif ($state === 'accept'): ?>
      <form method="post" action="<?= url('/invite/accept') ?>" class="mt-5"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-primary w-full" type="submit">Accept invitation</button></form>
      <p class="mt-3 mb-0 text-micro text-muted">You are signed in as <?= e($user['email']) ?>.</p>
    <?php else: ?>
      <div class="mt-4 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert">You are signed in as <?= e($user['email']) ?>, but this invitation is for another email. Sign out and use the invited account to accept it.</div>
      <form method="post" action="<?= url('/logout') ?>" class="mt-4"><?= csrf_field() ?><button class="btn w-full" type="submit">Sign out</button></form>
    <?php endif; ?>
  <?php endif; ?>
</div>
