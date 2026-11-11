<?php
/* "Add Task" modal on Project Details. Needs: $p (project), $s (summary: people), $taskForm (form sent back with errors, or null). */
$tv = $taskForm['values'] ?? ['title' => '', 'assignee_id' => 0, 'priority' => 'normal', 'due_date' => '', 'status' => 'todo']; $te = $taskForm['errors'] ?? [];
$defaults = ['title' => '', 'assignee_id' => '', 'priority' => 'normal', 'due_date' => '', 'status' => 'todo'];
?>
<dialog id="task-modal" class="modal-box" data-form-modal aria-labelledby="tm-title"<?= $taskForm ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/project/tasks') ?>" novalidate data-required="title" data-defaults="<?= e(json_encode($defaults)) ?>">
    <?= csrf_field() ?><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>">
    <div class="modal-head">
      <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('tasks', 20) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="tm-title">Create task</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">Choose a project and define the task details.</p></div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($taskForm['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> Your changes were not saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <div><span class="field-label">Project</span><div class="flex h-10 items-center rounded-control border border-line bg-page px-3 text-xs text-muted"><?= e($p['name']) ?></div></div>
      <div><label class="field-label" for="tf-title">Task title <span class="text-danger">*</span></label><input class="field-input" id="tf-title" name="title" maxlength="200" placeholder="e.g. Design homepage" value="<?= e($tv['title']) ?>" <?= isset($te['title']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($te['title'])): ?><p class="field-error"><?= e($te['title']) ?></p><?php endif; ?></div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="tf-assignee">Assignee</label>
          <select class="field-input" id="tf-assignee" name="assignee_id" <?= isset($te['assignee_id']) ? 'aria-invalid="true"' : '' ?>><option value="">Unassigned</option><?php foreach ($s['people'] as $uid => $name): ?><option value="<?= (int)$uid ?>"<?= (int)$tv['assignee_id'] === (int)$uid ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?php if (isset($te['assignee_id'])): ?><p class="field-error"><?= e($te['assignee_id']) ?></p><?php endif; ?></div>
        <div><label class="field-label" for="tf-priority">Priority</label><select class="field-input" id="tf-priority" name="priority"><?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= Ui::dot('priority', $k) ?>"<?= $tv['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="tf-due">Due date</label><input class="field-input" type="date" id="tf-due" name="due_date" placeholder="No due date" value="<?= e($tv['due_date']) ?>" <?= isset($te['due_date']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($te['due_date'])): ?><p class="field-error"><?= e($te['due_date']) ?></p><?php endif; ?></div>
        <div><label class="field-label" for="tf-status">Status</label><select class="field-input" id="tf-status" name="status"><?php foreach (Task::STATUS as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= Ui::dot('task', $k) ?>"<?= $tv['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit data-new="Create task">Create task</button></div>
  </form>
</dialog>
