<?php
/* Projects page. Vars: $ws $canManage $f (filters) $list (or null) $failed $form (re-opened form with errors, or null) */
$STATUS_TAG = [ // status => [badge background, dot color]
    'in_progress' => ['bg-tag-green/14', 'bg-tag-green'], 'planning' => ['bg-tag-blue/14', 'bg-tag-blue'], 'upcoming' => ['bg-tag-violet/14', 'bg-tag-violet'],
    'on_hold' => ['bg-tag-red/14', 'bg-tag-red'], 'completed' => ['bg-tag-gray/14', 'bg-tag-gray'],
];
$PRIORITY_DOT = ['high' => 'bg-tag-red', 'medium' => 'bg-tag-amber', 'normal' => 'bg-tag-blue', 'low' => 'bg-tag-lime'];
$tag = function (string $label, string $bg, string $dot): string { return '<span class="tag ' . $bg . '"><i class="h-1.5 w-1.5 shrink-0 rounded-full ' . $dot . '"></i>' . e($label) . '</span>'; };
$q = fn(array $o = []) => url('/projects') . (($s = http_build_query(array_filter(array_merge(['q' => $f['q'], 'status' => $f['status'], 'sort' => $f['sort'], 'page' => $f['page']], $o), fn($v, $k) => $v !== '' && !($k === 'status' && $v === 'all') && !($k === 'sort' && $v === 'updated') && !($k === 'page' && (int)$v <= 1), ARRAY_FILTER_USE_BOTH))) ? '?' . $s : '');
$filtered = $f['q'] !== '' || $f['status'] !== 'all';
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$heading = 'Projects'; $sub = 'Organize projects and keep delivery moving';
$actions = $canManage ? '<button type="button" class="btn btn-primary" data-project-new>' . icon('plus', 16) . ' New project</button>' : '';
include ROOT . '/app/Views/partials/page-heading.php';
$select = 'h-10 cursor-pointer rounded-control border border-line bg-surface px-3 text-tiny font-semibold text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-brand';
$fv = $form['values'] ?? ['name' => '', 'description' => '', 'start_date' => '', 'due_date' => '', 'status' => 'planning', 'priority' => 'normal']; $fe = $form['errors'] ?? [];
?>
<div class="flex flex-col gap-4.5 pb-6.5">
  <?php if (!$canManage): ?>
    <p class="m-0 rounded-xl border border-line bg-surface px-3.5 py-2.5 text-tiny text-muted">You can see the projects you belong to. Owners, admins and managers create and edit projects.</p>
  <?php endif; ?>

  <form method="get" action="<?= url('/projects') ?>" class="flex flex-wrap items-center gap-2.5" id="project-filters" role="search">
    <div class="flex h-10 min-w-48 flex-1 items-center gap-2 rounded-control border border-line bg-surface px-3 text-muted focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-brand">
      <?= icon('search', 16) ?>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search projects…" aria-label="Search projects" maxlength="100" class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-xs text-ink outline-none placeholder:text-muted" <?= $f['q'] !== '' ? 'data-keep-focus' : '' ?>>
    </div>
    <select name="status" aria-label="Status" data-kauto class="<?= $select ?>">
      <option value="all">All status</option>
      <?php foreach (Project::STATUS as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= $STATUS_TAG[$k][1] ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      <option value="archived"<?= $f['status'] === 'archived' ? ' selected' : '' ?>>Archived</option>
    </select>
    <select name="sort" aria-label="Sort" data-kauto class="<?= $select ?>">
      <?php foreach (Project::SORT as $k => $l): ?><option value="<?= $k ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>>Sort: <?= e($l) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn" type="submit">Apply</button></noscript>
  </form>

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
        <a class="btn btn-primary" href="<?= url('/projects') ?>">Clear filters</a>
      <?php elseif ($canManage): ?>
        <h2 class="text-base">No projects yet</h2>
        <p class="mt-1.5 mb-5 max-w-md text-xs leading-copy text-muted">Create your first project to organize tasks and track the time your team saves.</p>
        <button type="button" class="btn btn-primary" data-project-new><?= icon('plus', 16) ?> Create your first project</button>
      <?php else: ?>
        <h2 class="text-base">No projects yet</h2>
        <p class="mt-1.5 mb-0 max-w-md text-xs leading-copy text-muted">You are not part of any project yet. When a manager adds you to one, it will appear here.</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-2 gap-4 max-tablet:grid-cols-1">
      <?php foreach ($list['items'] as $p):
        $archived = (bool)$p['archived_at']; [$bg, $dot] = $archived ? $STATUS_TAG['completed'] : $STATUS_TAG[$p['status']];
        $label = $archived ? 'Archived' : Project::STATUS[$p['status']];
        $meta = (int)$p['tasks_total'] . ' task' . ((int)$p['tasks_total'] === 1 ? '' : 's') . ' · ' . fmt_hm((int)$p['logged']) . ' logged';
        $edit = ['id' => (int)$p['id'], 'name' => $p['name'], 'description' => (string)$p['description'], 'start_date' => (string)$p['start_date'], 'due_date' => (string)$p['due_date'], 'status' => $p['status'], 'priority' => $p['priority']];
      ?>
      <article class="panel-card relative p-5 hover:border-brand/50">
        <div class="flex items-center justify-between gap-3">
          <div class="flex h-10.5 w-10.5 items-center justify-center rounded-xl bg-soft text-brand"><?= icon('projects', 20) ?></div>
          <div class="flex items-center gap-1.5">
            <?= $tag($label, $bg, $dot) ?>
            <?php if ($canManage && !$archived): ?>
              <details class="relative z-10" data-menu>
                <summary class="icon-button list-none border-transparent bg-transparent [&::-webkit-details-marker]:hidden" aria-label="Project actions for <?= e($p['name']) ?>"><?= icon('more', 18) ?></summary>
                <div class="absolute top-full right-0 z-20 mt-1 w-44 rounded-xl border border-line bg-surface p-1.5 shadow-modal">
                  <button type="button" class="menu-item" data-project-edit="<?= e(json_encode($edit, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">Edit project</button>
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
          <?php if ($pg > 1): ?><a class="btn h-9" href="<?= e($q(['page' => $pg - 1])) ?>">← Previous</a><?php endif; ?>
          <?php if ($pg < $list['pages']): ?><a class="btn h-9" href="<?= e($q(['page' => $pg + 1])) ?>">Next →</a><?php endif; ?>
        </span>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<!-- Create / Edit project -->
<dialog id="project-modal" class="modal-box" aria-labelledby="pm-title"<?= $form ? ' data-autoopen' : '' ?>>
  <form method="post" action="<?= url('/projects/save') ?>" id="project-form" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0) ?: '' ?>">
    <div class="modal-head">
      <div class="flex-1"><h2 class="m-0 text-base" id="pm-title"><?= !empty($form['id']) ? 'Edit project' : 'Create project' ?></h2><p class="mt-1 mb-0 text-xs leading-copy text-muted" id="pm-sub"><?= !empty($form['id']) ? 'Update the details of this project.' : 'Set up a project to organize related tasks.' ?></p></div>
      <button type="button" class="icon-button border-transparent bg-transparent" data-modal-close aria-label="Close"><?= icon('close', 16) ?></button>
    </div>
    <div class="modal-body flex flex-col gap-3.5">
      <?php if (!empty($form['failed'])): ?><div class="rounded-xl border border-danger/35 bg-danger/8 px-3 py-2.5 text-tiny text-danger" role="alert"><strong>Could not save changes.</strong> Your changes were not saved. Check your connection and try again. Your input has been kept.</div><?php endif; ?>
      <div><label class="field-label" for="pf-name">Project name <span class="text-danger">*</span></label><input class="field-input" id="pf-name" name="name" maxlength="150" value="<?= e($fv['name']) ?>" placeholder="e.g. Website Redesign" <?= isset($fe['name']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['name'])): ?><p class="field-error"><?= e($fe['name']) ?></p><?php endif; ?></div>
      <div><label class="field-label" for="pf-desc">Description</label><textarea class="field-input" id="pf-desc" name="description" maxlength="2000" placeholder="What is this project about?" <?= isset($fe['description']) ? 'aria-invalid="true"' : '' ?>><?= e($fv['description']) ?></textarea><?php if (isset($fe['description'])): ?><p class="field-error"><?= e($fe['description']) ?></p><?php endif; ?></div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="pf-start">Start date <span class="text-danger">*</span></label><input class="field-input" type="date" id="pf-start" name="start_date" data-required placeholder="Select start date" value="<?= e($fv['start_date']) ?>" <?= isset($fe['start_date']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['start_date'])): ?><p class="field-error"><?= e($fe['start_date']) ?></p><?php endif; ?></div>
        <div><label class="field-label" for="pf-due">Due date</label><input class="field-input" type="date" id="pf-due" name="due_date" placeholder="No due date" value="<?= e($fv['due_date']) ?>" <?= isset($fe['due_date']) ? 'aria-invalid="true"' : '' ?>><?php if (isset($fe['due_date'])): ?><p class="field-error"><?= e($fe['due_date']) ?></p><?php endif; ?></div>
      </div>
      <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
        <div><label class="field-label" for="pf-status">Project status</label><select class="field-input" id="pf-status" name="status"><?php foreach (Project::STATUS as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= $STATUS_TAG[$k][1] ?>"<?= $fv['status'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div><label class="field-label" for="pf-priority">Priority</label><select class="field-input" id="pf-priority" name="priority"><?php foreach (Project::PRIORITY as $k => $l): ?><option value="<?= $k ?>" data-dot="<?= $PRIORITY_DOT[$k] ?>"<?= $fv['priority'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn-primary" id="pm-submit"><?= !empty($form['id']) ? 'Save changes' : 'Create project' ?></button>
    </div>
  </form>
</dialog>

<!-- Archive confirmation -->
<dialog id="archive-modal" class="modal-box" aria-labelledby="am-title">
  <form method="post" action="<?= url('/projects/archive') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <div class="modal-head">
      <div class="flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-xl bg-tag-amber/15 text-tag-amber"><?= icon('archive', 18) ?></div>
      <div class="flex-1"><h2 class="m-0 text-base" id="am-title">Archive this project?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted"><strong data-am-name></strong> will be hidden from active project lists.</p></div>
    </div>
    <div class="modal-body">
      <div class="rounded-xl border border-tag-amber/50 bg-tag-amber/8 px-3.5 py-3">
        <strong class="text-tiny">Project has linked work</strong>
        <p class="mt-1 mb-0 text-tiny leading-copy text-muted"><span data-am-counts></span> Archiving keeps existing history available to authorized users.</p>
      </div>
      <p class="mt-3 mb-0 text-tiny text-muted">This does not erase attendance records or saved time logs.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Archive project</button></div>
  </form>
</dialog>

<!-- Discard unsaved changes -->
<dialog id="discard-modal" class="modal-box max-w-105" aria-labelledby="dm-title">
  <div class="modal-head"><div class="flex-1"><h2 class="m-0 text-base" id="dm-title">Discard unsaved changes?</h2><p class="mt-1 mb-0 text-xs leading-copy text-muted">Your edits have not been saved.</p></div></div>
  <div class="modal-body"><p class="m-0 text-tiny leading-copy text-muted">If you leave now, the changes you made in this form will be lost. Previously saved information will not be affected.</p></div>
  <div class="modal-foot"><button type="button" class="btn" data-discard-keep>Keep editing</button><button type="button" class="btn btn-danger" data-discard-yes>Discard changes</button></div>
</dialog>
<?php endif; ?>

<?php if ($flash): [$ft, $fm, $ftitle] = $flash + [2 => '']; $ok = $ft !== 'error'; ?>
<div class="pointer-events-none fixed right-5 bottom-5 z-50 flex w-full max-w-sm flex-col gap-3 max-sm:right-4 max-sm:left-4 max-sm:max-w-none" aria-live="polite">
  <div class="toast" role="<?= $ok ? 'status' : 'alert' ?>" data-toast="<?= $ok ? 'auto' : 'stay' ?>">
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full <?= $ok ? 'bg-soft text-brand' : 'bg-danger/15 text-danger' ?>"><?= icon($ok ? 'check' : 'alert', 15) ?></span>
    <div class="min-w-0 flex-1"><h4 class="m-0 mb-1 text-xs"><?= e($ftitle ?: ($ok ? 'Done' : 'Something went wrong')) ?></h4><p class="m-0 text-tiny leading-copy text-muted"><?= e($fm) ?></p></div>
    <button type="button" class="cursor-pointer border-0 bg-transparent p-0 text-muted hover:text-ink" data-toast-close aria-label="Dismiss"><?= icon('close', 16) ?></button>
  </div>
</div>
<?php endif; ?>

<script>
(function(){
var $=function(s,r){return (r||document).querySelector(s)}, all=function(s,r){return [].slice.call((r||document).querySelectorAll(s))};
/* filters: selects submit at once, search waits until typing stops */
var ff=$('#project-filters');
if(ff){var t;all('select',ff).forEach(function(s){s.addEventListener('change',function(){ff.submit()})});
  var si=$('input[name=q]',ff);si.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){ff.submit()},450)});
  if(si.hasAttribute('data-keep-focus')){si.focus();si.setSelectionRange(si.value.length,si.value.length)}}
