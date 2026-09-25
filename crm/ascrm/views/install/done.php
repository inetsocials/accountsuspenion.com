<div class="card"><div class="card-b">
  <div class="eyebrow">Installed</div>
  <h1>AS Case Vault is ready</h1>
  <p class="lead">Sign in with the master admin account. You will connect an authenticator app on first sign-in.</p>
  <ol class="small">
    <li>Download <code>ascrm/keys/master.key</code> and store it offline (password manager or encrypted USB).</li>
    <li>Add a cron job in hPanel (every 15 minutes): <code>php <?= h(DR_ROOT) ?>/cli/cron.php</code></li>
    <li>Or call this private URL from an external scheduler: <code class="break"><?= h(\DR\Core\App::absoluteUrl('/cron/' . $cron)) ?></code></li>
    <li>Set the From address and alert emails under Settings.</li>
  </ol>
  <a class="btn btn-primary btn-block mt-2" href="<?= h(url('/login')) ?>">Sign in</a>
</div></div>
