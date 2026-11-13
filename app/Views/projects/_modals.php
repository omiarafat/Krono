<?php
/* Shared by Projects and Project Details. Needs: $back (page to return to), $form (form sent back with errors, or null). Only shown to people who may manage projects. */
$fv = $form['values'] ?? ['name' => '', 'description' => '', 'start_date' => '', 'due_date' => '', 'status' => 'planning', 'priority' => 'normal']; $fe = $form['errors'] ?? [];
$editing = !empty($form['id']);
$defaults = ['id' => '', 'name' => '', 'description' => '', 'start_date' => '', 'due_date' => '', 'status' => 'planning', 'priority' => 'normal'];
?>
<!-- Create / Edit project -->
<dialog id="project-modal" class="modal-box" data-form-modal aria-labelledby="pm-title"<?= $form ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/projects/save') ?>" novalidate data-required="name,start_date" data-date-order="start_date,due_date" data-defaults="<?= e(json_encode($defaults)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0) ?: '' ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-head">
      <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('projects', 20) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="pm-title" data-m-title data-new="Create project" data-edit="Edit project"><?= $editing ? 'Edit project' : 'Create project' ?></h2>
        <p class="mt-1 mb-0 text-xs leading-copy text-muted" data-m-sub data-new="Set up a project to organize related tasks." data-edit="Update the details of this project."><?= $editing ? 'Update the details of this project.' : 'Set up a project to organize related tasks.' ?></p></div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($form['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> Your changes were not saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <div><label class="field-label" for="pf-name">Project name <span class="text-danger">*</span></label><input class="field-input" id="pf-name" name="name" maxlength="150" value="<?= e($fv['name']) ?>" placeholder="e.g. Website Redesign" <?= isset($fe['name']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['name'])): ?><p class="field-error"><?= e($fe['name']) ?></p><?php endif; ?></div>
      <div><label class="field-label" for="pf-desc">Description</label><textarea class="field-input" id="pf-desc" name="description" maxlength="2000" placeholder="What is this project about?" <?= isset($fe['description']) ? 'aria-invalid="true"' : '' ?>><?= e($fv['description']) ?></textarea><?php if (isset($fe['description'])): ?><p class="field-error"><?= e($fe['description']) ?></p><?php endif; ?></div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="pf-start">Start date <span class="text-danger">*</span></label><input class="field-input" type="date" id="pf-start" name="start_date" data-required placeholder="Select start date" value="<?= e($fv['start_date']) ?>" <?= isset($fe['start_date']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['start_date'])): ?><p class="field-error"><?= e($fe['start_date']) ?></p><?php endif; ?></div>
        <div><label class="field-label" for="pf-due">Due date</label><input class="field-input" type="date" id="pf-due" name="due_date" placeholder="No due date" value="<?= e($fv['due_date']) ?>" <?= isset($fe['due_date']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['due_date'])): ?><p class="field-error"><?= e($fe['due_date']) ?></p><?php endif; ?></div>
      </div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="pf-status">Project status</label><select class="field-input" id="pf-status" name="status"><?php foreach (Project::STATUS as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('project', $k) ?>"<?= $fv['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="field-label" for="pf-priority">Priority</label><select class="field-input" id="pf-priority" name="priority"><?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('priority', $k) ?>"<?= $fv['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn-primary" data-m-submit data-new="Create project" data-edit="Save changes"><?= $editing ? 'Save changes' : 'Create project' ?></button>
    </div>
  </form>
</dialog>

<!-- Archive confirmation -->
<dialog id="archive-modal" class="modal-box" aria-labelledby="am-title">
  <form method="post" action="<?= url('/projects/archive') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <div class="modal-head">
      <div class="flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-xl bg-tag-amber/15 text-tag-amber"><?= icon('archive', 18) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="am-title">Archive this project?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted"><strong data-am-name></strong> will be hidden from active project lists.</p></div>
    </div>
    <div class="modal-body">
      <div class="rounded-xl border border-tag-amber/50 bg-tag-amber/8 px-3.5 py-3">
        <strong class="text-tiny">Project has linked work</strong>
        <p class="mt-1 mb-0 text-tiny leading-copy text-muted"><span data-am-counts></span> Archiving keeps existing history available to authorized users.</p>
      </div>
      <p class="mt-3 mb-0 text-tiny text-muted">This does not erase attendance records or saved time logs.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Archive project</button></div>
  </form>
</dialog>
