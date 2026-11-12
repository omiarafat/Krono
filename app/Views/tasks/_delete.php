<!-- Delete task. Without saved logs: asks, then deletes. With saved logs: explains why it is kept (never silently deletes history). -->
<dialog id="delete-task-modal" class="modal-box" aria-labelledby="dt-title">
  <form method="post" action="<?= url('/tasks/delete') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value=""><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-head">
      <div class="flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-xl bg-danger/12 text-danger"><?= icon('alert', 18) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="dt-title">Delete this task?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">Confirm carefully before removing <strong data-dt-name></strong>.</p></div>
    </div>
    <div class="modal-body">
      <div class="rounded-xl border border-danger/40 bg-danger/8 px-3.5 py-3" data-dt-free>
        <strong class="text-tiny">This cannot be undone</strong>
        <p class="mt-1 mb-0 text-tiny leading-copy text-muted">The task has no saved time logs. Deleting it removes it from the project.</p>
      </div>
      <div class="rounded-xl border border-danger/40 bg-danger/8 px-3.5 py-3" data-dt-linked hidden>
        <strong class="text-tiny">Saved records are linked</strong>
        <p class="mt-1 mb-0 text-tiny leading-copy text-muted">This task has <span data-dt-counts></span>. Saved history is never silently deleted, so this task is kept.</p>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn-danger" data-dt-free>Delete task</button>
      <a class="btn btn-primary" data-dt-linked data-dt-link hidden href="#">Review linked records</a></div>
  </form>
</dialog>