/* toast */
all('[data-toast]').forEach(function(x){var kill=function(){x.parentNode&&x.parentNode.remove()};
  $('[data-toast-close]',x).addEventListener('click',kill);if(x.dataset.toast==='auto')setTimeout(kill,6000)});
/* menus: one open at a time, close on outside click */
document.addEventListener('click',function(e){all('[data-menu][open]').forEach(function(d){if(!d.contains(e.target))d.removeAttribute('open')})});
var pm=$('#project-modal');if(!pm)return;
function dueMin(){var s=form.elements.start_date.value,du=form.elements.due_date;if(s)du.setAttribute('min',s);else du.removeAttribute('min')}
var form=$('#project-form'),am=$('#archive-modal'),dm=$('#discard-modal'),snap='';
function ser(){return new URLSearchParams(new FormData(form)).toString()}
function openProject(d){
  form.reset();$('[name=id]',form).value=d?d.id:'';
  var set=function(n,v){var el=form.elements[n];if(el)el.value=v==null?'':v};
  set('name',d?d.name:'');set('description',d?d.description:'');set('start_date',d?d.start_date:'');set('due_date',d?d.due_date:'');set('status',d?d.status:'planning');set('priority',d?d.priority:'normal');dueMin();window.KronoUI&&KronoUI.sync(form);
  $('#pm-title').textContent=d?'Edit project':'Create project';$('#pm-sub').textContent=d?'Update the details of this project.':'Set up a project to organize related tasks.';
  $('#pm-submit').textContent=d?'Save changes':'Create project';
  all('.field-error',form).forEach(function(x){x.remove()});all('[aria-invalid]',form).forEach(function(x){x.removeAttribute('aria-invalid')});
  pm.showModal();snap=ser();$('#pf-name').focus();}
