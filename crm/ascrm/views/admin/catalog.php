<div class="page-head"><div><h1>Service catalog</h1><p class="sub">Reusable invoice lines. Prices exclude tax.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <?php if (!$rows): ?><?php $icon = 'list'; $heading = 'No services yet'; $text = 'Add your standard packages, for example Suspension Case Review or Plan of Action.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Service</th><th class="right">Unit price</th><th class="right">Tax</th><th>Status</th><th></th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?><tr><td data-l="Service" class="title-cell"><?= h($r['name']) ?><small><?= h(str_limit((string) $r['description'], 100)) ?></small></td><td data-l="Unit price" class="right num"><?= h(money((int) $r['unit_price'])) ?></td><td data-l="Tax" class="right num"><?= h(rtrim(rtrim(number_format($r['vat_bp'] / 100, 2), '0'), '.')) ?>%</td>
        <td data-l="Status"><?= $r['active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-neutral">Hidden</span>' ?></td>
        <td class="right"><button type="button" class="btn btn-ghost btn-xs" data-fill="#cat-form" data-title="Edit service" data-values="<?= h(json_encode(['id' => $r['id'], 'name' => $r['name'], 'description' => $r['description'], 'unit_price' => number_format($r['unit_price'] / 100, 2, '.', ''), 'vat' => rtrim(rtrim(number_format($r['vat_bp'] / 100, 2, '.', ''), '0'), '.'), 'active' => (int) $r['active']])) ?>"><?= icon('pen') ?>Edit</button></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <form class="card" id="cat-form" method="post" action="<?= h(url('/admin/catalog')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <div class="card-h"><h2 data-form-title>Add service</h2></div>
    <div class="card-b">
      <label class="field"><span>Name</span><input type="text" name="name" required></label>
      <label class="field"><span>Description</span><textarea name="description" rows="3"></textarea></label>
      <div class="form-grid"><label class="field"><span>Unit price</span><input type="number" name="unit_price" step="0.01" min="0" required></label><label class="field"><span>Tax %</span><input type="number" name="vat" step="0.01" min="0" max="100" value="0"></label></div>
      <label class="check"><input type="checkbox" name="active" value="1" checked><span>Available for new invoices</span></label>
      <button class="btn btn-primary btn-block mt-1" type="submit">Save service</button>
    </div>
  </form>
</div>
