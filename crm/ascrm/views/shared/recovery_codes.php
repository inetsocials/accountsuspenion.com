<div class="page-head"><div><h1>Save your recovery codes</h1><p class="sub">Shown once. Each code signs you in one time if you lose your phone.</p></div></div>
<div class="card">
  <div class="card-b">
    <div class="alert alert-warn"><?= icon('alert') ?><div><strong>Store these somewhere safe now</strong>A password manager is ideal. Anyone with a code and your password can sign in, so keep them private.</div></div>
    <ul class="codes" id="codes"><?php foreach ($codes as $c): ?><li><?= h($c) ?></li><?php endforeach; ?></ul>
    <div class="row">
      <button type="button" class="btn btn-ghost" data-copy="#codes"><?= icon('file') ?>Copy</button>
      <button type="button" class="btn btn-ghost" data-print><?= icon('printer') ?>Print</button>
      <a class="btn btn-primary" href="<?= h(url(\DR\Core\Auth::isStaff($user) ? '/' : '/client')) ?>">I have saved them</a>
    </div>
  </div>
</div>
