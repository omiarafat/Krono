<?php
/* Vars: $prefs (theme, email, weekly, tips) | after a failed save: $error */
$save = '<button type="submit" form="appearance-form" class="btn btn-primary" data-save disabled>Save changes</button>';
$actions = $save;
include __DIR__ . '/_nav.php';
$themes = ['light' => ['Light', 'Bright and clean, best in daylight.'], 'dark' => ['Dark', 'Easy on the eyes in low light.'], 'system' => ['System', 'Follows your computer setting.']];
$mail = [
    'email' => ['Email notifications', 'Get an email when something needs your attention, like an invitation or an account change.'],
    'weekly' => ['Weekly summary', 'A short email each week with your tracked time and finished tasks.'],
    'tips' => ['Product tips', 'Occasional tips about new Krono features.'],
];
?>
<form method="post" action="<?= url('/settings/appearance') ?>" id="appearance-form" novalidate data-guard class="flex flex-col gap-4.5"><?= csrf_field() ?>
  <?php if (!empty($error)): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> <?= e($error) ?></div><?php endif; ?>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="th-title">
    <h2 class="m-0 text-base" id="th-title">Theme</h2>
    <p class="mt-1 mb-4.5 text-xs text-muted">Choose how Krono looks for you. This applies on every device you sign in on.</p>
    <div class="grid grid-cols-3 gap-3.5 max-sm:grid-cols-1">
      <?php foreach ($themes as $k => [$label, $text]): ?>
        <label class="relative flex cursor-pointer items-start gap-3 rounded-card border border-line bg-surface p-4 hover:border-brand/50 has-checked:border-brand has-checked:bg-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-brand">
          <input type="radio" name="theme" value="<?= e($k) ?>" class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer accent-brand"<?= $prefs['theme'] === $k ? ' checked' : '' ?>>
          <span class="min-w-0"><span class="block text-xs font-bold"><?= e($label) ?></span><span class="mt-0.5 block text-tiny leading-copy text-muted"><?= e($text) ?></span></span>
        </label>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="nt-title">
    <h2 class="m-0 text-base" id="nt-title">Notifications</h2>
    <p class="mt-1 mb-2 text-xs text-muted">Choose which emails Krono sends you. Security emails, like sign-in codes, are always sent.</p>
    <?php foreach ($mail as $k => [$label, $text]): ?>
      <div class="flex items-center justify-between gap-4 border-t border-line py-4 first-of-type:border-t-0">
        <div class="min-w-0"><label for="n-<?= e($k) ?>" class="block cursor-pointer text-xs font-bold"><?= e($label) ?></label><p class="m-0 mt-0.5 text-tiny leading-copy text-muted"><?= e($text) ?></p></div>
        <input type="checkbox" role="switch" class="switch" id="n-<?= e($k) ?>" name="<?= e($k) ?>" value="1"<?= $prefs[$k] ? ' checked' : '' ?>>
      </div>
    <?php endforeach; ?>
    <div class="mt-2 flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col max-sm:items-stretch"><p class="m-0 text-micro text-muted">Weekly summaries and product tips start arriving once email delivery is set up for your workspace.</p><?= $save ?></div>
  </section>
</form>
<?php include __DIR__ . '/_end.php'; ?>
