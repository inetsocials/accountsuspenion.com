<div class="card"><div class="card-b">
  <div class="eyebrow">Sensitive action</div>
  <h1>Confirm it is you</h1>
  <p class="lead">Enter your password and a fresh authenticator code. You will not be asked again for ten minutes.</p>
  <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('/sudo')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= h($next) ?>">
    <label class="field"><span>Password</span><input type="password" name="password" required autocomplete="current-password" autofocus></label>
    <label class="field"><span>Authenticator code</span><input class="code-input" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="000000"></label>
    <button class="btn btn-primary btn-block" type="submit">Confirm</button>
  </form>
  <div class="auth-links"><a href="<?= h($next ?: url('/')) ?>">Cancel</a></div>
</div></div>
