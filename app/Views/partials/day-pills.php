<?php /* Seven day toggles. Vars: $pillName (field name, e.g. "days_off[]"), $pillDays (list of selected day keys), $pillId (id prefix) */ ?>
<div class="flex flex-wrap gap-2" role="group">
  <?php foreach (DaysOff::DAYS as $k => $label): ?>
    <label class="day-pill"><input type="checkbox" class="sr-only" name="<?= e($pillName) ?>" value="<?= e($k) ?>" id="<?= e($pillId . '-' . $k) ?>"<?= in_array($k, $pillDays, true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label>
  <?php endforeach; ?>
</div>
