<?php use DR\Core\Labels; ?>
<div class="page-head"><div><h1>My account and security</h1><p class="sub"><?= h(Labels::ROLES[$user['role']] ?? '') ?>, <?= h($user['email']) ?></p></div></div>
<div class="grid g-2">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Profile</h2></div>
      <form class="card-b" method="post" action="<?= h(url('/account/profile')) ?>">
        <?= csrf_field() ?>
        <label class="field"><span>Name</span><input type="text" name="name" required value="<?= h($user['name']) ?>"></label>
        <label class="field"><span>Email</span><input type="email" value="<?= h($user['email']) ?>" disabled><span class="hint">To change your email, ask your case lead in writing.</span></label>
        <button class="btn btn-primary" type="submit">Save</button>
      </form>
    </div>
    <div class="card">
      <div class="card-h"><h2>Change password</h2></div>
      <form class="card-b" method="post" action="<?= h(url('/account/password')) ?>">
        <?= csrf_field() ?>
        <label class="field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
        <label class="field"><span>New password</span><input type="password" name="password" required minlength="12" autocomplete="new-password" data-strength></label>
        <label class="field"><span>Repeat new password</span><input type="password" name="password2" required minlength="12" autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Change password</button>
      </form>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Two-step verification</h2><span class="badge badge-ok">On</span></div>
      <div class="card-b">
        <p>Your account is protected by an authenticator app. You have <strong><?= (int) $codesLeft ?></strong> unused recovery codes.</p>
        <div class="row">
          <form method="post" action="<?= h(url('/account/recovery')) ?>" data-confirm="Generate new recovery codes? Your old codes will stop working."><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('refresh') ?>New recovery codes</button></form>
          <form method="post" action="<?= h(url('/account/2fa/reset')) ?>" data-confirm="Move to a new phone? You will be signed out and asked to scan a new code at next sign-in." data-danger><?= csrf_field() ?><button class="btn btn-soft-danger btn-sm" type="submit"><?= icon('key') ?>Move to a new phone</button></form>
        </div>
        <p class="hint mt-1">Both actions ask for your password and a current code first.</p>
      </div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Signed-in devices</h2>
        <?php if (count($sessions) > 1): ?><form method="post" action="<?= h(url('/account/sessions/revoke')) ?>" data-confirm="Sign out every other device?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Sign out others</button></form><?php endif; ?>
      </div>
      <ul class="list">
        <?php foreach ($sessions as $s): ?>
        <li>
          <span class="file-ico"><?= icon('globe') ?></span>
          <div class="grow"><div class="truncate"><?= h(str_limit($s['user_agent'] ?: 'Unknown browser', 80)) ?></div><div class="meta">IP <?= h($s['ip']) ?>, active <?= h(ago($s['last_seen_at'])) ?></div></div>
          <?php if ($s['id'] === $current): ?><span class="badge badge-ok">This device</span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if (in_array($user['role'], ['client', 'adviser'], true)): ?>
    <div class="card">
      <div class="card-h"><h2>Your data</h2></div>
      <div class="card-b">
        <p>Download a copy of your case records, messages, appeal tracker, held funds, invoices and documents as a ZIP file.</p>
        <a class="btn btn-ghost btn-sm" href="<?= h(url('/client/export')) ?>"><?= icon('download') ?>Download my data</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
