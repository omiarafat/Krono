  </div>
</div>
<dialog id="leave-modal" class="modal-box max-w-105" aria-labelledby="lm-title">
  <div class="modal-head"><div class="flex-1"><h2 class="m-0 text-base" id="lm-title">Leave without saving?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">Your changes have not been saved.</p></div></div>
  <div class="modal-body"><p class="m-0 text-tiny leading-copy text-muted">If you leave now, the changes you made in this form will be lost. Previously saved information will not be affected.</p></div>
  <div class="modal-foot"><button type="button" class="btn" data-leave-keep>Keep editing</button><button type="button" class="btn btn-danger" data-leave-yes>Discard changes</button></div>
</dialog>
<?php include ROOT . '/app/Views/partials/toast.php'; ?>
<script src="<?= asset('js/krono-settings.js') ?>" defer></script>
