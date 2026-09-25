<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; use DR\Service\Invoices; $cur = $inv['currency']; $draft = $inv['status'] === 'draft'; $over = Invoices::isOverdue($inv); ?>
<div class="page-head">
  <div>
    <div class="eyebrow"><a href="<?= h(url('/clients/' . $client['id'], ['tab' => 'invoices'])) ?>"><?= h($client['number'] . ' ' . $client['display_name']) ?></a><?= $case ? ' &middot; <a href="' . h(url('/cases/' . $case['id'])) . '">' . h($case['ref']) . '</a>' : '' ?></div>
    <h1 class="mono"><?= h($inv['number']) ?></h1>
    <p class="sub row"><?= $over ? View::badge('high', 'Overdue') : View::badge($inv['status']) ?> <span>Issued <?= h(fdate($inv['issued_on'])) ?>, due <?= h(fdate($inv['due_on'])) ?></span></p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= h(url('/invoices/' . $inv['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>Print or PDF</a>
    <?php if ($draft && Auth::atLeast($user, 'lead')): ?><form method="post" action="<?= h(url('/invoices/' . $inv['id'] . '/send')) ?>" data-confirm="Issue this invoice? The client will be notified to view it in the portal."><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= icon('mail') ?>Issue to client</button></form><?php endif; ?>
    <?php if (Auth::atLeast($user, 'admin') && $inv['status'] !== 'cancelled' && (int) $inv['paid'] === 0): ?><form method="post" action="<?= h(url('/invoices/' . $inv['id'] . '/cancel')) ?>" data-confirm="Cancel this invoice?" data-danger><?= csrf_field() ?><button class="btn btn-soft-danger" type="submit">Cancel</button></form><?php endif; ?>
  </div>
</div>
<div class="grid g-main">
  <div class="stack">
    <?php if ($draft && Auth::atLeast($user, 'lead')): ?>
    <form class="card" method="post" action="<?= h(url('/invoices/' . $inv['id'])) ?>">
      <?= csrf_field() ?>
      <div class="card-h"><h2>Edit draft</h2><button class="btn btn-primary btn-sm" type="submit">Save</button></div>
      <div class="card-b">
        <div class="form-grid-3">
          <label class="field"><span>Issue date</span><input type="date" name="issued_on" value="<?= h($inv['issued_on']) ?>"></label>
          <label class="field"><span>Due date</span><input type="date" name="due_on" value="<?= h($inv['due_on']) ?>"></label>
          <label class="field"><span>Discount</span><input type="number" name="discount" step="0.01" min="0" value="<?= h(number_format($inv['discount'] / 100, 2, '.', '')) ?>"></label>
        </div>
        <?php $currency = $cur; include DR_ROOT . '/views/staff/_items.php'; ?>
        <label class="field mt-2"><span>Notes</span><textarea name="notes" rows="2"><?= h((string) $inv['notes']) ?></textarea></label>
      </div>
    </form>
    <?php else: ?>
    <div class="card">
      <div class="card-h"><h2>Lines</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Tax</th><th class="right">Net</th></tr></thead><tbody>
        <?php foreach ($items as $it): ?><tr><td><?= h($it['description']) ?></td><td class="right num"><?= h(rtrim(rtrim(number_format($it['qty_x100'] / 100, 2), '0'), '.')) ?></td><td class="right num"><?= h(money((int) $it['unit_price'], $cur)) ?></td><td class="right num"><?= h(rtrim(rtrim(number_format($it['vat_bp'] / 100, 2), '0'), '.')) ?>%</td><td class="right num"><?= h(money((int) $it['line_total'], $cur)) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php if ($inv['notes']): ?><div class="card-f small pre"><?= h($inv['notes']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="stack">
    <div class="card"><div class="card-b num">
      <div class="row-between"><span class="muted">Subtotal</span><span><?= h(money((int) $inv['subtotal'], $cur)) ?></span></div>
      <?php if ((int) $inv['discount']): ?><div class="row-between"><span class="muted">Discount</span><span>-<?= h(money((int) $inv['discount'], $cur)) ?></span></div><?php endif; ?>
      <div class="row-between"><span class="muted">Tax</span><span><?= h(money((int) $inv['vat'], $cur)) ?></span></div>
      <hr>
      <div class="row-between"><strong>Total</strong><strong><?= h(money((int) $inv['total'], $cur)) ?></strong></div>
      <div class="row-between"><span class="muted">Paid</span><span class="text-ok"><?= h(money((int) $inv['paid'], $cur)) ?></span></div>
      <div class="row-between"><strong>Balance</strong><strong><?= h(money((int) $inv['total'] - (int) $inv['paid'], $cur)) ?></strong></div>
    </div></div>
    <div class="card">
      <div class="card-h"><h2>Payments</h2></div>
      <?php if (!$payments): ?><?php $icon = 'receipt'; $heading = 'No payments recorded'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($payments as $p): ?>
        <li><div class="grow"><strong class="num"><?= h(money((int) $p['amount'], $cur)) ?></strong> <span class="pill"><?= h(Labels::PAY_METHODS[$p['method']] ?? $p['method']) ?></span><div class="meta"><?= h(fdate($p['paid_on'])) ?><?= $p['reference'] ? ' &middot; ' . h($p['reference']) : '' ?> &middot; <?= h($p['by_name'] ?? '') ?></div></div>
        <?php if (Auth::atLeast($user, 'admin')): ?><form method="post" action="<?= h(url('/invoices/' . $inv['id'] . '/payments/' . $p['id'] . '/delete')) ?>" data-confirm="Remove this payment?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Remove"><?= icon('trash') ?></button></form><?php endif; ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
      <?php if (Auth::atLeast($user, 'admin') && in_array($inv['status'], ['sent', 'partial', 'paid'], true)): ?>
      <form class="card-f" method="post" action="<?= h(url('/invoices/' . $inv['id'] . '/payments')) ?>"><?= csrf_field() ?>
        <div class="form-grid">
          <label class="field"><span>Amount</span><input type="number" name="amount" step="0.01" required value="<?= h(number_format(max(0, $inv['total'] - $inv['paid']) / 100, 2, '.', '')) ?>"></label>
          <label class="field"><span>Received on</span><input type="date" name="paid_on" value="<?= h(today()) ?>"></label>
          <label class="field"><span>Method</span><select name="method"><?= options(Labels::PAY_METHODS, 'bank_transfer') ?></select></label>
          <label class="field"><span>Reference</span><input type="text" name="reference"></label>
        </div>
        <button class="btn btn-primary btn-sm" type="submit">Record payment</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
