<div class="card"><div class="card-b">
  <div class="eyebrow">Required for every account</div>
  <h1>Set up two-step verification</h1>
  <p class="lead">Protect your case file with an authenticator app such as Google Authenticator, Microsoft Authenticator, 1Password or Authy.</p>
  <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <div class="qr-box">
    <?= $qr ?>
    <div>
      <ol class="small">
        <li>In your authenticator app, add an account and scan this code.</li>
        <li>Cannot scan? Choose "enter a setup key" and type:</li>
      </ol>
      <code class="secret" id="totp-secret"><?= h($secret) ?></code>
      <button type="button" class="btn btn-ghost btn-xs mt-1" data-copy="#totp-secret">Copy key</button>
    </div>
  </div>
  <form method="post" action="<?= h(url('/login/setup')) ?>">
    <?= csrf_field() ?>
    <label class="field"><span>Enter the 6-digit code the app now shows</span><input class="code-input" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="000000"></label>
    <button class="btn btn-primary btn-block" type="submit">Confirm and continue</button>
  </form>
  <form method="post" action="<?= h(url('/login/cancel')) ?>" class="mt-1"><?= csrf_field() ?><button class="btn btn-link" type="submit">Cancel and sign out</button></form>
</div></div>
