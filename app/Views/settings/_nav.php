<?php /* Settings left menu + page heading. Vars: $tab $entry $menu (groups this person may open). Optional $actions for the heading. */
$heading = $entry[4]; $sub = $entry[5];
include ROOT . '/app/Views/partials/page-heading.php';
?>
<div class="grid grid-cols-[14.5rem_1fr] items-start gap-5 pb-6.5 max-laptop:grid-cols-1">
  <aside class="panel-card p-3 max-laptop:p-2" aria-label="Settings">
    <nav class="flex flex-col gap-5 max-laptop:gap-1.5">
      <?php foreach ($menu as $group => $items): ?>
        <div class="flex flex-col gap-1 max-laptop:flex-row max-laptop:flex-wrap">
          <div class="px-3 pt-1.5 pb-1.5 text-xs font-extrabold max-laptop:hidden"><?= e($group) ?></div>
          <?php foreach ($items as $k => $it): $on = $k === $tab; ?>
            <a href="<?= url($it[3]) ?>" data-guard-link class="flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2.5 text-tiny font-bold no-underline <?= $on ? 'bg-soft text-brand' : 'text-muted hover:bg-raised hover:text-ink' ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= icon($it[1], 15) ?><?= e($it[0]) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="min-w-0">
