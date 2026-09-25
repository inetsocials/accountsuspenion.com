<?php use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head">
  <div><div class="eyebrow"><a href="<?= h(url('/admin/users')) ?>">Users</a></div><h1><?= h($t['name']) ?></h1><p class="sub row"><span class="pill"><?= h(Labels::ROLES[$t['role']]) ?></span> <?= View::badge($t['status'] === 'active' ? 'active' : ($t['status'] === 'invited' ? 'invited' : 'suspended'), ucfirst($t['status'])) ?> <span><?= h($t['email']) ?></span></p></div>
</div>
<div class="grid g-main">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Recent activity</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/admin/audit', ['user' => $t['id']])) ?>">Full audit</a></div>
      <div class="table-wrap"><table class="table table-stack"><thead><tr><th>When</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead><tbody>
        <?php foreach ($audit as $a): ?><tr><td data-l="When" class="nowrap"><?= h(fdate($a['at'], true)) ?></td><td data-l="Action"><?= h(str_replace('_', ' ', $a['action'])) ?></td><td data-l="Entity"><?= h(trim(($a['entity'] ?? '') . ' ' . ($a['entity_id'] ?? ''))) ?></td><td data-l="IP" class="mono small"><?= h($a['ip'] ?? '') ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Active sessions</h2><?php if ($editable && $sessions): ?><form method="post" action="<?= h(url('/admin/users/' . $t['id'] . '/sessions')) ?>" data-confirm="Sign this user out everywhere?"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Sign out everywhere</button></form><?php endif; ?></div>
      <?php if (!$sessions): ?><?php $icon = 'globe'; $heading = 'No active sessions'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($sessions as $s): ?><li><div class="grow"><div class="truncate"><?= h(str_limit($s['user_agent'], 90)) ?></div><div class="meta">IP <?= h($s['ip']) ?>, started <?= h(fdate($s['created_at'], true)) ?>, active <?= h(ago($s['last_seen_at'])) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <?php if ($editable): ?>
    <form class="card" method="post" action="<?= h(url('/admin/users/' . $t['id'])) ?>">
      <?= csrf_field() ?>
      <div class="card-h"><h2>Account</h2></div>
      <div class="card-b">
        <label class="field"><span>Name</span><input type="text" name="name" value="<?= h($t['name']) ?>"></label>
        <?php if (in_array($t['role'], ['master', 'admin', 'lead', 'staff'], true)): ?>
        <label class="field"><span>Role</span><select name="role"><?php foreach ($assignable as $r): ?><option value="<?= h($r) ?>"<?= selected($r === $t['role']) ?>><?= h(Labels::ROLES[$r]) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label class="field"><span>Status</span><select name="status"><?= options(['active' => 'Active', 'suspended' => 'Suspended', 'invited' => 'Invited'], $t['status']) ?></select></label>
        <?php if ($t['locked_until'] && $t['locked_until'] > now()): ?><label class="check"><input type="checkbox" name="unlock" value="1"><span>Unlock (locked after failed attempts until <?= h(fdate($t['locked_until'], true)) ?>)</span></label><?php endif; ?>
        <button class="btn btn-primary btn-block" type="submit">Save</button>
      </div>
    </form>
    <div class="card">
      <div class="card-h"><h2>Security actions</h2></div>
      <div class="card-b stack-sm">
        <?php if ($t['status'] === 'invited'): ?><form method="post" action="<?= h(url('/admin/users/' . $t['id'] . '/resend')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-block" type="submit"><?= icon('mail') ?>Resend invitation</button></form><?php endif; ?>
        <?php if ((int) $t['totp_enabled'] === 1): ?><form method="post" action="<?= h(url('/admin/users/' . $t['id'] . '/reset-2fa')) ?>" data-confirm="Reset two-step verification? Confirm the person's identity in writing first. They will scan a new code at next sign-in." data-danger><?= csrf_field() ?><button class="btn btn-soft-danger btn-block" type="submit"><?= icon('key') ?>Reset two-step verification</button></form><?php endif; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info"><?= icon('lock') ?><div>You cannot change this account<?= (int) $t['id'] === (int) $user['id'] ? '. Use My account for your own settings' : ' with your role' ?>.</div></div>
    <?php endif; ?>
    <?php if ($client): ?><div class="card"><div class="card-b">Client user for <a href="<?= h(url('/clients/' . $client['id'])) ?>"><?= h($client['number'] . ' ' . $client['display_name']) ?></a></div></div><?php endif; ?>
    <?php if ($advises): ?><div class="card"><div class="card-h"><h2>Adviser to</h2></div><ul class="list"><?php foreach ($advises as $c): ?><li><a href="<?= h(url('/clients/' . $c['id'])) ?>"><?= h($c['number'] . ' ' . $c['display_name']) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="card"><div class="card-b small"><dl class="props"><dt>Created</dt><dd><?= h(fdate($t['created_at'], true)) ?></dd><dt>Last sign-in</dt><dd><?= $t['last_login_at'] ? h(fdate($t['last_login_at'], true)) . ' from ' . h((string) $t['last_login_ip']) : 'Never' ?></dd><dt>Two-step</dt><dd><?= (int) $t['totp_enabled'] ? 'Enrolled, ' . count(json_decode((string) $t['recovery_hashes'], true) ?: []) . ' recovery codes left' : 'Not yet enrolled' ?></dd></dl></div></div>
  </div>
</div>
