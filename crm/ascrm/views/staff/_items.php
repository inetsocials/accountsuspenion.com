<?php
/* Line items editor. Vars: $items (existing rows or []), $catalog, $currency, $discount, $editable */
$editable = $editable ?? true;
?>
<div data-items data-currency="<?= h($currency) ?>">
  <?php if ($editable && $catalog): ?>
  <div class="row mb-1"><label class="field mb-0 grow"><span class="sr-only">Add from catalog</span><select data-catalog><option value="">Add a service from the catalog</option>
    <?php foreach ($catalog as $c): ?><option value="<?= (int) $c['id'] ?>" data-name="<?= h($c['name']) ?>" data-price="<?= h(number_format($c['unit_price'] / 100, 2, '.', '')) ?>" data-vat="<?= h(rtrim(rtrim(number_format($c['vat_bp'] / 100, 2, '.', ''), '0'), '.')) ?>"><?= h($c['name']) ?> (<?= h(money((int) $c['unit_price'], $currency)) ?>)</option><?php endforeach; ?>
  </select></label></div>
  <?php endif; ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Tax %</th><th class="right">Net</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><input type="text" name="item_desc[]" value="<?= h($it['description']) ?>" class="input-sm" required></td>
        <td><input type="number" name="item_qty[]" value="<?= h(rtrim(rtrim(number_format($it['qty_x100'] / 100, 2, '.', ''), '0'), '.')) ?>" step="0.01" min="0.01" class="input-sm right"></td>
        <td><input type="number" name="item_unit[]" value="<?= h(number_format($it['unit_price'] / 100, 2, '.', '')) ?>" step="0.01" class="input-sm right"></td>
        <td><input type="number" name="item_vat[]" value="<?= h(rtrim(rtrim(number_format($it['vat_bp'] / 100, 2, '.', ''), '0'), '.')) ?>" step="0.01" min="0" max="100" class="input-sm right"></td>
        <td class="right num line-total"></td>
        <td class="right"><button type="button" class="btn btn-link btn-xs" data-remove-row aria-label="Remove line"><?= icon('x') ?></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <template><tr>
    <td><input type="text" name="item_desc[]" class="input-sm" placeholder="Service description" required></td>
    <td><input type="number" name="item_qty[]" value="1" step="0.01" min="0.01" class="input-sm right"></td>
    <td><input type="number" name="item_unit[]" value="" step="0.01" class="input-sm right" placeholder="0.00"></td>
    <td><input type="number" name="item_vat[]" value="0" step="0.01" min="0" max="100" class="input-sm right"></td>
    <td class="right num line-total"></td>
    <td class="right"><button type="button" class="btn btn-link btn-xs" data-remove-row aria-label="Remove line"><?= icon('x') ?></button></td>
  </tr></template>
  <div class="row-between mt-1">
    <button type="button" class="btn btn-ghost btn-sm" data-add-row><?= icon('plus') ?>Add line</button>
    <div class="num small">
      <div class="row-between"><span class="muted">Subtotal</span>&nbsp;&nbsp;<strong data-t="sub"></strong></div>
      <div class="row-between"><span class="muted">Discount</span>&nbsp;&nbsp;<strong data-t="disc"></strong></div>
      <div class="row-between"><span class="muted">Tax</span>&nbsp;&nbsp;<strong data-t="vat"></strong></div>
      <div class="row-between"><span>Total</span>&nbsp;&nbsp;<strong data-t="total"></strong></div>
    </div>
  </div>
</div>
