<?php use DR\Core\Labels; use DR\Core\View; ?>
<div class="page-head"><div><h1>Users and roles</h1><p class="sub">Staff accounts. Client and adviser access is managed from each client file.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <form class="filters" method="get" action="<?= h(url('/admin/users')) ?>">
      <input class="grow" type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="Name or email" aria-label="Search users">
      <select name="role" data-autosubmit aria-label="Role"><?= options(Labels::ROLES, $filters['role'], true, 'All staff roles') ?></select>
      <select name="status" data-autosubmit aria-label="Status"><?= options(['active' => 'Active', 'invited' => 'Invited', 'suspended' => 'Suspended'], $filters['status'], true, 'Any status') ?></select>
    </form>
    <div class="table-wrap"><table class="table table-stack">
      <thead><tr><th>Name</th><th>Role</th><th>Two-step</th><th>Last sign-in</th><th>Status</th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?>
        <tr><td data-l="Name" class="title-cell"><a class="row-link" href="<?= h(url('/admin/users/' . $r['id'])) ?>"><?= h($r['name']) ?></a><small><?= h($r['email']) ?><?= $r['client_name'] ? ' &middot; ' . h($r['client_name']) : '' ?></small></td>
          <td data-l="Role"><span class="pill"><?= h(Labels::ROLES[$r['role']] ?? $r['role']) ?></span></td>
          <td data-l="Two-step"><?= (int) $r['totp_enabled'] ? View::badge('active', 'On') : View::badge('draft', 'Pending') ?></td>
          <td data-l="Last sign-in" class="muted nowrap"><?= $r['last_login_at'] ? h(ago($r['last_login_at'])) : 'Never' ?></td>
          <td data-l="Status"><?= View::badge($r['status'] === 'active' ? 'active' : ($r['status'] === 'invited' ? 'invited' : 'suspended'), ucfirst($r['status'])) ?><?= $r['locked_until'] && $r['locked_until'] > now() ? ' ' . View::badge('high', 'Locked') : '' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?= pager($pg, '/admin/users', array_filter($filters)) ?>
  </div>
  <form class="card" id="invite-form" method="post" action="<?= h(url('/admin/users')) ?>">
    <?= csrf_field() ?>
    <div class="card-h"><h2>Invite a team member</h2></div>
    <div class="card-b">
      <label class="field"><span>Full name</span><input type="text" name="name" required></label>
      <label class="field"><span>Work email</span><input type="email" name="email" required></label>
      <label class="field"><span>Role</span><select name="role"><?php foreach ($assignable as $r): ?><option value="<?= h($r) ?>"<?= selected($r === 'staff') ?>><?= h(Labels::ROLES[$r]) ?></option><?php endforeach; ?></select></label>
      <div class="small muted mb-2">
        <strong>Staff</strong> works on assigned cases. <strong>Case Lead</strong> screens leads, accepts clients and runs cases. <strong>Admin</strong> sees everything, manages users, payments and the audit log. <strong>Master Admin</strong> also controls settings, keys, backups and erasure.
      </div>
      <button class="btn btn-primary btn-block" type="submit"><?= icon('mail') ?>Send invitation</button>
    </div>
  </form>
</div>
