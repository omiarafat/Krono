<?php
/* Vars: $m (saved email settings) $serverMail | after a failed save: $errors $form */
$errors = $errors ?? []; $v = ($form ?? []) + $m;
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$ok = WsSettings::configured($m); $smtp = $v['provider'] === 'smtp';
$actions = '<button type="submit" form="mail-form" class="btn btn-primary" data-save disabled>Save email settings</button>';
include __DIR__ . '/../_nav.php';
$noteTitle = 'Email configuration'; $noteText = 'Used for member invitations and account messages. Passwords are stored encrypted and are never shown again.';
include __DIR__ . '/../_note.php';
$types = [['Member invitations', 'Invitation link and expiry information', 'mail'], ['Email verification', 'Account verification code', 'shield'], ['Password recovery', 'Secure password reset instructions', 'lock']];
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="se-title">
    <div class="flex items-start justify-between gap-3"><div><h2 class="m-0 text-base" id="se-title">Sender settings</h2><p class="mt-1 mb-5 text-xs text-muted">Choose the identity used for workspace emails.</p></div>
      <span class="pill <?= $ok ? '' : 'pill-bad' ?>"><?= $ok ? 'Configured' : 'Not configured' ?></span></div>
    <?php if (!empty($errors['_'])): ?><div class="mb-4 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> <?= e($errors['_']) ?></div><?php endif; ?>
    <form method="post" action="<?= url('/settings/workspace/email') ?>" id="mail-form" novalidate data-guard autocomplete="off"><?= csrf_field() ?>
      <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
        <div><label class="field-label" for="em-name">From name</label><input class="field-input" id="em-name" name="from_name" maxlength="100" value="<?= e($v['from_name']) ?>" placeholder="Krono Workspace"<?= $bad('from_name') ?>><?= $err('from_name') ?></div>
        <div><label class="field-label" for="em-from">From email</label><input class="field-input" id="em-from" type="email" name="from_email" maxlength="190" value="<?= e($v['from_email']) ?>" placeholder="notifications@example.com"<?= $bad('from_email') ?>><?= $err('from_email') ?></div>
        <div><label class="field-label" for="em-prov">Mail provider</label>
          <select class="field-input" id="em-prov" name="provider" data-provider><option value="default"<?= !$smtp ? ' selected' : '' ?>>Server default</option><option value="smtp"<?= $smtp ? ' selected' : '' ?>>My own mail server (SMTP)</option></select></div>
        <div><label class="field-label" for="em-reply">Reply-to email</label><input class="field-input" id="em-reply" type="email" name="reply_to" maxlength="190" value="<?= e($v['reply_to']) ?>" placeholder="support@example.com"<?= $bad('reply_to') ?>><?= $err('reply_to') ?></div>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-4 max-sm:grid-cols-1" data-smtp<?= $smtp ? '' : ' hidden' ?>>
        <div><label class="field-label" for="em-host">Mail server</label><input class="field-input" id="em-host" name="smtp_host" maxlength="190" value="<?= e($v['smtp_host']) ?>" placeholder="smtp.example.com"<?= $bad('smtp_host') ?>><?= $err('smtp_host') ?></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="field-label" for="em-port">Port</label><input class="field-input" id="em-port" type="number" min="1" max="65535" name="smtp_port" value="<?= (int)$v['smtp_port'] ?>"<?= $bad('smtp_port') ?>><?= $err('smtp_port') ?></div>
          <div><label class="field-label" for="em-sec">Connection</label><select class="field-input" id="em-sec" name="smtp_secure"><?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>"<?= $v['smtp_secure'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        </div>
        <div><label class="field-label" for="em-user">Username</label><input class="field-input" id="em-user" name="smtp_user" maxlength="190" value="<?= e($v['smtp_user']) ?>" autocomplete="off"></div>
        <div><label class="field-label" for="em-pass">Password</label><input class="field-input" id="em-pass" type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= $m['has_pass'] ? '•••••••• saved. Leave blank to keep it' : 'Mail account password' ?>"<?= $bad('smtp_pass') ?>><?= $err('smtp_pass') ?></div>
      </div>
      <?php if (!$smtp): ?><p class="mt-4 mb-0 text-tiny leading-copy text-muted" data-default-note><?= $serverMail ? 'Emails use the mail settings of this Krono server.' : 'This server has no mail delivery set up yet. Choose your own mail server to send real emails.' ?></p><?php endif; ?>
      <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-stretch"><p class="m-0 text-micro text-muted">Provider secrets are stored encrypted and shown masked.</p>
        <button type="submit" class="btn btn-primary" data-save disabled>Save email settings</button></div>
    </form>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="et-title">
    <h2 class="m-0 text-base" id="et-title">Email types</h2>
    <p class="mt-1 mb-2 text-xs text-muted">Essential account and team emails. They are always on.</p>
    <?php foreach ($types as [$t, $text, $ic]): ?>
      <div class="flex items-center gap-3.5 border-t border-line py-4 first-of-type:border-t-0"><span class="text-muted"><?= icon($ic, 20) ?></span><div class="min-w-0 flex-1"><div class="text-xs font-bold"><?= e($t) ?></div><div class="text-tiny text-muted"><?= e($text) ?></div></div><span class="pill">Enabled</span></div>
    <?php endforeach; ?>
    <form method="post" action="<?= url('/settings/workspace/email/test') ?>" class="m-0 mt-1 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-stretch"><?= csrf_field() ?>
      <p class="m-0 text-micro text-muted">The test email is sent only to your own address (<?= e($user['email']) ?>).</p><button type="submit" class="btn">Send test email</button></form>
  </section>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
