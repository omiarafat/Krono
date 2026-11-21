<?php
/* Vars: after a failed save: $errors */
$errors = $errors ?? [];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$actions = '';
include __DIR__ . '/_nav.php';
?>
<section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="pw-title">
  <div class="flex items-start gap-3">
    <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('lock', 20) ?></div>
    <div><h2 class="m-0 text-base" id="pw-title">Change password</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">When you change it, every other browser and desktop app is signed out.</p></div>
  </div>
  <form method="post" action="<?= url('/settings/password') ?>" novalidate data-guard data-required="current_password,new_password,confirm_password" autocomplete="off" class="mt-5 max-w-lg"><?= csrf_field() ?>
    <div class="flex flex-col gap-4">
      <div><label class="field-label" for="pw-cur">Current password</label><input class="field-input" id="pw-cur" type="password" name="current_password" autocomplete="current-password" placeholder="Enter your current password"<?= $bad('current_password') ?>><?= $err('current_password') ?></div>
      <div><label class="field-label" for="pw-new">New password</label><input class="field-input" id="pw-new" type="password" name="new_password" autocomplete="new-password" maxlength="200" placeholder="At least 8 characters"<?= $bad('new_password') ?>><?= $err('new_password') ?>
        <p class="mt-1.5 mb-0 text-micro text-muted">At least 8 characters, different from your current password.</p></div>
      <div><label class="field-label" for="pw-conf">Confirm new password</label><input class="field-input" id="pw-conf" type="password" name="confirm_password" autocomplete="new-password" maxlength="200" placeholder="Enter new password again"<?= $bad('confirm_password') ?>><?= $err('confirm_password') ?></div>
    </div>
    <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary" data-save disabled>Update password</button></div>
  </form>
</section>
<?php include __DIR__ . '/_end.php'; ?>
