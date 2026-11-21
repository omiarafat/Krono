<?php /* Vars: $form $errors $bad $mode $eligible $zones */
$heading = 'Create or upgrade workspace'; $sub = 'Start a company workspace while keeping your existing account and personal data.';
include ROOT . '/app/Views/partials/page-heading.php';
$bad = $bad ?? []; $mark = fn($k) => isset($bad[$k]) ? ' aria-invalid="true"' : '';
$canUp = count($eligible) > 0; $up = $mode === 'upgrade' && $canUp;
?>
<section class="panel-card mb-6 max-w-4xl p-5.5 max-sm:p-4" aria-labelledby="cw-title" data-ws-form>
  <div class="mb-4 flex items-start justify-between gap-3"><div><h2 class="m-0 text-base" id="cw-title">Choose how to set up your company</h2><p class="mt-1 mb-0 text-xs text-muted">Both options use your existing Krono account.</p></div><span class="pill">No payment required</span></div>
  <?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/workspaces/new') ?>" novalidate class="flex flex-col gap-4"><?= csrf_field() ?>
    <div class="grid grid-cols-2 gap-3.5 max-sm:grid-cols-1">
      <label class="choice-card"><input class="sr-only" type="radio" name="mode" value="new"<?= $up ? '' : ' checked' ?>><span class="ws-card-icon"><?= icon('plus', 20) ?></span><span><strong class="block text-xs">Create a new company workspace</strong><span class="block text-micro text-muted">Keep your personal workspace separate and start a fresh company workspace.</span></span></label>
      <label class="choice-card<?= $canUp ? '' : ' is-disabled' ?>"><input class="sr-only" type="radio" name="mode" value="upgrade"<?= $up ? ' checked' : '' ?><?= $canUp ? '' : ' disabled' ?>><span class="ws-card-icon"><?= icon('team', 20) ?></span><span><strong class="block text-xs">Upgrade my current workspace</strong><span class="block text-micro text-muted"><?= $canUp ? 'Turn your personal workspace into a company workspace without losing its records.' : 'You do not own a personal workspace that can be upgraded.' ?></span></span></label>
    </div>
    <div data-when="upgrade" class="flex flex-col gap-1"<?= $up ? '' : ' hidden' ?>>
      <label class="field-label" for="c-ws">Workspace to upgrade</label>
      <select class="field-input" id="c-ws" name="workspace_id"<?= $mark('workspace_id') ?>><?php foreach ($eligible as $w): ?><option value="<?= (int)$w['id'] ?>"<?= (int)$form['workspace_id'] === (int)$w['id'] ? ' selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select>
      <p class="m-0 text-micro text-muted">The workspace keeps its ID, projects, tasks and history. Only its type changes.</p>
    </div>
    <div class="grid grid-cols-2 gap-4 max-sm:grid-cols-1">
      <div><label class="field-label" for="c-name">Workspace name <span class="text-danger" data-when="new">*</span><span class="font-normal text-muted" data-when="upgrade" hidden>(optional)</span></label><input class="field-input" id="c-name" name="name" maxlength="120" value="<?= e($form['name']) ?>" placeholder="e.g. ABC Technologies"<?= $mark('name') ?>></div>
      <div><label class="field-label" for="c-co">Company name</label><input class="field-input" id="c-co" name="company_name" maxlength="160" value="<?= e($form['company_name']) ?>" placeholder="Legal or trading name (optional)"<?= $mark('company_name') ?>></div>
      <div><label class="field-label" for="c-ind">Industry</label><select class="field-input" id="c-ind" name="industry"<?= $mark('industry') ?>><option value="">Select industry (optional)</option><?php foreach (Workspace::INDUSTRIES as $k => $l): ?><option value="<?= e($k) ?>"<?= $form['industry'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div data-when="new"><label class="field-label" for="c-cc">Country</label><select class="field-input" id="c-cc" name="country"<?= $mark('country') ?>><option value="">Not set</option><?php foreach (Workspace::COUNTRIES as $c => $n): ?><option value="<?= e($c) ?>"<?= $form['country'] === $c ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></div>
    </div>
    <div data-when="new"><label class="field-label" for="c-tz">Time zone</label><select class="field-input" id="c-tz" name="timezone" data-search="on"<?= $mark('timezone') ?>><?php foreach ($zones as $id => $label): ?><option value="<?= e($id) ?>"<?= $form['timezone'] === $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><p class="mt-1.5 mb-0 text-micro text-muted">Can be changed later in Workspace Settings.</p></div>
    <div class="flex items-center justify-between gap-3 border-t border-line pt-4 max-sm:flex-col-reverse max-sm:items-stretch"><a class="btn no-underline" href="<?= url('/workspaces') ?>">Cancel</a><button class="btn btn-primary" type="submit">Continue</button></div>
  </form>
</section>
<script src="<?= asset('js/krono-workspace.js') ?>" defer></script>
