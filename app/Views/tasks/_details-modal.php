<?php
/* "Edit description" modal on Task Details: description, notes (one per line) and the estimate.
   Vars: $t (task) $detailForm (form sent back with errors, or null) $back */
$dv = $detailForm['values'] ?? ['description' => (string)$t['description'], 'notes' => (string)($t['notes'] ?? ''), 'est_h' => $t['estimate_minutes'] ? (string)intdiv((int)$t['estimate_minutes'], 60) : '', 'est_m' => $t['estimate_minutes'] ? (string)((int)$t['estimate_minutes'] % 60) : ''];
$de = $detailForm['errors'] ?? [];
$derr = fn(string $k) => isset($de[$k]) ? '<p class="field-error">' . e($de[$k]) . '</p>' : '';
$dbad = fn(string $k) => isset($de[$k]) ? ' aria-invalid="true"' : '';
$fill = ['id' => (int)$t['id'], 'description' => (string)$t['description'], 'notes' => (string)($t['notes'] ?? ''), 'est_h' => $t['estimate_minutes'] ? (string)intdiv((int)$t['estimate_minutes'], 60) : '', 'est_m' => $t['estimate_minutes'] ? (string)((int)$t['estimate_minutes'] % 60) : ''];
?>
<dialog id="details-modal" class="modal-box" data-form-modal aria-labelledby="dm-title"<?= $detailForm ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/tasks/details') ?>" novalidate data-defaults="<?= e(json_encode($fill)) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-head">
      <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('file', 20) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="dm-title">Edit description</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">Describe the work, add notes and set the time estimate.</p></div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($detailForm['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3.5 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> Your changes were not saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <div><label class="field-label" for="dm-desc">Description</label><textarea class="field-input h-28 resize-y py-2.5" id="dm-desc" name="description" maxlength="2000" placeholder="What needs to be done?"<?= $dbad('description') ?>><?= e($dv['description']) ?></textarea><?= $derr('description') ?></div>
      <div><label class="field-label" for="dm-notes">Notes</label><textarea class="field-input h-24 resize-y py-2.5" id="dm-notes" name="notes" placeholder="One note per line"<?= $dbad('notes') ?>><?= e($dv['notes']) ?></textarea><?= $derr('notes') ?><p class="mt-1.5 mb-0 text-micro text-muted">Each line becomes one bullet.</p></div>
      <div><span class="field-label">Estimated time</span>
        <div class="flex items-center gap-2"><input class="field-input w-24" type="number" min="0" max="999" inputmode="numeric" name="est_h" value="<?= e($dv['est_h']) ?>" placeholder="0" aria-label="Estimated hours"<?= $dbad('estimate') ?>><span class="text-xs text-muted">h</span>
          <input class="field-input w-24" type="number" min="0" max="59" inputmode="numeric" name="est_m" value="<?= e($dv['est_m']) ?>" placeholder="0" aria-label="Estimated minutes"<?= $dbad('estimate') ?>><span class="text-xs text-muted">min</span></div><?= $derr('estimate') ?></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit>Save changes</button></div>
  </form>
</dialog>
