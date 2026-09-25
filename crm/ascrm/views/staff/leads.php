<?php use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head">
  <div><h1>Leads inbox</h1><p class="sub">Case requests from the website, priority deadlines first. Contents are encrypted until opened here.</p></div>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/leads')) ?>">
    <select name="status" data-autosubmit aria-label="Status"><?= options(['lead' => 'Open leads', 'declined' => 'Declined', 'all' => 'All'], $filters['status']) ?></select>
    <select name="priority" data-autosubmit aria-label="Priority"><?= options(Labels::PRIORITY, $filters['priority'], true, 'Any priority') ?></select>
    <select name="source" data-autosubmit aria-label="Source"><?= options(Labels::SOURCES, $filters['source'], true, 'Any source') ?></select>
    <noscript><button class="btn btn-ghost btn-sm" type="submit">Filter</button></noscript>
  </form>
  <?php if (!$rows): ?>
    <?php $icon = 'inbox'; $heading = 'No leads match'; $text = 'When someone submits the website case form, it lands here instantly.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Reference</th><th>Issue</th><th>Platform</th><th>Appeals so far</th><th>Source</th><th>Priority</th><th>Case lead</th><th>Received</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['priority'] === 'emergency' ? 'emergency' : '' ?>">
        <td data-l="Reference"><a class="row-link ref" href="<?= h(url('/leads/' . $r['id'])) ?>"><?= h($r['ref']) ?></a><?php if ($r['status'] === 'declined'): ?> <?= View::badge('declined') ?><?php endif; ?></td>
        <td data-l="Issue"><?= h($r['title']) ?></td>
        <td data-l="Platform"><?= h(Labels::PLATFORMS[$r['platform'] ?? ''] ?? '') ?></td>
        <td data-l="Appeals so far"><?= h(Labels::HISTORY[$r['appeal_history'] ?? ''] ?? '') ?></td>
        <td data-l="Source"><span class="pill"><?= h(Labels::SOURCES[$r['source']] ?? $r['source']) ?></span></td>
        <td data-l="Priority"><?= View::badge($r['priority']) ?></td>
        <td data-l="Case lead"><?= $r['lead_name'] ? h($r['lead_name']) : '<span class="muted">Unclaimed</span>' ?></td>
        <td data-l="Received" class="nowrap"><span title="<?= h(fdate($r['created_at'], true)) ?>"><?= h(ago($r['created_at'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($pg, '/leads', array_filter($filters)) ?>
  <?php endif; ?>
</div>
