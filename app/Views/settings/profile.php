<?php
/* Vars: $profile $pending $zones $formats $tz  | after a failed save: $errors $form $askPassword */
$errors = $errors ?? []; $v = $form ?? ['name' => $profile['name'], 'email' => $profile['email'], 'timezone' => $profile['timezone'], 'date_format' => $profile['date_format']];
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$save = '<button type="submit" form="profile-form" class="btn btn-primary" data-save disabled>Save changes</button>';
$actions = $save;
include __DIR__ . '/_nav.php';
$now = new DateTimeImmutable('now', $tz);
?>
<?php if ($pending): ?>
  <section class="panel-card mb-4 border-brand/30 p-5.5" aria-labelledby="pe-title">
    <div class="flex items-start gap-3">
      <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('mail', 20) ?></div>
      <div class="min-w-0 flex-1">
        <h2 class="m-0 text-base" id="pe-title">Confirm your new email</h2>
        <p class="mt-1 mb-0 text-xs leading-copy text-muted">We sent a 6-digit code to <strong class="text-ink"><?= e($pending['new_email']) ?></strong>. Your current email (<?= e($profile['email']) ?>) stays active until you confirm.</p>
        <form method="post" action="<?= url('/settings/email/verify') ?>" novalidate class="mt-3.5 flex flex-wrap items-start gap-2.5"><?= csrf_field() ?>
          <div class="w-44"><input class="field-input" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6-digit code" aria-label="Verification code"<?= $bad('code') ?>><?= $err('code') ?></div>
          <button class="btn btn-primary" type="submit">Confirm email</button>
        </form>
        <div class="mt-2.5 flex flex-wrap gap-4 text-tiny">
          <form method="post" action="<?= url('/settings/email/resend') ?>" class="m-0"><?= csrf_field() ?><button class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-brand hover:underline" type="submit">Send a new code</button></form>
          <form method="post" action="<?= url('/settings/email/cancel') ?>" class="m-0"><?= csrf_field() ?><button class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-muted hover:text-ink hover:underline" type="submit">Cancel the change</button></form>
        </div>
        <?php if (Auth::fixedCode()): ?><p class="mt-2.5 mb-0 text-micro text-muted">Local development: the code is <?= e((string)env('FIXED_CODE', '123456')) ?>.</p><?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>
