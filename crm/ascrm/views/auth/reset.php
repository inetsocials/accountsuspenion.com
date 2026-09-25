<div class="card"><div class="card-b">
  <?php if ($done): ?>
    <h1>Password changed</h1>
    <p class="lead">Every device was signed out. Sign in with your new password and your authenticator code.</p>
    <a class="btn btn-primary btn-block" href="<?= h(url('/login')) ?>">Sign in</a>
  <?php else: ?>
    <h1>Choose a new password</h1>
    <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
    <form method="post" action="<?= h(url('/reset/' . $token)) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>New password</span><input type="password" name="password" required minlength="12" autocomplete="new-password" data-strength></label>
      <label class="field"><span>Repeat new password</span><input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Save password</button>
    </form>
  <?php endif; ?>
</div></div>
