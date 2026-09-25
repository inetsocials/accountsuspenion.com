<div class="card"><div class="card-b">
  <div class="eyebrow">First-time setup</div>
  <h1>Install AS Case Vault</h1>
  <p class="lead">This runs once. Afterwards the installer is switched off automatically.</p>
  <ul class="req">
  <?php foreach ($reqs as [$label, $ok, $detail, $required]): ?>
    <li><span class="<?= $ok ? 'ok' : ($required ? 'bad' : 'soft') ?>"><?= icon($ok ? 'check' : 'alert') ?></span><strong><?= h($label) ?></strong><span class="muted small"><?= h($detail) ?></span></li>
  <?php endforeach; ?>
  </ul>
  <?php if ($error): ?><div class="alert alert-danger mb-2"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('/install')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <label class="field"><span>Setup key</span><input type="text" name="setup_key" required class="mono" spellcheck="false">
      <span class="hint">Open <code>ascrm/config/setup.key</code> in the Hostinger File Manager and paste its contents. This proves you control the server.</span></label>
    <label class="field"><span>Portal address</span><input type="url" name="app_url" required value="<?= h($in['app_url'] ?? '') ?>"><span class="hint">For example https://accountsuspension.com/portal</span></label>
    <h3 class="mt-2">Database</h3>
    <label class="field"><span>Type</span><select name="driver"><?= options(['mysql' => 'MySQL or MariaDB (recommended on Hostinger)', 'sqlite' => 'SQLite file (small teams, testing)'], $in['driver'] ?? 'mysql') ?></select></label>
    <div class="form-grid">
      <label class="field"><span>Host</span><input type="text" name="db_host" value="<?= h($in['db_host'] ?? 'localhost') ?>"></label>
      <label class="field"><span>Port</span><input type="text" name="db_port" value="<?= h($in['db_port'] ?? '3306') ?>" inputmode="numeric"></label>
      <label class="field"><span>Database name</span><input type="text" name="db_name" value="<?= h($in['db_name'] ?? '') ?>"></label>
      <label class="field"><span>Database user</span><input type="text" name="db_user" value="<?= h($in['db_user'] ?? '') ?>"></label>
      <label class="field full"><span>Database password</span><input type="password" name="db_pass" autocomplete="new-password"></label>
    </div>
    <h3 class="mt-2">Master admin</h3>
    <div class="form-grid">
      <label class="field"><span>Full name</span><input type="text" name="name" required value="<?= h($in['name'] ?? '') ?>"></label>
      <label class="field"><span>Email</span><input type="email" name="email" required value="<?= h($in['email'] ?? '') ?>"></label>
      <label class="field full"><span>Password</span><input type="password" name="password" required minlength="12" autocomplete="new-password" data-strength></label>
    </div>
    <div class="alert alert-info mb-2"><?= icon('key') ?><div>A new master encryption key will be written to <code>ascrm/keys/master.key</code>. Download a copy and keep it offline. Without it, encrypted data and backups cannot be recovered.</div></div>
    <button class="btn btn-primary btn-block" type="submit">Install</button>
  </form>
</div></div>
