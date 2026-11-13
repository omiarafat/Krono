<?php
/* Projects page. Vars: $ws $canManage $f (filters) $list (or null) $failed $form (re-opened form with errors, or null) */
$q = fn(array $o = []) => url('/projects') . (($s = http_build_query(array_filter(array_merge(['q' => $f['q'], 'status' => $f['status'], 'sort' => $f['sort'], 'page' => $f['page']], $o), fn($v, $k) => $v !== '' && !($k === 'status' && $v === 'all') && !($k === 'sort' && $v === 'updated') && !($k === 'page' && (int)$v <= 1), ARRAY_FILTER_USE_BOTH))) ? '?' . $s : '');
$filtered = $f['q'] !== '' || $f['status'] !== 'all';

$heading = 'Projects'; $sub = 'Organize projects and keep delivery moving';
$actions = $canManage ? '<button type="button" class="btn btn-primary" data-modal-open="project-modal">' . icon('plus', 16) . ' New project</button>' : '';
include ROOT . '/app/Views/partials/page-heading.php';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$fv = $form['values'] ?? ['name' => '', 'description' => '', 'start_date' => '', 'due_date' => '', 'status' => 'planning', 'priority' => 'normal']; $fe = $form['errors'] ?? [];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <?php if (!$canManage): ?>
    <p class="m-0 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-tiny text-muted">You can see the projects you belong to. Owners, admins and managers create and edit projects.</p>
  <?php endif; ?>

  <form method="get" action="<?= url('/projects') ?>" class="flex flex-wrap items-center gap-2.5" id="project-filters" role="search" data-live data-live-target="project-results">
    <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
      <?= icon('search', 16) ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search projects…" aria-label="Search projects" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted">
    </div>
    <select name="status" aria-label="Status" data-kauto class="<?= $select ?>">
      <option value="all">All status</option>
      <?php foreach (Project::STATUS as $k => $l): ?><option value="<?= $k ?>" data-tag="<?= Ui::tone('project', $k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      <option value="archived"<?= $f['status'] === 'archived' ? ' selected' : '' ?>>Archived</option>
    </select>
    <select name="sort" aria-label="Sort" data-kauto class="<?= $select ?>">
      <?php foreach (Project::SORT as $k => $l): ?><option value="<?= $k ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>>Sort: <?= e($l) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">Apply</button></noscript>
  </form>

  <div id="project-results" data-live-area class="flex flex-col gap-4.5">
  <?php if ($failed): ?>
    <div class="panel-card flex flex-col items-center px-10 py-12 text-center" role="alert">
      <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-icon bg-tag-amber/15 text-tag-amber"><?= icon('alert', 28) ?></div>
      <h2 class="text-base">Connection interrupted</h2>
      <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">We couldn't load the latest data. Please try again in a moment.</p>
      <a class="btn btn-primary" href="<?= e($q()) ?>">Try again</a>
    </div>
  <?php elseif (!$list['items']): ?>
    <div class="panel-card flex flex-col items-center px-10 py-14 text-center">
      <div class="mb-4 flex h-20 w-20 -rotate-6 items-center justify-center rounded-3xl bg-soft text-brand"><?= icon($filtered ? 'search' : 'projects', 30) ?></div>
      <?php if ($filtered): ?>
        <h2 class="text-base">No results found</h2>
        <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">Try changing the search terms or clearing one or more filters.</p>
        <a class="btn btn-primary" href="<?= url('/projects') ?>" data-live>Clear filters</a>
      <?php elseif ($canManage): ?>
        <h2 class="text-base">No projects yet</h2>
        <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">Create your first project to organize tasks and track the time your team saves.</p>
        <button type="button" class="btn btn-primary" data-modal-open="project-modal"><?= icon('plus', 16) ?> Create your first project</button>
      <?php else: ?>
        <h2 class="text-base">No projects yet</h2>
        <p class="mt-1.5 mb-0 max-w-md text-xs leading-copy text-muted">You are not part of any project yet. When a manager adds you to one, it will appear here.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-2 gap-4 max-tablet:grid-cols-1">
      <?php foreach ($list['items'] as $p):
        $archived = (bool)$p['archived_at'];
        $meta = (int)$p['tasks_total'] . ' task' . ((int)$p['tasks_total'] === 1 ? '' : 's') . ' · ' . fmt_hm((int)$p['logged']) . ' logged';
        $edit = ['id' => (int)$p['id'], 'name' => $p['name'], 'description' => (string)$p['description'], 'start_date' => (string)$p['start_date'], 'due_date' => (string)$p['due_date'], 'status' => $p['status'], 'priority' => $p['priority']];
      ?>
      <article class="panel-card relative p-5 hover:border-brand/50">
        <div class="flex items-center justify-between gap-3">
          <div class="flex h-10.5 w-10.5 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('projects', 20) ?></div>
          <div class="flex items-center gap-1.5">
            <?= $archived ? Ui::tag('project', 'archived', 'Archived') : Ui::tag('project', $p['status']) ?>
            <?php if ($canManage && !$archived): ?>
              <details class="relative z-10" data-menu>
                <summary class="icon-button list-none border-transparent bg-transparent [&::-webkit-details-marker]:hidden" aria-label="Project actions for <?= e($p['name']) ?>"><?= icon('more', 18) ?></summary>
                <div class="absolute top-full right-0 z-20 mt-1 w-44 rounded-xl border border-line bg-surface p-1.5 shadow-modal">
                  <button type="button" class="menu-item" data-modal-open="project-modal" data-fill="<?= e(json_encode($edit)) ?>">Edit project</button>
                  <button type="button" class="menu-item text-danger" data-project-archive="<?= (int)$p['id'] ?>" data-name="<?= e($p['name']) ?>" data-tasks="<?= (int)$p['tasks_total'] ?>" data-logs="<?= (int)$p['logs_count'] ?>">Archive project</button>
                </div>
              </details>
            <?php endif; ?>
          </div>
        </div>
        <h3 class="mt-3.5 mb-0 truncate text-sm"><a href="<?= url('/project?id=' . (int)$p['id']) ?>" class="text-ink no-underline after:absolute after:inset-0 after:content-[''] hover:text-brand focus-visible:outline-2 focus-visible:outline-brand"><?= e($p['name']) ?></a></h3>
        <p class="mt-1 mb-0 text-tiny text-muted"><?= e($meta) ?></p>
        <div class="mt-3.5 h-1.5 overflow-hidden rounded-full bg-soft" role="progressbar" aria-label="Progress" aria-valuenow="<?= $p['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><i class="block h-full rounded-full bg-brand" style="width:<?= $p['percent'] ?>%"></i></div>
        <div class="mt-3.5 flex items-center gap-2">
          <span class="text-tiny text-muted">Progress</span><strong class="text-tiny"><?= $p['percent'] ?>%</strong>
          <span class="ml-auto flex">
            <?php foreach ($p['initials'] as $i): ?><b class="-ml-1.25 inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-surface bg-soft text-nano font-bold text-brand"><?= e($i) ?></b><?php endforeach; ?>
            <?php if ($p['more'] > 0): ?><b class="-ml-1.25 inline-flex h-6 w-6 items-center justify-center rounded-full border-2 border-surface bg-soft text-nano font-bold text-brand">+<?= (int)$p['more'] ?></b><?php endif; ?>
          </span>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if ($list['pages'] > 1): $pg = $list['page']; ?>
      <nav class="flex items-center justify-between gap-3 text-tiny text-muted" aria-label="Pages">
        <span>Page <?= $pg ?> of <?= $list['pages'] ?> · <?= $list['total'] ?> projects</span>
        <span class="flex gap-2">
          <?php if ($pg > 1): ?><a class="btn h-9" data-live href="<?= e($q(['page' => $pg - 1])) ?>">← Previous</a><?php endif; ?>
          <?php if ($pg < $list['pages']): ?><a class="btn h-9" data-live href="<?= e($q(['page' => $pg + 1])) ?>">Next →</a><?php endif; ?>
        </span>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
  </div>
</div>

<?php if ($canManage) { include __DIR__ . '/_modals.php'; include __DIR__ . '/_discard.php'; } ?>
<?php include ROOT . '/app/Views/partials/toast.php'; ?>

<script src="<?= asset('js/krono-modals.js') ?>" defer></script>
