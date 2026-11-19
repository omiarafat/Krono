<?php
/* Vars: $g (workspace row + owner) $zones $tz | after a failed save: $errors $form */
$errors = $errors ?? []; $v = $form ?? $g;
$err = fn(string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true"' : '';
$actions = '<button type="submit" form="ws-form" class="btn btn-primary" data-save disabled>Save changes</button>';
include __DIR__ . '/../_nav.php';
$now = new DateTimeImmutable('now', $tz);
$created = (new DateTimeImmutable($g['created_at'] . ' UTC'))->setTimezone($tz);
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="wi-title">
    <h2 class="m-0 text-base" id="wi-title">Workspace identity</h2>
    <p class="mt-1 mb-5 text-xs text-muted">Name and defaults used across the workspace. People can still choose their own timezone and date format in their profile.</p>
    <?php if (!empty($errors['_'])): ?><div class="mb-4 rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> <?= e($errors['_']) ?></div><?php endif; ?>
    <form method="post" action="<?= url('/settings/workspace/general') ?>" id="ws-form" novalidate data-guard data-required="name" autocomplete="off"><?= csrf_field() ?>
      <div class="grid grid-cols-3 gap-4 max-laptop:grid-cols-2 max-sm:grid-cols-1">
        <div><label class="field-label" for="ws-name">Workspace name</label><input class="field-input" id="ws-name" name="name" maxlength="120" value="<?= e($v['name']) ?>"<?= $bad('name') ?>><?= $err('name') ?></div>
        <div><label class="field-label" for="ws-tz">Timezone</label>
          <select class="field-input" id="ws-tz" name="timezone" data-search="on"<?= $bad('timezone') ?>><?php foreach ($zones as $id => $label): ?><option value="<?= e($id) ?>"<?= $v['timezone'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $err('timezone') ?></div>
        <div><label class="field-label" for="ws-week">Week starts on</label>
          <select class="field-input" id="ws-week" name="week_start"<?= $bad('week_start') ?>><?php foreach (Workspace::WEEK_DAYS as $k => $l): ?><option value="<?= e($k) ?>"<?= $v['week_start'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('week_start') ?></div>
        <div><label class="field-label" for="ws-df">Default date format</label>
          <select class="field-input" id="ws-df" name="date_format"<?= $bad('date_format') ?>><?php foreach (Account::DATE_FORMATS as $f => $php): ?><option value="<?= e($f) ?>" data-meta="<?= e($now->format($php[0])) ?>"<?= $v['date_format'] === $f ? ' selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select><?= $err('date_format') ?></div>
        <div><label class="field-label" for="ws-tf">Time format</label>
          <select class="field-input" id="ws-tf" name="time_format"<?= $bad('time_format') ?>><?php foreach (WsSettings::TIME_FORMATS as $k => $l): ?><option value="<?= e($k) ?>"<?= $v['time_format'] === (string)$k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('time_format') ?></div>
        <div><label class="field-label" for="ws-lang">Language</label>
          <select class="field-input" id="ws-lang" name="language"<?= $bad('language') ?>><?php foreach (WsSettings::LANGUAGES as $k => $l): ?><option value="<?= e($k) ?>"<?= $v['language'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('language') ?></div>
      </div>
      <?php if (Perm::me('workspace.daysoff')): $pillName = 'days_off[]'; $pillId = 'ws-off'; $pillDays = DaysOff::listOf((string)($v['days_off'] ?? $g['days_off'])); ?>
      <div class="mt-5"><div class="field-label" id="ws-off-label">Weekly days off</div>
        <?php include ROOT . '/app/Views/partials/day-pills.php'; ?>
        <p class="mt-2 mb-0 text-micro text-muted">Every member, manager and admin gets these days off by default. A person can ask to use their own days in Settings → Profile information; you approve it in Teams. The Owner always follows this setting.</p></div>
      <?php endif; ?>
      <div class="mt-5 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-stretch"><p class="m-0 text-micro text-muted">Changes affect how the workspace shows dates and times. Saved timestamps are not changed.</p>
        <button type="submit" class="btn btn-primary" data-save disabled>Save changes</button></div>
    </form>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="wd-title">
    <h2 class="m-0 text-base" id="wd-title">Workspace details</h2>
    <p class="mt-1 mb-4.5 text-xs text-muted">Reference information for this workspace.</p>
    <dl class="m-0 grid grid-cols-3 gap-4 max-sm:grid-cols-1">
      <div><dt class="text-tiny text-muted">Workspace ID</dt><dd class="m-0 mt-1 text-xs font-bold"><?= e(WsSettings::code((int)$g['id'])) ?></dd></div>
      <div><dt class="text-tiny text-muted">Created</dt><dd class="m-0 mt-1 text-xs font-bold"><?= e($created->format(Account::df())) ?></dd></div>
      <div><dt class="text-tiny text-muted">Workspace owner</dt><dd class="m-0 mt-1 text-xs font-bold"><?= e($g['owner'] ?: '—') ?></dd></div>
    </dl>
  </section>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
