<div class="card"><div class="card-b">
  <h1>Reset your password</h1>
  <?php if ($sent): ?>
    <div class="alert alert-ok"><?= icon('mail') ?><div><strong>Check your inbox</strong>If an active account uses that address, a reset link is on its way. It expires in one hour.</div></div>
    <a class="btn btn-ghost btn-block mt-2" href="<?= h(url('/login')) ?>">Back to sign in</a>
  <?php else: ?>
    <p class="lead">Enter your account email. Your authenticator app is still required after a reset.</p>
    <form method="post" action="<?= h(url('/forgot')) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Email</span><input type="email" name="email" required autocomplete="username" autofocus></label>
      <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
    </form>
    <div class="auth-links"><a href="<?= h(url('/login')) ?>">Back to sign in</a></div>
  <?php endif; ?>
</div></div>
