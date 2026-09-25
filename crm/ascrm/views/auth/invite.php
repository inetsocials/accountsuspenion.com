<div class="card"><div class="card-b">
  <div class="eyebrow">Welcome</div>
  <h1>Create your secure access</h1>
  <p class="lead">You were invited as <strong><?= h(\DR\Core\Labels::ROLES[$invitee['role']] ?? '') ?></strong> with <?= h($invitee['email']) ?>. Next you will connect an authenticator app.</p>
  <?php if ($error): ?><div class="alert alert-danger mb-2" role="alert"><?= icon('alert') ?><div><?= h($error) ?></div></div><?php endif; ?>
  <form method="post" action="<?= h(url('/invite/' . $token)) ?>">
    <?= csrf_field() ?>
    <label class="field"><span>Your name</span><input type="text" name="name" required value="<?= h($invitee['name']) ?>" autocomplete="name"></label>
    <label class="field"><span>Choose a password</span><input type="password" name="password" required minlength="12" autocomplete="new-password" data-strength><span class="hint">At least 12 characters. A short sentence works well.</span></label>
    <label class="field"><span>Repeat password</span><input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>
    <label class="check"><input type="checkbox" name="accept" value="1" required><span>I will keep my sign-in details private and understand the portal is the only channel for case communication. <?= h(\DR\Core\Settings::get('org_name')) ?> will never ask for my password or authentication codes.</span></label>
    <button class="btn btn-primary btn-block mt-1" type="submit">Continue to two-step setup</button>
  </form>
</div></div>
