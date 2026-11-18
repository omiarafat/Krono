<?php
/* Teams modals (Owner/Admin only). Vars: $roles $managers $eligible $inviteForm $back */
$iv = $inviteForm['values'] ?? ['email' => '', 'role' => 'member', 'manager_id' => 0]; $ie = $inviteForm['errors'] ?? [];
$err = fn(string $k) => isset($ie[$k]) ? '<p class="field-error">' . e($ie[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($ie[$k]) ? ' aria-invalid="true"' : '';
$mgrJson = e(json_encode($managers, JSON_UNESCAPED_UNICODE));
$back = $back ?? '/teams';
$head = fn(string $id, string $ic, string $title, string $sub) => '<div class="modal-head"><div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand">' . icon($ic, 20) . '</div><div class="flex-1"><h2 class="m-0 text-base" id="' . $id . '">' . $title . '</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">' . $sub . '</p></div><button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close">' . icon('close', 16) . '</button></div>';
$reports = '<div data-reports-wrap hidden class="mt-3.5"><label class="field-label">Direct reports <span class="text-danger">*</span></label><select class="field-input" name="reports" disabled></select><p class="mt-1.5 mb-0 text-micro text-muted">This manager has direct reports. Choose who they report to next.</p></div>';
?>
<dialog id="invite-modal" class="modal-box" data-form-modal aria-labelledby="im-title"<?= $inviteForm ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/teams/invite') ?>" novalidate data-required="email" data-defaults="<?= e(json_encode(['email' => '', 'role' => 'member', 'manager_id' => ''])) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
    <?= $head('im-title', 'mail', 'Invite member', 'They get an email with a link that works for ' . Team::INVITE_DAYS . ' days.') ?>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($inviteForm['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not send the invitation.</strong> Nothing was saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <div><label class="field-label" for="inv-email">Email address <span class="text-danger">*</span></label><input class="field-input" id="inv-email" type="email" name="email" maxlength="190" placeholder="name@company.com" autocomplete="off" value="<?= e($iv['email']) ?>"<?= $bad('email') ?>><?= $err('email') ?></div>
      <div><label class="field-label" for="inv-role">Role</label>
        <select class="field-input" id="inv-role" name="role"<?= $bad('role') ?>><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" data-base="<?= e(Team::baseOf($ws, $k)) ?>"<?= $iv['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('role') ?></div>
      <div data-show-if="role:member,viewer"><label class="field-label" for="inv-manager">Direct manager</label>
        <select class="field-input" id="inv-manager" name="manager_id"<?= $bad('manager_id') ?>><option value="">No manager</option><?php foreach ($managers as $id => $n): ?><option value="<?= (int)$id ?>"<?= (int)$iv['manager_id'] === (int)$id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><?= $err('manager_id') ?>
        <p class="mt-1.5 mb-0 text-micro text-muted">Optional. A manager can see attendance and reports for direct reports only.</p></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit>Send invitation</button></div>
  </form>
</dialog>

<dialog id="role-modal" class="modal-box" data-form-modal aria-labelledby="rm-title">
  <form method="post" action="<?= url('/teams/role') ?>" novalidate data-defaults="<?= e(json_encode(['user_id' => '', 'role' => 'member'])) ?>" data-managers="<?= $mgrJson ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="user_id" value="">
    <?= $head('rm-title', 'shield', 'Change role', 'Update what <strong data-fill-text="name">this member</strong> can do in the workspace.') ?>
    <div class="modal-body">
      <label class="field-label" for="rm-role">New role</label>
      <select class="field-input" id="rm-role" name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>" data-base="<?= e(Team::baseOf($ws, $k)) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <p class="mt-1.5 mb-0 text-micro text-muted">Members and Viewers can be given a direct manager. Other roles have none.</p>
      <?= $reports ?>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit>Save role</button></div>
  </form>
</dialog>

<dialog id="assign-modal" class="modal-box" data-form-modal aria-labelledby="am2-title">
  <form method="post" action="<?= url('/teams/manager') ?>" novalidate data-required="user_id,manager_id" data-defaults="<?= e(json_encode(['user_id' => '', 'manager_id' => ''])) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
    <?= $head('am2-title', 'team', 'Assign member', 'Choose a manager and the member who reports to them.') ?>
    <div class="modal-body flex flex-col gap-3.5">
      <div><label class="field-label" for="as-manager">Manager <span class="text-danger">*</span></label>
        <select class="field-input" id="as-manager" name="manager_id"><option value="">Select a manager</option><option value="0">No manager (remove assignment)</option><?php foreach ($managers as $id => $n): ?><option value="<?= (int)$id ?>"><?= e($n) ?></option><?php endforeach; ?></select></div>
      <div><label class="field-label" for="as-user">Member <span class="text-danger">*</span></label>
        <select class="field-input" id="as-user" name="user_id"><option value="">Select a member</option><?php foreach ($eligible as $id => $p): ?><option value="<?= (int)$id ?>" data-meta="<?= $p['manager'] !== '' ? 'Reports to ' . e($p['manager']) : 'Unassigned' ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
        <p class="mt-1.5 mb-0 text-micro text-muted">Only active Members and Viewers can be assigned. Moving someone updates their access scope from now on.</p></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit>Save assignment</button></div>
  </form>
</dialog>

<dialog id="deactivate-modal" class="modal-box max-w-105" data-form-modal aria-labelledby="dm2-title">
  <form method="post" action="<?= url('/teams/status') ?>" novalidate data-defaults="<?= e(json_encode(['user_id' => ''])) ?>" data-managers="<?= $mgrJson ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="user_id" value=""><input type="hidden" name="do" value="deactivate">
    <?= $head('dm2-title', 'alert', 'Deactivate member?', '<strong data-fill-text="name">This member</strong> will lose access to the workspace.') ?>
    <div class="modal-body">
      <p class="m-0 text-tiny leading-copy text-muted">Their attendance, time logs, tasks and projects stay as they are. You can reactivate them at any time.</p>
      <?= $reports ?>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger" data-m-submit>Deactivate</button></div>
  </form>
</dialog>

<dialog id="reactivate-modal" class="modal-box max-w-105" data-form-modal aria-labelledby="ra-title">
  <form method="post" action="<?= url('/teams/status') ?>" novalidate data-defaults="<?= e(json_encode(['user_id' => ''])) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="user_id" value=""><input type="hidden" name="do" value="reactivate">
    <?= $head('ra-title', 'check', 'Reactivate member?', '<strong data-fill-text="name">This member</strong> will get access again.') ?>
    <div class="modal-body"><p class="m-0 text-tiny leading-copy text-muted">They return with their previous role. Their direct manager is not restored, so assign one again if needed.</p></div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit>Reactivate</button></div>
  </form>
</dialog>

<dialog id="cancel-invite-modal" class="modal-box max-w-105" data-form-modal aria-labelledby="ci-title">
  <form method="post" action="<?= url('/teams/invitation') ?>" novalidate data-defaults="<?= e(json_encode(['id' => ''])) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="id" value=""><input type="hidden" name="do" value="cancel">
    <?= $head('ci-title', 'alert', 'Cancel invitation?', 'The link sent to <strong data-fill-text="name">this address</strong> will stop working.') ?>
    <div class="modal-body"><p class="m-0 text-tiny leading-copy text-muted">You can invite the same address again later.</p></div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Keep invitation</button><button type="submit" class="btn btn-danger" data-m-submit>Cancel invitation</button></div>
  </form>
</dialog>

<form method="post" action="<?= url('/teams/invitation') ?>" id="resend-form" hidden><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="id" value=""><input type="hidden" name="do" value="resend"></form>
