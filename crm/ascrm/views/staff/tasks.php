<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head"><div><h1>Tasks</h1><p class="sub">Your work queue. Task details are encrypted.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <form class="filters" method="get" action="<?= h(url('/tasks')) ?>">
      <select name="view" data-autosubmit aria-label="View"><?= options(['mine' => 'Assigned to me', 'created' => 'Created by me'] + (Auth::atLeast($user, 'admin') ? ['all' => 'Everyone'] : []), $view) ?></select>
      <select name="status" data-autosubmit aria-label="Status"><?= options(Labels::TASK_STATUS + ['any' => 'Any status'], $status) ?></select>
    </form>
    <?php if (!$rows): ?><?php $icon = 'check-square'; $heading = 'Nothing here'; $text = 'Add a task on the right or from a case.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?>
    <ul class="list">
      <?php foreach ($rows as $t): $late = $t['status'] === 'open' && $t['due_on'] && $t['due_on'] < today(); ?>
      <li>
        <?php if ($t['status'] === 'open'): ?><form method="post" action="<?= h(url('/tasks/' . $t['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="done"><button class="icon-btn" type="submit" aria-label="Mark done"><?= icon('check') ?></button></form>
        <?php else: ?><form method="post" action="<?= h(url('/tasks/' . $t['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="open"><button class="icon-btn" type="submit" aria-label="Reopen"><?= icon('refresh') ?></button></form><?php endif; ?>
        <div class="grow">
          <div class="<?= $t['status'] !== 'open' ? 'muted' : '' ?>"><strong><?= h($t['title']) ?></strong></div>
          <div class="meta"><?php if ($t['ref']): ?><a class="ref" href="<?= h(url('/cases/' . $t['case_id'], ['tab' => 'work'])) ?>"><?= h($t['ref']) ?></a> &middot; <?php endif; ?><?= h($t['assignee'] ?? '') ?> &middot; <span class="<?= $late ? 'text-danger' : '' ?>"><?= $t['due_on'] ? 'Due ' . h(fdate($t['due_on'])) : 'No due date' ?></span></div>
          <?php if ($t['description']): ?><div class="small muted pre"><?= h(str_limit($t['description'], 300)) ?></div><?php endif; ?>
        </div>
        <?= View::badge($t['status'] === 'open' ? ($late ? 'high' : $t['priority']) : $t['status'], $t['status'] === 'open' ? ($late ? 'Overdue' : ucfirst($t['priority'])) : null) ?>
        <button type="button" class="btn btn-ghost btn-xs" data-fill="#task-form" data-title="Edit task" data-values="<?= h(json_encode(['task_id' => $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'due_on' => $t['due_on'], 'case_id' => $t['case_id'], 'assignee_id' => $t['assignee_id']])) ?>"><?= icon('pen') ?></button>
        <form method="post" action="<?= h(url('/tasks/' . $t['id'] . '/delete')) ?>" data-confirm="Delete this task?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?= pager($pg, '/tasks', ['view' => $view, 'status' => $status]) ?>
    <?php endif; ?>
  </div>
  <form class="card" id="task-form" method="post" action="<?= h(url('/tasks')) ?>">
    <?= csrf_field() ?><input type="hidden" name="task_id" value="">
    <div class="card-h"><h2 data-form-title>New task</h2></div>
    <div class="card-b">
      <label class="field"><span>Task</span><input type="text" name="title" required></label>
      <label class="field"><span>Case</span><select name="case_id"><option value="">No case</option><?php foreach ($cases as $c): ?><option value="<?= (int) $c['id'] ?>"><?= h($c['ref'] . ' ' . str_limit($c['title'], 40)) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Assign to</span><select name="assignee_id"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'] === (int) $user['id']) ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></label>
      <div class="form-grid"><label class="field"><span>Due</span><input type="date" name="due_on"></label><label class="field"><span>Priority</span><select name="priority"><?= options(Labels::TASK_PRIORITY, 'medium') ?></select></label></div>
      <label class="field"><span>Details</span><textarea name="description" rows="4"></textarea></label>
      <button class="btn btn-primary btn-block" type="submit">Save task</button>
    </div>
  </form>
</div>
