<?php use DR\Core\View; ?>
<div class="page-head"><div><h1>Invoices</h1><p class="sub">Pay by bank transfer quoting the invoice number. We never take card details by email or message.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <?php if (!$rows): ?><?php $icon = 'receipt'; $heading = 'No invoices'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Number</th><th>Case</th><th>Issued</th><th>Due</th><th class="right">Total</th><th class="right">Balance</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($rows as $i): ?><tr><td data-l="Number" class="mono"><?= h($i['number']) ?></td><td data-l="Case" class="ref"><?= h($i['ref'] ?? '') ?></td><td data-l="Issued"><?= h(fdate($i['issued_on'])) ?></td><td data-l="Due"><?= h(fdate($i['due_on'])) ?></td><td data-l="Total" class="right num"><?= h(money((int) $i['total'], $i['currency'])) ?></td><td data-l="Balance" class="right num"><?= h(money((int) $i['total'] - (int) $i['paid'], $i['currency'])) ?></td><td data-l="Status"><?= View::badge($i['status']) ?></td><td class="right"><a class="btn btn-ghost btn-xs" href="<?= h(url('/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>View</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <div class="card"><div class="card-h"><h2>How to pay</h2></div><div class="card-b small">
    <?php if ($bank): ?><p class="pre mb-1"><?= h($bank) ?></p><?php else: ?><p class="muted">Bank details are shown on each invoice.</p><?php endif; ?>
    <p class="mb-0 muted">Always confirm bank details inside this portal before paying. We will never change them by email.</p>
  </div></div>
</div>
