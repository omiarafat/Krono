<?php
/* Read-only list of the access rules the server always enforces. */
include __DIR__ . '/../_nav.php';
$noteTitle = 'Security rules are enforced by the server'; $noteText = 'These rules cannot be switched off from this page. They are shown here so you know how your workspace data is protected.';
include __DIR__ . '/../_note.php';
$pol = [['Role enforcement', 'Every protected server request', 'Required', 'Always on'], ['Manager report scope', 'Assigned employee relationship only', 'Enforced', 'Cannot be changed by a role'], ['PDF export permissions', 'Separate from view access', 'Permission-based', 'Checked by the server'], ['Audit events', 'Role, member and workspace changes', 'Recorded', 'See the Audit Log tab']];
$bounds = [['Manager access', 'Managers can view their own data and directly assigned employees only.', 'Enforced', 'team'], ['PDF export', 'Export permission is checked independently from report viewing permission.', 'Enforced', 'reports'], ['Saved records', 'Changing a role does not delete earlier attendance or time logs.', 'Protected', 'lock']];
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="ap-title">
    <h2 class="m-0 text-base" id="ap-title">Access policies</h2>
    <p class="mt-1 mb-4 text-xs text-muted">Review the rules that protect workspace data.</p>
    <div class="relative overflow-x-auto"><table class="min-w-150">
      <thead><tr><th>Policy</th><th>Status</th><th>Behaviour</th></tr></thead>
      <tbody>
      <?php foreach ($pol as [$t, $s, $st, $b]): ?>
        <tr><td><div class="text-xs font-bold"><?= e($t) ?></div><div class="text-micro text-muted"><?= e($s) ?></div></td><td><span class="pill <?= $st === 'Permission-based' ? 'pill-line' : '' ?>"><?= e($st) ?></span></td><td class="text-tiny"><?= e($b) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="db-title">
    <h2 class="m-0 text-base" id="db-title">Data access boundaries</h2>
    <p class="mt-1 mb-2 text-xs text-muted">Expected access scope for workspace roles.</p>
    <?php foreach ($bounds as [$t, $text, $st, $ic]): ?>
      <div class="flex items-center gap-3.5 border-t border-line py-4 first-of-type:border-t-0">
        <span class="flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon($ic, 18) ?></span>
        <div class="min-w-0 flex-1"><div class="text-xs font-bold"><?= e($t) ?></div><div class="text-tiny leading-copy text-muted"><?= e($text) ?></div></div>
        <span class="pill"><?= e($st) ?></span>
      </div>
    <?php endforeach; ?>
  </section>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
