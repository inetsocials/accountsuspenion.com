<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; use DR\Service\Invoices; ?>
<div class="page-head">
  <div><h1>Invoices</h1><p class="sub">Issue in the portal, record bank transfers as they arrive. No card data is ever handled here.</p></div>
  <?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-primary" href="<?= h(url('/invoices/new')) ?>"><?= icon('plus') ?>New invoice</a><?php endif; ?>
</div>
<div class="grid g-3 mb-2">
  <div class="card kpi"><span class="glyph"><?= icon('receipt') ?></span><div class="label">Outstanding</div><div class="value"><?= h(money((int) $totals['outstanding'])) ?></div></div>
  <div class="card kpi danger"><span class="glyph"><?= icon('alert') ?></span><div class="label">Overdue</div><div class="value"><?= h(money((int) $totals['overdue'])) ?></div></div>
  <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Received to date</div><div class="value"><?= h(money((int) $totals['received'])) ?></div></div>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/invoices')) ?>">
    <select name="status" data-autosubmit aria-label="Status"><?= options(['outstanding' => 'Outstanding', 'overdue' => 'Overdue'] + Labels::INVOICE_STATUS, $status, true, 'All invoices') ?></select>
  </form>
  <?php if (!$rows): ?><?php $icon = 'receipt'; $heading = 'No invoices'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Number</th><th>Client</th><th>Case</th><th>Issued</th><th>Due</th><th class="right">Total</th><th class="right">Balance</th><th>Status</th></tr></thead>
    <tbody><?php foreach ($rows as $i): $over = Invoices::isOverdue($i); ?>
      <tr>
        <td data-l="Number"><a class="row-link mono" href="<?= h(url('/invoices/' . $i['id'])) ?>"><?= h($i['number']) ?></a></td>
        <td data-l="Client"><?= h($i['display_name']) ?></td>
        <td data-l="Case" class="ref"><?= h($i['ref'] ?? '') ?></td>
        <td data-l="Issued" class="nowrap"><?= h(fdate($i['issued_on'])) ?></td>
        <td data-l="Due" class="nowrap <?= $over ? 'text-danger' : '' ?>"><?= h(fdate($i['due_on'])) ?></td>
        <td data-l="Total" class="right num"><?= h(money((int) $i['total'], $i['currency'])) ?></td>
        <td data-l="Balance" class="right num"><?= h(money((int) $i['total'] - (int) $i['paid'], $i['currency'])) ?></td>
        <td data-l="Status"><?= $over ? View::badge('high', 'Overdue') : View::badge($i['status']) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, '/invoices', array_filter(['status' => $status])) ?>
  <?php endif; ?>
</div>
