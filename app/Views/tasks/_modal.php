<?php
/* Add / Edit task modal, shared by the Tasks page and Project Details.
   Vars: $taskProjects (id => [name, tasks, people[id => name]]), $taskFixed (project id when the page is one project, else 0),
         $taskForm (form sent back with errors, or null), $back (where to return after saving). */
$tv = $taskForm['values'] ?? ['project_id' => $taskFixed ?: '', 'title' => '', 'assignee_id' => 0, 'priority' => 'normal', 'due_date' => '', 'status' => 'todo']; $te = $taskForm['errors'] ?? [];
$editing = !empty($taskForm['id']); $curProject = (int)($tv['project_id'] ?? 0);
$defaults = ['id' => '', 'project_id' => $taskFixed ?: (string)($taskDefaultProject ?? ''), 'title' => '', 'assignee_id' => '', 'priority' => 'normal', 'due_date' => '', 'status' => 'todo'];
$people = $taskProjects[$curProject]['people'] ?? [];
$err = fn(string $k) => isset($te[$k]) ? '<p class="field-error">' . e($te[$k]) . '</p>' : '';
$bad = fn(string $k) => isset($te[$k]) ? ' aria-invalid="true"' : '';
$locked = fn(string $name) => '<div class="flex h-10 items-center rounded-control border border-line bg-page px-3 text-xs text-muted" data-fill-text="project_name">' . e($name) . '</div>';
?>
<dialog id="task-modal" class="modal-box" data-form-modal aria-labelledby="tm-title"<?= $taskForm ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/tasks/save') ?>" novalidate data-required="title<?= $taskFixed ? '' : ',project_id' ?>" data-defaults="<?= e(json_encode($defaults)) ?>" data-people="<?= e(json_encode($taskProjects ? array_map(fn($p) => $p['people'], $taskProjects) : new stdClass)) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $editing ? (int)$taskForm['id'] : '' ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-head">
      <div class="flex h-10.5 w-10.5 shrink-0 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('tasks', 20) ?></div>
      <div class="flex-1">
        <h2 class="m-0 text-base" id="tm-title" data-m-title data-new="Create task" data-edit="Edit task"><?= $editing ? 'Edit task' : 'Create task' ?></h2>
        <p class="mt-1 mb-0 text-xs leading-copy text-muted" data-m-sub data-new="Choose a project and define the task details." data-edit="Update task information. Status and time tracking are separate actions."><?= $editing ? 'Update task information. Status and time tracking are separate actions.' : 'Choose a project and define the task details.' ?></p>
      </div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($taskForm['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> Your changes were not saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <?php if ($taskFixed): ?>
        <input type="hidden" name="project_id" value="<?= (int)$taskFixed ?>">
        <div><span class="field-label">Project</span><?= $locked($taskProjects[$taskFixed]['name'] ?? '') ?></div>
      <?php else: ?>
        <div data-only="new"<?= $editing ? ' hidden' : '' ?>><label class="field-label" for="tf-project">Project <span class="text-danger">*</span></label>
          <select class="field-input" id="tf-project" name="project_id" data-dependent<?= $bad('project_id') ?>>
            <option value="">Select a project</option>
            <?php foreach ($taskProjects as $pid => $pr): ?><option value="<?= (int)$pid ?>" data-meta="<?= (int)$pr['tasks'] ?> task<?= $pr['tasks'] === 1 ? '' : 's' ?>"<?= $curProject === (int)$pid ? ' selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?>
          </select><?= $err('project_id') ?>
          <p class="mt-1.5 mb-0 text-micro text-muted">Only projects you can access are listed.</p></div>
        <div data-only="edit"<?= $editing ? '' : ' hidden' ?>><span class="field-label">Project</span><?= $locked($taskProjects[$curProject]['name'] ?? '') ?></div>
      <?php endif; ?>
      <div><label class="field-label" for="tf-title">Task title <span class="text-danger">*</span></label><input class="field-input" id="tf-title" name="title" maxlength="200" placeholder="e.g. Design homepage" value="<?= e($tv['title']) ?>"<?= $bad('title') ?>><?= $err('title') ?></div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="tf-assignee">Assignee</label>
          <select class="field-input" id="tf-assignee" name="assignee_id"<?= $bad('assignee_id') ?>><option value="">Unassigned</option><?php foreach ($people as $uid => $name): ?><option value="<?= (int)$uid ?>"<?= (int)$tv['assignee_id'] === (int)$uid ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><?= $err('assignee_id') ?></div>
        <div><label class="field-label" for="tf-priority">Priority</label><select class="field-input" id="tf-priority" name="priority"><?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= Ui::dot('priority', $k) ?>"<?= $tv['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="tf-due">Due date</label><input class="field-input" type="date" id="tf-due" name="due_date" placeholder="No due date" value="<?= e($tv['due_date']) ?>"<?= $bad('due_date') ?>><?= $err('due_date') ?></div>
        <div><label class="field-label" for="tf-status">Status</label><select class="field-input" id="tf-status" name="status"<?= $bad('status') ?>><?php foreach (Task::STATUS as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= Ui::dot('task', $k) ?>"<?= $tv['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?= $err('status') ?></div>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary" data-m-submit data-new="Create task" data-edit="Save changes"><?= $editing ? 'Save changes' : 'Create task' ?></button></div>
  </form>
</dialog>
<?php if (!$taskFixed): ?>
<script>
/* the Assignee list follows the chosen project */
(function () {
  var f = document.querySelector('#task-modal form'), pr = f.elements.project_id, as = f.elements.assignee_id, map = JSON.parse(f.dataset.people);
  pr.addEventListener('change', function () {
    var cur = as.value, ppl = map[pr.value] || {}; as.innerHTML = '';
    as.add(new Option('Unassigned', ''));
    Object.keys(ppl).forEach(function (id) { as.add(new Option(ppl[id], id)); });
    as.value = ppl[cur] ? cur : ''; window.KronoUI && window.KronoUI.sync(f);
  });
})();
</script>
<?php endif; ?>
