<?php /* Plans & Billing (read-only). Vars: $ws $plan. Payments are not enabled: no price entry, no card or payment fields anywhere. */
include __DIR__ . '/../_nav.php';
$free = ['Personal or company workspace', 'Core projects and tasks', 'Basic team management', 'Time tracking and basic reports'];
$later = ['Advanced roles and controls', 'Expanded reports and exports', 'Higher team and workspace limits', 'Priority administration features'];
$billing = [['Billing workspace', $ws['name']], ['Current subscription', $plan['label'] . ' · ' . $plan['state']], ['Payment method', 'Not added'], ['Invoices', 'None']];
$tick = fn(string $t) => '<li class="flex items-center gap-2.5 text-tiny"><span class="text-brand">' . icon('check', 14) . '</span>' . e($t) . '</li>';
?>
<div class="flex flex-col gap-4.5">
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="pl-title">
    <div class="mb-4 flex items-start justify-between gap-3"><div><h2 class="m-0 text-base" id="pl-title">Workspace plan</h2><p class="mt-1 mb-0 text-xs text-muted">Plans are attached to each workspace, not to your global user account.</p></div><span class="pill">No payment setup</span></div>
    <div class="grid grid-cols-2 gap-3.5 max-laptop:grid-cols-1">
      <div class="flex flex-col rounded-panel border-2 border-brand bg-soft/60 p-5">
        <div class="flex items-center justify-between"><strong class="text-sm">Free</strong><span class="pill">Current plan</span></div>
        <div class="mt-4 text-stat font-extrabold">৳0 <span class="text-tiny font-normal text-muted">/ forever</span></div>
        <p class="mt-3 mb-4 text-tiny text-muted">Start with personal work or a small team.</p>
        <ul class="m-0 flex list-none flex-col gap-2.5 p-0"><?php foreach ($free as $t) echo $tick($t); ?></ul>
        <button type="button" class="btn btn-primary mt-auto w-full" disabled>Current plan</button>
      </div>
      <div class="flex flex-col rounded-panel border border-line bg-surface p-5">
        <div class="flex items-center justify-between"><strong class="text-sm">Paid plans</strong><span class="pill pill-line">Coming later</span></div>
        <div class="mt-4 text-stat font-extrabold">Later</div>
        <p class="mt-3 mb-4 text-tiny text-muted">Pro, Business and Enterprise options may be introduced after usage limits and support requirements are defined.</p>
        <ul class="m-0 flex list-none flex-col gap-2.5 p-0"><?php foreach ($later as $t) echo $tick($t); ?></ul>
        <button type="button" class="btn mt-auto w-full" disabled>Payment is not enabled</button>
      </div>
    </div>
  </section>
  <section class="panel-card p-5.5 max-sm:p-4" aria-labelledby="bs-title">
    <h2 class="m-0 text-base" id="bs-title">Billing status</h2><p class="mt-1 mb-4 text-xs text-muted">No payment provider or card details are needed in the current release.</p>
    <dl class="m-0 flex flex-col"><?php foreach ($billing as [$k, $v]): ?><div class="flex justify-between gap-4 border-t border-line py-3 text-xs first:border-t-0"><dt class="text-muted"><?= e($k) ?></dt><dd class="m-0 truncate text-right font-bold"><?= e($v) ?></dd></div><?php endforeach; ?></dl>
    <p class="auth-notice mt-3 mb-0">Paid plans will arrive with a payment provider and verified payment notifications. Krono will not collect or store card details itself.</p>
  </section>
</div>
<?php include __DIR__ . '/../_end.php'; ?>
