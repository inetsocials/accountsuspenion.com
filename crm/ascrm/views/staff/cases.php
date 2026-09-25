<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head">
  <div><h1>Cases</h1><p class="sub">Accepted cases across Diagnose, Evidence, Appeal and Protect.</p></div>
  <?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-primary" href="<?= h(url('/cases/new')) ?>"><?= icon('plus') ?>New case</a><?php endif; ?>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/cases')) ?>">
    <input class="grow" type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Reference, title or client" aria-label="Search cases">
    <select name="status" data-autosubmit aria-label="Status"><?= options(['open' => 'Open cases', 'all' => 'All'] + array_diff_key(Labels::CASE_STATUS, ['lead' => 1, 'declined' => 1]), $filters['status']) ?></select>
    <select name="stage" data-autosubmit aria-label="Stage"><?= options(Labels::STAGES, $filters['stage'], true, 'Any stage') ?></select>
    <select name="priority" data-autosubmit aria-label="Priority"><?= options(Labels::PRIORITY, $filters['priority'], true, 'Any priority') ?></select>
    <select name="lead" data-autosubmit aria-label="Case lead"><option value="">Any case lead</option><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((string) $filters['lead'] === (string) $s['id']) ?>><?= h($s['name']) ?></option><?php endforeach; ?></select>
    <select name="sort" data-autosubmit aria-label="Sort"><?= options(['activity' => 'Recent activity', 'priority' => 'Priority', 'opened' => 'Newest', 'ref' => 'Reference'], $filters['sort']) ?></select>
    <label class="check mb-0"><input type="checkbox" name="mine" value="1"<?= checked($filters['mine'] === '1') ?> data-autosubmit><span>Mine</span></label>
  </form>
  <?php if (!$rows): ?>
    <?php $icon = 'briefcase'; $heading = 'No cases match'; $text = 'Adjust the filters or accept a lead.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Case</th><th>Client</th><th>Stage</th><th>Status</th><th>Priority</th><th>Appeals</th><th>Lead</th><th>Activity</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $pct = $r['targets'] ? $r['won'] / $r['targets'] * 100 : 0; ?>
      <tr class="<?= $r['priority'] === 'emergency' ? 'emergency' : '' ?>">
        <td data-l="Case" class="title-cell"><a class="row-link" href="<?= h(url('/cases/' . $r['id'])) ?>"><span class="ref"><?= h($r['ref']) ?></span></a> <?php if ($r['unread']): ?><span class="unread" title="Unread messages"><?= (int) $r['unread'] ?></span><?php endif; ?><small><?= h(str_limit($r['title'], 70)) ?></small></td>
        <td data-l="Client"><a href="<?= h(url('/clients/' . $r['client_id'])) ?>"><?= h($r['display_name']) ?></a></td>
        <td data-l="Stage"><?= h(Labels::STAGES[$r['stage']] ?? '') ?></td>
        <td data-l="Status"><?= View::badge($r['status']) ?></td>
        <td data-l="Priority"><?= View::badge($r['priority']) ?></td>
        <td data-l="Appeals"><?php if ($r['targets']): ?><div class="bar ok"><i class="<?= wclass($pct) ?>"></i></div><span class="small muted"><?= (int) $r['won'] ?> of <?= (int) $r['targets'] ?></span><?php else: ?><span class="muted small">None yet</span><?php endif; ?></td>
        <td data-l="Lead"><?= h($r['lead_name'] ?? '') ?></td>
        <td data-l="Activity" class="muted nowrap"><?= h(ago($r['last_activity_at'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, '/cases', array_filter($filters)) ?>
  <?php endif; ?>
</div>
