<?php use DR\Core\Labels; ?>
<div class="page-head">
  <div><h1>Audit log</h1><p class="sub">Append-only record of sign-ins, access, downloads and changes. No client content is stored here.</p></div>
  <a class="btn btn-ghost" href="<?= h(url('/admin/audit.csv', $q)) ?>"><?= icon('download') ?>Export CSV</a>
</div>
<div class="card">
  <form class="filters" method="get" action="<?= h(url('/admin/audit')) ?>">
    <select name="action" aria-label="Action"><option value="">Any action</option><?php foreach ($actions as $a): ?><option value="<?= h($a) ?>"<?= selected(($q['action'] ?? '') === $a) ?>><?= h(str_replace('_', ' ', $a)) ?></option><?php endforeach; ?></select>
    <select name="user" aria-label="User"><option value="">Any user</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= selected((string) ($q['user'] ?? '') === (string) $u['id']) ?>><?= h($u['name']) ?> (<?= h(Labels::ROLES[$u['role']] ?? '') ?>)</option><?php endforeach; ?></select>
    <input type="date" name="from" value="<?= h($q['from'] ?? '') ?>" aria-label="From">
    <input type="date" name="to" value="<?= h($q['to'] ?? '') ?>" aria-label="To">
    <label class="check mb-0"><input type="checkbox" name="security" value="1"<?= checked(($q['security'] ?? '') === '1') ?>><span>Security events only</span></label>
    <button class="btn btn-ghost btn-sm" type="submit"><?= icon('filter') ?>Apply</button>
  </form>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Details</th></tr></thead>
    <tbody><?php foreach ($rows as $r): $sec = in_array($r['action'], ['login_failed', 'account_locked', 'mfa_failed', 'mfa_locked', 'access_denied', 'sudo_failed', 'upload_rejected'], true); ?>
      <tr class="<?= $sec ? 'emergency' : '' ?>">
        <td data-l="When" class="nowrap"><?= h(fdate($r['at'], true)) ?></td>
        <td data-l="User"><?= h($r['user_name'] ?? ucfirst((string) ($r['role'] ?? 'system'))) ?></td>
        <td data-l="Action"><?= $sec ? '<span class="badge badge-danger badge-plain">' . h(str_replace('_', ' ', $r['action'])) . '</span>' : h(str_replace('_', ' ', $r['action'])) ?></td>
        <td data-l="Entity"><?php if ($r['entity']): ?><a href="<?= h(url('/admin/audit', ['entity' => $r['entity'], 'entity_id' => $r['entity_id']])) ?>"><?= h($r['entity'] . ' ' . $r['entity_id']) ?></a><?php endif; ?></td>
        <td data-l="IP" class="mono small"><?= h($r['ip'] ?? '') ?></td>
        <td data-l="Details" class="mono small break"><?= h(str_limit((string) $r['meta'], 120)) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?= pager($pg, '/admin/audit', $q) ?>
</div>
