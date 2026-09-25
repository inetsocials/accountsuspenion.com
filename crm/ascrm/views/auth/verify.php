<div class="card"><div class="card-b">
  <div class="eyebrow">Step 2 of 2</div>
  <h1>Enter your code</h1>
  <p class="lead">Open your authenticator app and enter the 6-digit code for <?= h(\DR\Core\Settings::get('portal_name')) ?>.</p>
  <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('/login/verify')) ?>">
    <?= csrf_field() ?>
    <label class="field"><span class="sr-only">Authentication code</span><input class="code-input" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="12" autofocus placeholder="000000"></label>
    <button class="btn btn-primary btn-block" type="submit">Verify and sign in</button>
  </form>
  <p class="small muted mt-2">Lost your phone? Enter one of your recovery codes (format XXXXX-XXXXX) in the box above instead.</p>
  <form method="post" action="<?= h(url('/login/cancel')) ?>" class="mt-1"><?= csrf_field() ?><button class="btn btn-link" type="submit">Use a different account</button></form>
</div></div>
