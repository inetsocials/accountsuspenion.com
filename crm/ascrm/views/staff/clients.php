<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head">
  <div><h1>Clients</h1><p class="sub">Each client file has its own encryption key and a private vault folder.</p></div>
  <?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-primary" href="<?= h(url('/clients/new')) ?>"><?= icon('plus') ?>New client</a><?php endif; ?>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/clients')) ?>">
    <input class="grow" type="search" name="q" value="<?= h($q) ?>" placeholder="Search name or client number" aria-label="Search clients">
    <select name="status" data-autosubmit aria-label="Status"><?= options(['active' => 'Active', 'inactive' => 'Inactive'], $status, true, 'Any status') ?></select>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('search') ?>Search</button>
  </form>
  <?php if (!$rows): ?>
    <?php $icon = 'users'; $heading = 'No clients found'; $text = 'Clients are created when you accept a lead, or manually.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Client</th><th>Type</th><th>Risk</th><th class="right">Open cases</th><th class="right">Documents</th><th>Last activity</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr>
        <td data-l="Client" class="title-cell"><a class="row-link" href="<?= h(url('/clients/' . $r['id'])) ?>"><?= h($r['display_name']) ?></a><small class="mono"><?= h($r['number']) ?><?= $r['is_codename'] ? ' (codename)' : '' ?></small></td>
        <td data-l="Type"><?= h(Labels::CLIENT_TYPE[$r['type']] ?? $r['type']) ?></td>
        <td data-l="Risk"><?= $r['risk_level'] === 'standard' ? '<span class="muted">Standard</span>' : View::badge($r['risk_level'] === 'high' ? 'high' : 'medium', Labels::RISK[$r['risk_level']]) ?></td>
        <td data-l="Open cases" class="right num"><?= (int) $r['open_cases'] ?> <span class="muted">/ <?= (int) $r['all_cases'] ?></span></td>
        <td data-l="Documents" class="right num"><?= (int) $r['docs'] ?></td>
        <td data-l="Last activity" class="muted nowrap"><?= h(ago($r['last_activity'])) ?></td>
        <td data-l="Status"><?= View::badge($r['status'] === 'active' ? 'active' : 'cancelled', ucfirst($r['status'])) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, '/clients', array_filter(['q' => $q, 'status' => $status])) ?>
  <?php endif; ?>
</div>
