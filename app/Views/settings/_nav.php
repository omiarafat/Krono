<?php /* Settings left menu + page heading. Vars: $tab $entry $menu (groups this person may open). Optional $actions for the heading. */
$heading = $entry[4]; $sub = $entry[5];
include ROOT . '/app/Views/partials/page-heading.php';
?>
<script>try{if(localStorage.getItem('krono-settings-menu')==='collapsed')document.documentElement.dataset.settingsMenu='collapsed'}catch(x){}</script>
<div class="grid grid-cols-[14.5rem_1fr] items-start gap-5 pb-6.5 lg:menu-collapsed:grid-cols-[3rem_1fr] max-lg:grid-cols-1">
  <aside class="panel-card p-3 max-lg:p-2 relative lg:menu-collapsed:px-2" aria-label="Settings">
    <button type="button" data-menu-toggle class="icon-button absolute top-7.5 -right-3 z-1 flex max-lg:hidden lg:menu-collapsed:mx-auto" aria-label="Collapse settings menu" aria-expanded="true">
      <span class="inline-flex transition-transform menu-collapsed:rotate-180"><?= icon('chevron-left', 18) ?></span>
    </button>
    <nav class="flex flex-col gap-3 max-lg:gap-1.5">
      <?php foreach ($menu as $group => $items): ?>
        <div class="flex flex-col gap-1 max-lg:flex-row max-lg:flex-wrap lg:menu-collapsed:border-t lg:menu-collapsed:border-line lg:menu-collapsed:pt-3 lg:menu-collapsed:first:border-t-0 lg:menu-collapsed:first:pt-0">
          <div class="px-3 pb-1 text-xs font-extrabold max-lg:hidden menu-collapsed:hidden"><?= e($group) ?></div>
          <?php foreach ($items as $k => $it): $on = $k === $tab; ?>
            <a href="<?= url($it[3]) ?>" data-guard-link class="group/nav relative flex items-center gap-2.5 whitespace-nowrap rounded-lg px-3 py-2.5 text-tiny font-bold no-underline lg:menu-collapsed:justify-center lg:menu-collapsed:px-0 <?= $on ? 'bg-soft text-brand' : 'text-muted hover:bg-raised hover:text-ink' ?>"<?= $on ? ' aria-current="page"' : '' ?>>
              <?= icon($it[1], 16) ?><span class="lg:menu-collapsed:hidden"><?= e($it[0]) ?></span>
              <span role="tooltip" class="menu-tip max-lg:hidden group-hover/nav:opacity-100 group-focus-visible/nav:opacity-100"><?= e($it[0]) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="min-w-0">
