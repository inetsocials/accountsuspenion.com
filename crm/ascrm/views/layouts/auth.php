<?php
use DR\Core\Config;
use DR\Core\Settings;
$org = Config::installed() ? Settings::get('org_name') : 'AccountSuspension.com';
include DR_ROOT . '/views/partials/head.php';
?>
<body>
<?php include DR_ROOT . '/views/partials/icons.php'; ?>
<div class="auth-body">
  <section class="auth-art" aria-hidden="true">
    <img src="<?= h(asset('img/logo-light.png')) ?>" alt="" width="273" height="30">
    <h2>Your account case, handled in private.</h2>
    <p>The secure case vault for <?= h($org) ?> clients and advisers. Written communication only, end to end.</p>
    <ul class="auth-points">
      <li><?= icon('lock') ?><span>Every client has a private vault with its own encryption key. Files are encrypted the moment they arrive.</span></li>
      <li><?= icon('shield') ?><span>Two-step sign-in with an authenticator app is compulsory for every account.</span></li>
      <li><?= icon('pulse') ?><span>Every view, download and change is recorded in the audit trail.</span></li>
      <li><?= icon('lock') ?><span>We never ask for your passwords or authentication codes.</span></li>
    </ul>
    <p class="fine"><?= h(Config::installed() ? (Settings::get('legal_name') ?: Settings::get('org_name')) : 'AccountSuspension.com') ?>. Independent, not affiliated with any platform.</p>
  </section>
  <main class="auth-main" id="main">
    <div class="auth-card<?= !empty($wide) ? ' auth-wide' : '' ?>">
      <div class="auth-mobile-logo"><img src="<?= h(asset('img/logo.png')) ?>" alt="<?= h($org) ?>" width="236" height="26"></div>
<?= $content ?>
    </div>
  </main>
</div>
<?php include DR_ROOT . '/views/partials/flashes.php'; ?>
<dialog id="confirm-dialog"><div class="dlg-b"><h2>Please confirm</h2><p id="confirm-text" class="muted"></p></div><div class="dlg-f"><button type="button" class="btn btn-ghost" id="confirm-cancel">Cancel</button><button type="button" class="btn btn-primary" id="confirm-ok">Confirm</button></div></dialog>
</body>
</html>
