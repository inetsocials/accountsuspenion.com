<?php use DR\Core\Labels; ?>
<div class="page-head">
  <div><h1>Calendar</h1><p class="sub">Deadlines, tasks due and invoice due dates. Reminders go out automatically.</p></div>
  <div class="actions">
    <a class="btn btn-ghost btn-sm" href="<?= h(url('/calendar', ['m' => $prev])) ?>" aria-label="Previous month"><?= icon('chevron-left') ?></a>
    <a class="btn btn-ghost btn-sm" href="<?= h(url('/calendar')) ?>">Today</a>
    <a class="btn btn-ghost btn-sm" href="<?= h(url('/calendar', ['m' => $next])) ?>" aria-label="Next month"><?= icon('chevron') ?></a>
  </div>
</div>
<div class="grid g-side">
  <div class="card">
    <div class="card-h"><h2><?= h($first->format('F Y')) ?></h2><div class="legend"><span><i class="lb"></i>Task</span><span><i class="ld"></i>Overdue</span><span><i class="lc"></i>Invoice due</span></div></div>
    <div class="cal">
      <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><div class="dow"><?= $d ?></div><?php endforeach; ?>
      <?php for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+1 day')): $k = $d->format('Y-m-d'); $cls = ($d->format('m') !== $first->format('m') ? ' other' : '') . ($k === today() ? ' today' : ''); ?>
        <div class="day<?= $cls ?>"><span class="dnum"><?= $d->format('j') ?></span>
          <?php foreach ($events[$k] ?? [] as $e):
            $href = $e['type'] === 'invoice' ? url('/invoices/' . $e['id']) : ($e['case_id'] ? url('/cases/' . $e['case_id'], ['tab' => 'work']) : url($e['type'] === 'task' ? '/tasks' : '/calendar')); ?>
            <a class="ev <?= h($e['tone']) ?>" href="<?= h($href) ?>" title="<?= h(($e['ref'] ? $e['ref'] . ': ' : '') . $e['label']) ?>"><?= h(($e['ref'] ? $e['ref'] . ' ' : '') . $e['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Next 14 days</h2></div>
      <?php if (!$agenda): ?><?php $icon = 'calendar'; $heading = 'Clear fortnight'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($agenda as $a): ?>
        <li><div class="grow"><div><strong><?= h($a['title']) ?></strong></div><div class="meta <?= $a['due_at'] < now() ? 'text-danger' : '' ?>"><?= $a['ref'] ? h($a['ref']) . ' &middot; ' : '' ?><?= h(fdate($a['due_at'], true)) ?></div><?php if ($a['notes']): ?><div class="small muted pre"><?= h(str_limit($a['notes'], 160)) ?></div><?php endif; ?></div>
          <form method="post" action="<?= h(url('/deadlines/' . $a['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><button class="btn btn-ghost btn-xs" type="submit">Done</button></form></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <form class="card" method="post" action="<?= h(url('/deadlines')) ?>"><?= csrf_field() ?>
      <div class="card-h"><h2>Add deadline</h2></div>
      <div class="card-b">
        <label class="field"><span>Title</span><input type="text" name="title" required></label>
        <label class="field"><span>Case</span><select name="case_id"><option value="">No case (personal)</option><?php foreach ($cases as $c): ?><option value="<?= (int) $c['id'] ?>"><?= h($c['ref'] . ' ' . str_limit($c['title'], 40)) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Type</span><select name="kind"><?= options(Labels::DEADLINE_KINDS, 'other') ?></select></label>
        <div class="form-grid"><label class="field"><span>Due</span><input type="datetime-local" name="due_at" required></label><label class="field"><span>Remind (days before)</span><input type="number" name="remind_days" value="2" min="0" max="30"></label></div>
        <label class="check"><input type="checkbox" name="client_visible" value="1"><span>Show to client</span></label>
        <label class="field"><span>Notes</span><textarea name="notes" rows="2"></textarea></label>
        <button class="btn btn-primary btn-block" type="submit">Add deadline</button>
      </div>
    </form>
  </div>
</div>
