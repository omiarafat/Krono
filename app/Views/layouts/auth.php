<?php
/* Sign-in pages: brand panel on the left (hidden on small screens), the form card on the right.
   A page may set $aside = ['eyebrow' => , 'title' => , 'text' => , 'items' => [[icon, text], ...]] to change the left panel. */
$aside = $aside ?? [];
$aside += ['eyebrow' => 'A clearer way to work', 'title' => 'One account. Work the way you need.', 'text' => 'Track time, manage projects and keep your team organised from one place.',
           'items' => [['time', 'Track work and keep every workspace organised'], ['team', 'Personal use and company teams in one account'], ['shield', 'Workspace-level roles and controlled access']]];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Krono') ?> · Krono</title>
<link rel="icon" href="<?= asset('brand/Krono_Mark.svg') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<div class="grid min-h-screen grid-cols-1 bg-page laptop:grid-cols-5">
  <aside class="hidden flex-col justify-between border-r border-line bg-soft/60 px-12 py-10 laptop:col-span-2 laptop:flex">
    <div>
      <img class="logo-light block h-auto w-40" src="<?= asset('brand/Krono_Logo_Light.svg') ?>" alt="Krono">
      <img class="logo-dark block h-auto w-40" src="<?= asset('brand/Krono_Logo_Dark.svg') ?>" alt="Krono">
    </div>
    <div class="max-w-md">
      <span class="auth-eyebrow"><?= e($aside['eyebrow']) ?></span>
      <h1 class="mb-3.5 text-4xl"><?= e($aside['title']) ?></h1>
      <p class="mt-0 mb-7 text-ui leading-copy text-muted"><?= e($aside['text']) ?></p>
      <ul class="m-0 flex list-none flex-col gap-3 p-0">
        <?php foreach ($aside['items'] as [$ic, $tx]): ?>
          <li class="flex items-center gap-3 text-xs text-muted"><span class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-control border border-line bg-surface text-brand"><?= icon($ic, 16) ?></span><?= e($tx) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <p class="m-0 text-tiny text-muted">Krono · Track time · Manage work</p>
  </aside>
  <main class="flex flex-col items-center justify-center gap-6 px-4 py-10 laptop:col-span-3 laptop:px-8">
    <div class="laptop:hidden">
      <img class="logo-light block h-auto w-40" src="<?= asset('brand/Krono_Logo_Light.svg') ?>" alt="Krono">
      <img class="logo-dark block h-auto w-40" src="<?= asset('brand/Krono_Logo_Dark.svg') ?>" alt="Krono">
    </div>
    <div class="w-full max-w-107.5"><?= $content ?? '' ?></div>
  </main>
</div>
<script src="<?= asset('js/krono-auth.js') ?>" defer></script>
</body>
</html>
