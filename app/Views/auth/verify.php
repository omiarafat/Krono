<div class="w-full rounded-[20px] border border-line bg-surface p-[30px] shadow-card">
  <div class="mb-[17px] flex h-[47px] w-[47px] items-center justify-center rounded-[14px] bg-soft text-brand"><?= icon('shield') ?></div>
  <h1>Verify your email</h1><p class="text-muted">Enter the one-time code sent to <?= e($email ?? '') ?></p>
  <?= flash_html() ?><?= errors_html($errors ?? []) ?>
  <form method="post" action="<?= url('/verify') ?>" novalidate class="mt-[18px]"><?= csrf_field() ?>
    <div class="mb-5 flex flex-col gap-3.5">
      <label class="block">Verification code · 6 digits<input class="flex h-10 w-full items-center rounded-[10px] border border-line bg-surface px-3 text-[13px] font-medium text-ink outline-none [font-family:inherit] focus:border-brand focus:ring-[3px] focus:ring-soft" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required autofocus></label>
    </div>
    <button class="inline-flex h-[42px] w-full cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-brand bg-brand px-4 text-xs font-bold text-on-brand shadow-[0_3px_7px_rgba(4,120,87,.10)] [font-family:inherit] hover:bg-brand-hover" type="submit">Verify email</button>
  </form>
  <?php if (Auth::fixedCode()): ?><p class="mt-3 mb-0 text-center text-[11px] text-muted">Local development: the code is <?= e((string)env('FIXED_CODE', '123456')) ?>.</p><?php endif; ?>
  <form method="post" action="<?= url('/verify/resend') ?>" class="mt-[17px] mb-0 text-center text-[11px] text-muted"><?= csrf_field() ?>
    <button class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-brand [font-family:inherit] text-[11px] hover:underline" type="submit">Resend code</button> &nbsp;·&nbsp; <a class="font-semibold text-brand hover:underline" href="<?= url('/login') ?>">Back to login</a>
  </form>
</div>
