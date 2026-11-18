<?php /* Settings left menu + page heading. Vars: $tab $entry $menu (groups this person may open). Optional $actions for the heading. */
$heading = $entry[4]; $sub = $entry[5];
include ROOT . '/app/Views/partials/page-heading.php';
?>
<script>try{if(localStorage.getItem('krono-settings-menu')==='collapsed')document.documentElement.dataset.settingsMenu='collapsed'}catch(x){}</script>
<div class="grid grid-cols-[14.5rem_1fr] items-start gap-5 pb-6.5 laptop:menu-collapsed:grid-cols-[4.5rem_1fr] max-laptop:grid-cols-1">
  <aside class="panel-card p-3 max-laptop:p-2 laptop:menu-collapsed:px-2" aria-label="Settings">
    <button type="button" data-menu-toggle class="icon-button mb-1.5 ml-auto flex max-laptop:hidden laptop:menu-collapsed:mx-auto" aria-label="Collapse settings menu" aria-expanded="true"><span class="inline-flex transition-transform menu-collapsed:rotate-180"><?= icon('chevron-left', 18) ?></span></button>
    <nav class="flex flex-col gap-3 max-laptop:gap-1.5">
      <?php foreach ($menu as $group => $items): ?>
        <div class="flex flex-col gap-1 max-laptop:flex-row max-laptop:flex-wrap laptop:menu-collapsed:border-t laptop:menu-collapsed:border-line laptop:menu-collapsed:pt-3 laptop:menu-collapsed:first:border-t-0 laptop:menu-collapsed:first:pt-0">
          <div class="px-3 pb-1 text-xs font-extrabold max-laptop:hidden menu-collapsed:hidden"><?= e($group) ?></div>
          <?php foreach ($items as $k => $it): $on = $k === $tab; ?>
            <a href="<?= url($it[3]) ?>" data-guard-link class="group/nav relative flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2.5 text-tiny font-bold no-underline laptop:menu-collapsed:justify-center laptop:menu-collapsed:px-0 <?= $on ? 'bg-soft text-brand' : 'text-muted hover:bg-raised hover:text-ink' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
              <?= icon($it[1], 16) ?><span class="laptop:menu-collapsed:hidden"><?= e($it[0]) ?></span>
              <span role="tooltip" class="menu-tip max-laptop:hidden group-hover/nav:opacity-100 group-focus-visible/nav:opacity-100"><?= e($it[0]) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="min-w-0">
