<div class="page-head"><div><div class="eyebrow">Website</div><h1>Redirects</h1><p class="sub">Permanent (301) redirects from old URLs, for example posts from the previous WordPress site. They apply immediately, without a rebuild.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <?php if (!$rows): ?><?php $icon = 'link'; $heading = 'No redirects yet'; $text = 'Add every old URL that does not exist on the new site so its search rankings and backlinks carry over.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack">
      <thead><tr><th>Old path</th><th>New address</th><th class="right">Hits</th><th class="right"></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?>
        <tr><td data-l="Old path" class="mono break"><?= h($r['from_path']) ?></td><td data-l="New address" class="mono break"><?= h($r['to_path']) ?></td><td data-l="Hits" class="right num"><?= (int) $r['hits'] ?></td>
        <td data-l="" class="right"><form class="inline" method="post" action="<?= h(url('/cms/redirects/' . $r['id'] . '/delete')) ?>" data-confirm="Remove this redirect?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </div>
  <form class="card" method="post" action="<?= h(url('/cms/redirects')) ?>">
    <?= csrf_field() ?>
    <div class="card-h"><h2>Add a redirect</h2></div>
    <div class="card-b">
      <label class="field"><span>Old path</span><input type="text" name="from_path" required maxlength="255" class="mono" placeholder="/how-to-appeal-amazon-suspension/"></label>
      <label class="field"><span>New address</span><input type="text" name="to_path" required maxlength="255" class="mono" placeholder="/blog/how-to-write-an-amazon-plan-of-action/"></label>
      <p class="hint">Paths only work for URLs that do not exist on the new site. Existing pages always win.</p>
      <button class="btn btn-primary btn-block mt-1" type="submit">Save redirect</button>
    </div>
  </form>
</div>
