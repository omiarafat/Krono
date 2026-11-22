<?php /* Vars: $ws $inv (email, role_label, manager, expires_at) $link $mailed $tz */
$heading = 'Invitation created'; $sub = 'Share the secure link below with ' . $inv['email'] . '.';
$actions = '<a class="btn" href="' . e(url('/teams')) . '">Back to Teams</a>';
include ROOT . '/app/Views/partials/page-heading.php';
$rows = [['Email', $inv['email']], ['Workspace', $ws['name']], ['Role', $inv['role_label']], ['Reports to', $inv['manager'] ?: 'Not set'], ['Team', $inv['team'] ?: 'No team'], ['Expires', Ui::when($inv['expires_at'], $tz) . ' (' . Team::INVITE_DAYS . ' days)']];
?>
<section class="panel-card mb-6 max-w-3xl p-5.5 max-sm:p-4" aria-labelledby="ic-title" data-invite-link>
  <div class="mb-4 flex items-start justify-between gap-3"><div class="flex items-center gap-3.5"><span class="ws-card-icon"><?= icon('check', 20) ?></span><div><h2 class="m-0 text-base" id="ic-title">Invitation ready</h2><p class="mt-1 mb-0 text-xs text-muted">The person joins by opening the link and signing in or creating an account with this email.</p></div></div><span class="pill">Pending</span></div>
  <dl class="m-0 grid grid-cols-2 gap-x-6 gap-y-3 max-sm:grid-cols-1">
    <?php foreach ($rows as [$k, $v]): ?><div><dt class="text-micro font-bold text-muted"><?= e($k) ?></dt><dd class="m-0 truncate text-xs font-semibold"><?= e($v) ?></dd></div><?php endforeach; ?>
  </dl>
  <div class="mt-5"><label class="field-label" for="ic-link">Invitation link</label>
    <div class="flex gap-2.5 max-sm:flex-col"><input class="field-input min-w-0 flex-1" id="ic-link" type="text" readonly value="<?= e($link) ?>" data-link-input><button class="btn btn-primary" type="button" data-copy><?= icon('file', 16) ?><span data-copy-label>Copy link</span></button></div>
    <p class="mt-1.5 mb-0 text-micro text-muted" data-copy-note>If the button does not work, select the link above and copy it by hand.</p></div>
  <p class="auth-notice mt-5 mb-0"><?= $mailed ? 'We also emailed this link to <strong class="text-ink">' . e($inv['email']) . '</strong>.' : '<strong class="text-ink">Email sending is not set up on this server</strong>, so nothing was emailed. Share the link with the person yourself.' ?> The link only works for <?= e($inv['email']) ?> and stops working after <?= Team::INVITE_DAYS ?> days. You will not see it again after you leave this page, but you can resend the invitation any time from the members list to get a new one.</p>
</section>
<script src="<?= asset('js/krono-invite.js') ?>" defer></script>