function requestClose(){if(ser()!==snap&&!pm.hasAttribute('data-saving')){dm.showModal()}else pm.close()}
all('[data-project-new]').forEach(function(b){b.addEventListener('click',function(){openProject(null)})});
all('[data-project-edit]').forEach(function(b){b.addEventListener('click',function(){b.closest('[data-menu]').removeAttribute('open');openProject(JSON.parse(b.dataset.projectEdit))})});
all('[data-modal-close]',pm).forEach(function(b){b.addEventListener('click',requestClose)});
pm.addEventListener('cancel',function(e){e.preventDefault();requestClose()});
pm.addEventListener('mousedown',function(e){if(e.target===pm)requestClose()});
$('[data-discard-keep]',dm).addEventListener('click',function(){dm.close()});
$('[data-discard-yes]',dm).addEventListener('click',function(){dm.close();pm.close()});
form.elements.start_date.addEventListener('change',dueMin);dueMin();
form.addEventListener('submit',function(e){
  if(pm.hasAttribute('data-saving')){e.preventDefault();return}
  var bad=false,req=function(n,m){var el=form.elements[n];if(!el.value.trim()){bad=true;mark(el,m)}};
  all('.field-error',form).forEach(function(x){x.remove()});
  function mark(el,m){el.setAttribute('aria-invalid','true');var p=document.createElement('p');p.className='field-error';p.textContent=m;el.parentNode.appendChild(p)}
  req('name','This field is required.');req('start_date','This field is required.');
  var s=form.elements.start_date.value,d=form.elements.due_date.value;if(s&&d&&d<s){bad=true;mark(form.elements.due_date,'Due date cannot be before the start date.')}
  if(bad){e.preventDefault();window.KronoUI&&KronoUI.sync(form);var f=$('[aria-invalid]',form);f&&(f._kfocus||f).focus();return}
  pm.setAttribute('data-saving','');var b=$('#pm-submit');b.disabled=true;b.textContent='Saving…';});
/* archive */
all('[data-project-archive]').forEach(function(b){b.addEventListener('click',function(){
  b.closest('[data-menu]').removeAttribute('open');var f=$('form',am);f.elements.id.value=b.dataset.projectArchive;$('[data-am-name]',am).textContent=b.dataset.name;
  var t=+b.dataset.tasks,l=+b.dataset.logs;$('[data-am-counts]',am).textContent=t+' task'+(t===1?'':'s')+' and '+l+' saved time log'+(l===1?'':'s')+' are linked to this project.';am.showModal()})});
all('[data-modal-close]',am).forEach(function(b){b.addEventListener('click',function(){am.close()})});
$('form',am).addEventListener('submit',function(){var b=$('.btn-danger',am);b.disabled=true;b.textContent='Archiving…'});
if(pm.hasAttribute('data-autoopen')){pm.showModal();snap='';var ids=['pf-name'];$('#pf-name').focus();
  var first=$('[aria-invalid]',form);first&&(first._kfocus||first).focus();}
})();
</script>
