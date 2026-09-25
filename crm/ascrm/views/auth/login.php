<div class="card"><div class="card-b">
  <h1>Sign in</h1>
  <p class="lead">Client, adviser and staff access to the secure case vault.</p>
  <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('/login')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="username" value="<?= h($email) ?>" autofocus></label>
    <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('lock') ?>Continue</button>
  </form>
  <div class="auth-links"><a href="<?= h(url('/forgot')) ?>">Forgotten password</a><a href="<?= h(url('/status')) ?>">Check a case reference</a></div>
</div></div>
<p class="small muted center mt-2">New client? Your portal invitation arrives by email once a case lead accepts your request.</p>
