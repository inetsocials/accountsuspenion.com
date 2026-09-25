<?php
use DR\Core\Labels;
use DR\Core\Settings;
$cur = $inv['currency'];
?>
<article class="print-sheet">
  <div class="inv-head">
    <div>
      <img src="<?= h(asset('img/logo.png')) ?>" alt="<?= h(Settings::get('org_name')) ?>" width="273" height="30">
      <p class="small mt-2 mb-0"><strong><?= h(Settings::get('legal_name') ?: Settings::get('org_name')) ?></strong><?php if (Settings::get('vat_number')): ?><br>Tax ID <?= h(Settings::get('vat_number')) ?><?php endif; ?></p>
    </div>
    <div class="right">
      <h1>INVOICE</h1>
      <p class="small mb-0"><strong class="mono"><?= h($inv['number']) ?></strong><br>Issued <?= h(fdate($inv['issued_on'])) ?><br>Due <?= h(fdate($inv['due_on'])) ?><br><?= h(Labels::INVOICE_STATUS[$inv['status']] ?? '') ?></p>
    </div>
  </div>
  <div class="grid g-2 mb-2">
    <div><div class="eyebrow">Billed to</div><p class="mb-0"><strong><?= h($client['display_name']) ?></strong><br><span class="small">Client <?= h($client['number']) ?></span>
      <?php if ($billing): ?><br><span class="small"><?= h($billing['name']) ?><?= $billing['organisation'] ? ', ' . h($billing['organisation']) : '' ?></span><?php endif; ?></p></div>
    <?php if ($case): ?><div><div class="eyebrow">Matter</div><p class="mb-0"><span class="ref"><?= h($case['ref']) ?></span><br><span class="small"><?= h($case['title']) ?></span></p></div><?php endif; ?>
  </div>
  <table class="table">
    <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Tax</th><th class="right">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr><td><?= h($it['description']) ?></td><td class="right num"><?= h(rtrim(rtrim(number_format($it['qty_x100'] / 100, 2), '0'), '.')) ?></td><td class="right num"><?= h(money((int) $it['unit_price'], $cur)) ?></td><td class="right num"><?= h(rtrim(rtrim(number_format($it['vat_bp'] / 100, 2), '0'), '.')) ?>%</td><td class="right num"><?= h(money((int) $it['line_total'], $cur)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="totals num">
    <div><span>Subtotal</span><span><?= h(money((int) $inv['subtotal'], $cur)) ?></span></div>
    <?php if ((int) $inv['discount'] > 0): ?><div><span>Discount</span><span>-<?= h(money((int) $inv['discount'], $cur)) ?></span></div><?php endif; ?>
    <div><span>Tax</span><span><?= h(money((int) $inv['vat'], $cur)) ?></span></div>
    <div class="grand"><span>Total</span><span><?= h(money((int) $inv['total'], $cur)) ?></span></div>
    <?php if ((int) $inv['paid'] !== 0): ?><div><span>Paid</span><span><?= h(money((int) $inv['paid'], $cur)) ?></span></div><div><strong>Balance due</strong><strong><?= h(money((int) $inv['total'] - (int) $inv['paid'], $cur)) ?></strong></div><?php endif; ?>
  </div>
  <?php if ($inv['notes']): ?><p class="small pre mt-3"><?= h($inv['notes']) ?></p><?php endif; ?>
  <hr>
  <?php if (Settings::get('bank_details')): ?><p class="small pre"><strong>Bank details</strong><br><?= h(Settings::get('bank_details')) ?></p><?php endif; ?>
  <p class="small muted pre"><?= h(Settings::get('invoice_footer')) ?></p>
</article>
