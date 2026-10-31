<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Krono') ?> · Krono</title>
<script>try{var t=localStorage.getItem('krono-theme');if(t==='light'||t==='dark')document.documentElement.dataset.theme=t}catch(e){}</script>
<link rel="icon" href="<?= asset('brand/Krono_Mark.svg') ?>">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
<div class="flex min-h-screen items-center justify-center bg-page p-7.5">
  <div class="flex w-107.5 max-w-full flex-col items-center gap-4">
    <div class="mb-1 flex flex-col items-center gap-2">
      <div>
        <img class="logo-light block h-auto w-45" src="<?= asset('brand/Krono_Logo_Light.svg') ?>" alt="Krono">
        <img class="logo-dark block h-auto w-45" src="<?= asset('brand/Krono_Logo_Dark.svg') ?>" alt="Krono">
      </div>
      <span class="text-[9px] font-extrabold tracking-[1.4px] text-brand">PRODUCTIVITY WORKSPACE</span>
    </div>
    <?= $content ?? '' ?>
    <p class="m-0 text-[11px] text-muted">Secure access to your time and work</p>
  </div>
</div>
</body>
</html>
