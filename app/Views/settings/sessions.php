<?php
/* Vars: $sessions (kind, id, device, ip, last, created, current) */
$others = count(array_filter($sessions, fn($s) => !$s['current']));
$actions = '';
include __DIR__ . '/_nav.php';
?>
<section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="se-title">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div><h2 class="m-0 text-base" id="se-title">Active sessions</h2><p class="mt-1 mb-0 text-xs text-muted">Browsers and desktop apps signed in to your account.</p></div>
    <?php if ($others): ?><button type="button" class="btn" data-open="others-modal"><?= icon('logout', 15) ?> Sign out other sessions</button><?php endif; ?>
  </div>
  <ul class="m-0 mt-4.5 flex list-none flex-col p-0">
    <?php foreach ($sessions as $s): ?>
      <li class="flex flex-wrap items-center gap-3.5 border-t border-line py-3.5 first:border-t-0">
        <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('monitor', 19) ?></div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2"><strong class="text-xs"><?= e($s['device']) ?></strong><?php if ($s['current']): ?><span class="tag tag-green">This device</span><?php endif; ?></div>
          <div class="mt-0.5 text-micro text-muted"><?= $s['kind'] === 'app' ? 'Desktop app' : 'Website' ?><?= $s['ip'] !== '' ? ' · ' . e($s['ip']) : '' ?> · <?= $s['current'] ? 'Active now' : 'Last used ' . e(Team::lastLabel($s['last'], $tz, true)) ?> · Signed in <?= e(Team::lastLabel($s['created'], $tz)) ?></div>
        </div>
        <?php if ($s['current']): ?>
          <form method="post" action="<?= url('/logout') ?>" class="m-0"><?= csrf_field() ?><button class="btn h-9" type="submit">Sign out</button></form>
        <?php else: ?>
          <form method="post" action="<?= url('/settings/sessions/revoke') ?>" class="m-0"><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($s['kind']) ?>"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn h-9" type="submit">Sign out</button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$others): ?><p class="mt-1 mb-0 border-t border-line pt-4 text-tiny text-muted">You are not signed in anywhere else.</p><?php endif; ?>
</section>
<dialog id="others-modal" class="modal-box max-w-105" aria-labelledby="om-title">
  <form method="post" action="<?= url('/settings/sessions/revoke-others') ?>"><?= csrf_field() ?>
    <div class="modal-head"><div class="flex-1"><h2 class="m-0 text-base" id="om-title">Sign out other sessions?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">This signs out <?= $others ?> other session<?= $others === 1 ? '' : 's' ?>.</p></div></div>
    <div class="modal-body"><p class="m-0 text-tiny leading-copy text-muted">Other browsers and the Krono desktop app will need to sign in again. This device stays signed in.</p></div>
    <div class="modal-foot"><button type="button" class="btn" data-close>Cancel</button><button type="submit" class="btn btn-danger">Sign out others</button></div>
  </form>
</dialog>
<?php include __DIR__ . '/_end.php'; ?>