<section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="pi-title">
  <h2 class="m-0 text-base" id="pi-title">Profile information</h2>
  <p class="mt-1 mb-5 text-xs text-muted">Your name and account identity</p>
  <?php if (!empty($errors['_'])): ?><div class="mb-4 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> <?= e($errors['_']) ?></div><?php endif; ?>
  <div class="mb-5 flex flex-wrap items-center gap-3.5">
    <?= Avatar::html((int)$profile['id'], $profile['name'] ?: $profile['email'], $profile['avatar'], 'h-13.5 w-13.5', 'text-lg') ?>
    <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold"><?= e($profile['name']) ?></div><div class="text-tiny text-muted"><?= $profile['avatar'] ? 'Your photo is shown next to your name' : 'No photo yet. Your first letter is shown instead' ?></div>
      <div class="mt-0.75 text-micro text-muted">JPG, PNG or WebP, up to 2 MB</div></div>
    <div class="flex flex-wrap items-center gap-2">
      <form method="post" action="<?= url('/settings/avatar') ?>" enctype="multipart/form-data" class="m-0" data-photo-form><?= csrf_field() ?>
        <input type="file" name="photo" id="pf-photo" accept="image/jpeg,image/png,image/webp" class="sr-only" data-photo-input>
        <label for="pf-photo" class="btn h-9"><?= $profile['avatar'] ? 'Change photo' : 'Upload photo' ?></label>
      </form>
      <?php if ($profile['avatar']): ?><form method="post" action="<?= url('/settings/avatar/remove') ?>" class="m-0"><?= csrf_field() ?><button type="submit" class="btn h-9">Remove photo</button></form><?php endif; ?>
    </div>
    <p class="m-0 hidden w-full text-tiny text-danger" role="alert" data-photo-error></p>
  </div>
  <form method="post" action="<?= url('/settings/profile') ?>" id="profile-form" novalidate data-guard data-required="name,email" autocomplete="off"><?= csrf_field() ?>
    <div class="grid grid-cols-3 gap-4 max-laptop:grid-cols-2 max-sm:grid-cols-1">
      <div><label class="field-label" for="pf-name">Full name</label><input class="field-input" id="pf-name" name="name" maxlength="100" value="<?= e($v['name']) ?>"<?= $bad('name') ?>><?= $err('name') ?></div>
      <div><label class="field-label" for="pf-email">Email address</label><input class="field-input" id="pf-email" type="email" name="email" maxlength="190" value="<?= e($v['email']) ?>" data-orig="<?= e($profile['email']) ?>"<?= $bad('email') ?>><?= $err('email') ?></div>
      <div><label class="field-label" for="pf-tz">Timezone</label>
        <select class="field-input" id="pf-tz" name="timezone" data-search="on"<?= $bad('timezone') ?>><option value="">Use the workspace timezone</option><?php foreach ($zones as $id => $label): ?><option value="<?= e($id) ?>"<?= $v['timezone'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $err('timezone') ?></div>
      <div><label class="field-label" for="pf-df">Date format</label>
        <select class="field-input" id="pf-df" name="date_format"<?= $bad('date_format') ?>><?php foreach ($formats as $f): ?><option value="<?= e($f) ?>" data-meta="<?= e($now->format(Account::DATE_FORMATS[$f][0])) ?>"<?= $v['date_format'] === $f ? ' selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select><?= $err('date_format') ?></div>
      <div data-email-pw<?= empty($askPassword) && empty($errors['current_password']) ? ' hidden' : '' ?> class="col-span-2 max-sm:col-span-1">
        <label class="field-label" for="pf-pw">Current password <span class="text-danger">*</span></label>
        <input class="field-input" id="pf-pw" type="password" name="current_password" autocomplete="current-password" placeholder="Needed to change your email"<?= $bad('current_password') ?>><?= $err('current_password') ?>
        <p class="mt-1.5 mb-0 text-micro text-muted">We will send a code to the new address. Your current email stays active until you confirm it.</p>
      </div>
    </div>
    <?php $own = (string)($profile['timezone'] ?? '') !== ''; ?>
    <aside class="mt-5 rounded-card border border-brand/25 bg-soft p-4.5" aria-labelledby="tz-note">
      <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-surface text-brand"><?= icon('globe', 18) ?></span>
        <div class="min-w-0">
          <h3 class="m-0 text-xs" id="tz-note">How timezone and date format work</h3>
          <p class="mt-1 mb-0 text-tiny leading-copy text-muted">Right now Krono shows your times in <strong class="text-ink"><?= e($tz->getName()) ?></strong> (<?= $own ? 'your own choice' : 'the workspace timezone' ?>). It is <strong class="text-ink"><?= e($now->format(Account::tf() . ', ' . Account::df())) ?></strong> for you.</p>
          <ul class="mt-2.5 mb-0 flex list-none flex-col gap-1.5 p-0 text-tiny leading-copy text-muted">
            <li class="flex gap-2"><span class="text-brand"><?= icon('check', 13) ?></span><span><strong class="text-ink">Only your screen changes.</strong> Saved work, attendance and time logs are never changed.</span></li>
            <li class="flex gap-2"><span class="text-brand"><?= icon('check', 13) ?></span><span><strong class="text-ink">Want to see the same times as your team?</strong> Keep "Use the workspace timezone".</span></li>
            <li class="flex gap-2"><span class="text-brand"><?= icon('check', 13) ?></span><span><strong class="text-ink">A different timezone can show a different day.</strong> Work saved at 2:00 AM on 12 Nov in Dhaka shows as 3:00 PM on 11 Nov for someone in New York. It is the same moment, only the clock is different.</span></li>
          </ul>
        </div>
      </div>
    </aside>
    <div class="mt-5 flex justify-end"><?= $save ?></div>
  </form>
</section>
<?php if (!empty($dayOff)): $pillName = 'days_off[]'; $pillId = 'me-off'; $pillDays = DaysOff::listOf($dayOff['days']); $pend = $dayOff['pending']; ?>
<section class="panel-card mt-4.5 p-5.5 max-sm:p-4" aria-labelledby="do-title">
  <h2 class="m-0 text-base" id="do-title">Weekly days off</h2>
  <p class="mt-1 mb-4 text-xs text-muted">Your days off follow the workspace (<?= e(DaysOff::label($dayOff['default'])) ?>) unless you ask for your own. A change needs approval from an Owner, an Admin or your manager.</p>
  <?php if ($pend): ?>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-tag-amber/40 bg-tag-amber/10 px-3.5 py-3 text-tiny">
      <span><strong>Waiting for approval:</strong> <?= e(DaysOff::label((string)$pend['days_off'])) ?>. Your current days off stay until it is approved.</span>
      <form method="post" action="<?= url('/requests/cancel') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pend['id'] ?>"><input type="hidden" name="back" value="/settings/profile"><button type="submit" class="btn h-9">Withdraw request</button></form>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= url('/requests/days') ?>" novalidate><?= csrf_field() ?>
    <div class="field-label">Your days off <span class="font-normal text-muted">· <?= $dayOff['custom'] ? 'your own choice' : 'workspace default' ?></span></div>
    <?php include ROOT . '/app/Views/partials/day-pills.php'; ?>
    <div class="mt-4 flex justify-end"><button type="submit" class="btn btn-primary">Send for approval</button></div>
  </form>
</section>
<?php endif; ?>
<?php include __DIR__ . '/_end.php'; ?>
