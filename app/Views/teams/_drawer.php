<?php /* Member details panel, filled by krono-team.js from the row's data-member. */
$notes = ['owner' => 'Full control of the workspace.', 'admin' => 'Manages members, projects and settings.', 'manager' => 'Sees attendance and reports for direct reports only.', 'member' => 'Tracks own time, attendance and assigned tasks.', 'viewer' => 'Read-only access to assigned work.'];
$row = fn(string $k, string $l) => '<div class="flex items-start justify-between gap-4 border-b border-line py-3 text-xs last:border-0"><span class="text-muted">' . $l . '</span><span class="text-right font-semibold" data-d="' . $k . '">—</span></div>';
?>
<dialog id="member-drawer" class="drawer" aria-labelledby="dr-name" data-notes="<?= e(json_encode($notes)) ?>">
  <div class="flex h-full flex-col">
    <div class="flex items-start gap-3 border-b border-line px-5.5 py-4.5">
      <b class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-soft text-sm font-bold text-brand" data-d="initials"></b>
      <div class="min-w-0 flex-1"><h2 class="m-0 truncate text-base" id="dr-name" data-d="name"></h2><div class="truncate text-tiny text-muted" data-d="email"></div><div class="mt-2" data-d="status"></div></div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-drawer-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="flex-1 overflow-y-auto px-5.5 py-3">
      <?= $row('role', 'Role') ?><?= $row('daysoff', 'Weekly days off') ?><?= $row('manager', 'Reports to') ?><?= $row('reports', 'Direct reports') ?><?= $row('joined', 'Joined') ?><?= $row('last', 'Last active') ?><?= $row('extra', 'Invitation') ?>
      <div class="mt-4 hidden" data-d-requests>
        <h3 class="m-0 mb-2 text-xs">Requests waiting for you</h3>
        <div class="flex flex-col gap-2.5" data-d-request-list></div>
      </div>
      <p class="mt-4 mb-0 rounded-xl border border-line bg-page px-3.5 py-3 text-tiny leading-copy text-muted" data-d="note"></p>
    </div>
    <div class="flex flex-wrap justify-end gap-2.25 border-t border-line bg-page px-5.5 py-3.5" data-d-actions></div>
  </div>
  <template id="request-form-tpl"><form method="post" action="<?= url('/requests/decide') ?>" class="m-0 flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value=""><input type="hidden" name="do" value=""><input type="hidden" name="back" value="/teams"></form></template>
</dialog>
