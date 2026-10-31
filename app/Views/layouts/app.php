<?php
// Signed-in shell (Design A). $content = the page, $nav = active menu key, $crumb = page name in the breadcrumb.
$nav = $nav ?? 'dashboard'; $crumb = $crumb ?? ($title ?? 'Dashboard');
$uname = (string)($user['name'] ?? ''); $uemail = (string)($user['email'] ?? '');
$initial = mb_strtoupper(mb_substr(trim($uname) !== '' ? trim($uname) : $uemail, 0, 1));
$items = [ // key => [label, icon, path]
    'dashboard' => ['Dashboard', 'dashboard', '/dashboard'], 'projects' => ['Projects', 'projects', '/projects'], 'tasks' => ['Tasks', 'tasks', '/tasks'],
    'time' => ['Time Tracking', 'time', '/time-tracking'], 'attendance' => ['Attendance', 'attendance', '/attendance'], 'reports' => ['Reports', 'reports', '/reports'],
    'calendar' => ['Calendar', 'calendar', '/calendar'], 'team' => ['Team & Roles', 'team', '/team'], 'settings' => ['Settings', 'settings', '/settings'],
];
$tip = 'pointer-events-none absolute left-full top-1/2 z-50 ml-3 hidden -translate-y-1/2 whitespace-nowrap rounded-lg bg-ink px-2.5 py-1.5 text-[11px] font-semibold text-page opacity-0 shadow-card transition-opacity in-data-[sidebar=collapsed]:block';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Krono') ?> · Krono</title>
<script>try{var d=document.documentElement,t=localStorage.getItem('krono-theme');if(t==='light'||t==='dark')d.dataset.theme=t;var s=localStorage.getItem('krono-sidebar');d.dataset.sidebar=(s==='collapsed'||s==='expanded')?s:(matchMedia('(max-width:1000px)').matches?'collapsed':'expanded')}catch(e){document.documentElement.dataset.sidebar='expanded'}</script>
<link rel="icon" href="<?= asset('brand/Krono_Mark.svg') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<div class="flex min-h-screen">
  <aside class="sticky top-0 z-40 flex h-screen w-62 min-w-62 shrink-0 flex-col self-start border-r border-line bg-side px-3.75 pt-5.5 pb-4 in-data-[sidebar=collapsed]:w-19.5 in-data-[sidebar=collapsed]:min-w-19.5 in-data-[sidebar=collapsed]:px-3">
    <div class="mb-5.5 border-b border-line px-1.75 pb-6 in-data-[sidebar=collapsed]:hidden">
      <a href="<?= url('/dashboard') ?>" aria-label="Krono dashboard">
        <img class="logo-light block h-auto w-44" src="<?= asset('brand/Krono_Logo_Light.svg') ?>" alt="Krono">
        <img class="logo-dark block h-auto w-44" src="<?= asset('brand/Krono_Logo_Dark.svg') ?>" alt="Krono">
      </a>
      <span class="mt-2.5 ml-2 block text-[9px] font-bold tracking-[1.3px] text-side-muted">WORK MANAGEMENT</span>
    </div>
    <button type="button" data-sidebar-toggle class="group/nav relative mx-auto mb-5.5 hidden h-11 w-11 cursor-pointer items-center justify-center rounded-xl border-0 bg-transparent p-0 in-data-[sidebar=collapsed]:flex" aria-label="Open sidebar">
      <img class="h-9 w-9" src="<?= asset('brand/Krono_Mark.svg') ?>" alt="">
      <span role="tooltip" class="<?= $tip ?> group-hover/nav:opacity-100 group-focus-visible/nav:opacity-100">Open sidebar</span>
    </button>
    <nav aria-label="Main">
      <div class="px-2.5 pb-2.5 text-[10px] font-bold tracking-[1.25px] text-side-muted in-data-[sidebar=collapsed]:hidden">WORKSPACE</div>
      <?php foreach ($items as $k => [$label, $ic, $path]): ?>
        <a class="nav-link group/nav" href="<?= url($path) ?>"<?= $k === $nav ? ' aria-current="page"' : '' ?>>
          <?= icon($ic, 18) ?><span class="in-data-[sidebar=collapsed]:hidden"><?= e($label) ?></span>
          <span role="tooltip" class="<?= $tip ?> group-hover/nav:opacity-100 group-focus-visible/nav:opacity-100"><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="mt-auto flex items-center gap-2.25 border-t border-line px-0.75 pt-4 in-data-[sidebar=collapsed]:justify-center">
      <div class="user-dot"><?= e($initial) ?></div>
      <div class="flex min-w-0 flex-1 flex-col gap-0.5 in-data-[sidebar=collapsed]:hidden"><strong class="truncate text-xs"><?= e($uname) ?></strong><span class="truncate text-[10px] text-side-muted"><?= e($uemail) ?></span></div>
    </div>
  </aside>

  <main class="min-w-0 flex-1">
    <header class="sticky top-0 z-30 border-b border-line bg-page/90 px-8 backdrop-blur max-sm:px-4">
      <div class="mx-auto flex h-18.5 items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3.5">
          <button type="button" data-sidebar-toggle class="icon-button" aria-label="Toggle sidebar"><?= icon('menu', 16) ?></button>
          <div class="truncate text-xs text-muted">Workspace <span class="mx-2 text-side-muted">›</span> <strong class="font-bold text-ink"><?= e($crumb) ?></strong></div>
        </div>
        <div class="flex items-center gap-3">
          <button class="icon-button" type="button" aria-label="Search" title="Search (coming soon)"><?= icon('search', 16) ?></button>
          <button class="icon-button" type="button" aria-label="Notifications" title="Notifications (coming soon)"><?= icon('bell', 16) ?></button>
          <span class="mx-0.75 h-6 w-px bg-line"></span>
          <details class="relative" data-account-menu>
            <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl p-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand [&::-webkit-details-marker]:hidden" aria-label="Account menu">
              <span class="user-dot"><?= e($initial) ?></span>
              <span class="flex flex-col gap-0.5 text-[11px] max-sm:hidden"><strong><?= e($uname) ?></strong><span class="text-[10px] text-muted">Workspace</span></span>
            </summary>
            <div class="absolute right-0 top-[calc(100%+8px)] z-50 min-w-50 rounded-xl border border-line bg-surface p-1.5 shadow-card">
              <form method="post" action="<?= url('/open-app') ?>"><?= csrf_field() ?><button class="menu-item" type="submit">Open Krono Desktop</button></form>
              <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?><button class="menu-item" type="submit">Log out</button></form>
            </div>
          </details>
        </div>
      </div>
    </header>
    <div class="mx-auto px-8 max-sm:px-4">
      <?= $content ?? '' ?>
      <footer class="border-t border-line py-4.5 text-[10px] text-muted">Krono <span class="mx-1.25">·</span> Track time. Manage work.</footer>
    </div>
  </main>
</div>
<script>
(function(){var r=document.documentElement;
document.addEventListener('click',function(e){
  var b=e.target.closest('[data-sidebar-toggle]');
  if(b){var next=r.dataset.sidebar==='collapsed'?'expanded':'collapsed';r.dataset.sidebar=next;try{localStorage.setItem('krono-sidebar',next)}catch(x){}return}
  if(e.target.closest('.nav-link')&&r.dataset.sidebar==='collapsed'){try{localStorage.setItem('krono-sidebar','expanded')}catch(x){}} // icon click opens the sidebar on the next page
  document.querySelectorAll('[data-account-menu][open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open')});
});})();
</script>
</body>
</html>
