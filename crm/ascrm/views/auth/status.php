<div class="card"><div class="card-b">
  <h1>Check a case</h1>
  <?php if ($sent): ?>
    <div class="alert alert-ok"><?= icon('mail') ?><div><strong>Check your inbox</strong>If the reference and email match a request, a private status link has been sent to that email. It works for 30 minutes.</div></div>
    <a class="btn btn-ghost btn-block mt-2" href="<?= h(url('/login')) ?>">Client sign in</a>
  <?php else: ?>
    <p class="lead">Enter the reference you received (for example AS-1A2B3C4D) and the email you used. We will send a one-time status link to that email.</p>
    <form method="post" action="<?= h(url('/status')) ?>">
      <?= csrf_field() ?>
      <label class="field"><span>Case reference</span><input type="text" name="ref" required pattern="[Aa][Ss]-[A-Fa-f0-9]{8}" class="mono" value="<?= h($ref) ?>" placeholder="AS-XXXXXXXX" autocapitalize="characters"></label>
      <label class="field"><span>Email used for the request</span><input type="email" name="email" required autocomplete="email"></label>
      <button class="btn btn-primary btn-block" type="submit">Send status link</button>
    </form>
    <div class="auth-links"><a href="<?= h(url('/login')) ?>">Client sign in</a></div>
  <?php endif; ?>
</div></div>
