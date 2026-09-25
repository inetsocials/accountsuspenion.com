<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\View;
$cid = (int) $client['id'];
$lead = Auth::atLeast($user, 'lead');
$tabs = ['overview' => ['Overview', 'home'], 'documents' => ['Vault', 'lock'], 'invoices' => ['Invoices', 'receipt'], 'notes' => ['Private notes', 'pen'], 'activity' => ['Access log', 'pulse']];
?>
<div class="page-head">
  <div>
    <div class="eyebrow">Client <?= h($client['number']) ?></div>
    <h1><?= h($client['display_name']) ?><?= $client['is_codename'] ? ' <span class="pill">Codename</span>' : '' ?></h1>
    <p class="sub row"><?= View::badge($client['status'] === 'active' ? 'active' : 'cancelled', ucfirst($client['status'])) ?> <span class="pill"><?= h(Labels::CLIENT_TYPE[$client['type']] ?? '') ?></span> <span class="pill"><?= h(Labels::RISK[$client['risk_level']] ?? '') ?> risk</span> <span class="vault-note"><?= icon('lock') ?>Vault <span class="mono"><?= h(substr($client['vault_dir'], 0, 8)) ?>&hellip;</span></span></p>
  </div>
  <div class="actions">
    <?php if ($lead): ?><a class="btn btn-ghost" href="<?= h(url('/invoices/new', ['client' => $cid])) ?>"><?= icon('receipt') ?>New invoice</a><a class="btn btn-primary" href="<?= h(url('/cases/new', ['client' => $cid])) ?>"><?= icon('plus') ?>New case</a><?php endif; ?>
  </div>
</div>
<nav class="tabs" aria-label="Client sections">
<?php foreach ($tabs as $k => [$lbl, $ic]): ?><a href="<?= h(url('/clients/' . $cid, ['tab' => $k])) ?>"<?= $tab === $k ? ' class="active" aria-current="page"' : '' ?>><?= icon($ic) ?><?= h($lbl) ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
<div class="grid g-main">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Cases</h2></div>
      <?php if (!$cases): ?><?php $icon = 'briefcase'; $heading = 'No cases you can see'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Case</th><th>Stage</th><th>Status</th><th>Lead</th><th>Activity</th></tr></thead><tbody>
        <?php foreach ($cases as $c): ?><tr><td data-l="Case" class="title-cell"><a class="row-link" href="<?= h(url((in_array($c['status'], ['lead', 'declined'], true) ? '/leads/' : '/cases/') . $c['id'])) ?>"><span class="ref"><?= h($c['ref']) ?></span></a><small><?= h($c['title']) ?></small></td>
        <td data-l="Stage"><?= h(Labels::get($c['stage'])) ?></td><td data-l="Status"><?= View::badge($c['status']) ?></td><td data-l="Lead"><?= h($c['lead_name'] ?? '') ?></td><td data-l="Activity" class="muted nowrap"><?= h(ago($c['last_activity_at'])) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Portal access</h2></div>
      <?php if ($users): ?>
      <ul class="list"><?php foreach ($users as $pu): ?>
        <li><span class="avatar sm"><?= h(initials($pu['name'])) ?></span><div class="grow"><strong><?= h($pu['name']) ?></strong> <span class="pill"><?= h(Labels::ROLES[$pu['role']]) ?></span><div class="meta"><?= h($pu['email']) ?><?= $pu['last_login_at'] ? ', last sign-in ' . h(ago($pu['last_login_at'])) : '' ?></div></div>
          <?= View::badge($pu['status'] === 'active' ? 'active' : ($pu['status'] === 'invited' ? 'invited' : 'suspended'), ucfirst($pu['status'])) ?>
          <?php if ($pu['status'] === 'invited' && $lead): ?><form method="post" action="<?= h(url('/clients/' . $cid . '/users/' . $pu['id'] . '/resend')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-xs" type="submit">Resend</button></form><?php endif; ?>
        </li><?php endforeach; ?></ul>
      <?php else: ?><?php $icon = 'user'; $heading = 'No portal users yet'; $text = 'Invite the client or their adviser below.'; include DR_ROOT . '/views/partials/empty.php'; ?><?php endif; ?>
      <?php if ($lead): ?>
      <form class="card-f form-grid-3" method="post" action="<?= h(url('/clients/' . $cid . '/users')) ?>">
        <?= csrf_field() ?>
        <label class="field mb-0"><span>Name</span><input type="text" name="name" class="input-sm"></label>
        <label class="field mb-0"><span>Email</span><input type="email" name="email" required class="input-sm"></label>
        <label class="field mb-0"><span>Access as</span><select name="role" class="input-sm"><?= options(['client' => 'Client user', 'adviser' => 'Adviser (attorney, accountant, agency)'], 'client') ?></select></label>
        <div class="full mt-1"><button class="btn btn-primary btn-sm" type="submit"><?= icon('mail') ?>Send invitation</button></div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="stack">
    <?php if ($lead): ?>
    <form class="card" method="post" action="<?= h(url('/clients/' . $cid)) ?>">
      <?= csrf_field() ?><input type="hidden" name="section" value="details">
      <div class="card-h"><h2>Details</h2></div>
      <div class="card-b">
        <label class="field"><span>Display name</span><input type="text" name="display_name" value="<?= h($client['display_name']) ?>" required></label>
        <label class="check"><input type="checkbox" name="codename" value="1"<?= checked((bool) $client['is_codename']) ?>><span>Codename</span></label>
        <div class="form-grid">
          <label class="field"><span>Type</span><select name="type"><?= options(Labels::CLIENT_TYPE, $client['type']) ?></select></label>
          <label class="field"><span>Risk</span><select name="risk_level"><?= options(Labels::RISK, $client['risk_level']) ?></select></label>
        </div>
        <label class="field"><span>Status</span><select name="status"><?= options(['active' => 'Active', 'inactive' => 'Inactive'], $client['status']) ?></select></label>
        <button class="btn btn-ghost btn-block" type="submit">Save details</button>
      </div>
    </form>
    <?php endif; ?>
    <div class="card">
      <div class="card-h"><h2>Contacts</h2></div>
      <?php if ($contacts): ?><ul class="list"><?php foreach ($contacts as $ct): ?>
        <li><div class="grow"><strong><?= h($ct['name']) ?></strong> <span class="pill"><?= h(ucfirst($ct['kind'])) ?></span><div class="meta"><?= h($ct['email'] ?? '') ?><?= $ct['organisation'] ? ', ' . h($ct['organisation']) : '' ?></div></div>
        <?php if ($lead): ?><form method="post" action="<?= h(url('/clients/' . $cid . '/contacts/' . $ct['id'] . '/delete')) ?>" data-confirm="Remove this contact?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Remove"><?= icon('trash') ?></button></form><?php endif; ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
      <details class="card-f"><summary class="btn btn-ghost btn-sm">Add contact</summary>
        <form class="mt-2" method="post" action="<?= h(url('/clients/' . $cid . '/contacts')) ?>"><?= csrf_field() ?>
          <label class="field"><span>Name</span><input type="text" name="name" required></label>
          <label class="field"><span>Email</span><input type="email" name="email"></label>
          <label class="field"><span>Company</span><input type="text" name="organisation"></label>
          <label class="field"><span>Role</span><select name="kind"><?= options(['primary' => 'Primary', 'billing' => 'Billing', 'legal' => 'Legal', 'adviser' => 'Adviser', 'other' => 'Other'], 'other') ?></select></label>
          <button class="btn btn-primary btn-sm" type="submit">Add contact</button>
        </form>
      </details>
    </div>
    <?php if ($user['role'] === 'master'): ?>
    <div class="card">
      <div class="card-h"><h2 class="text-danger">Right to erasure</h2></div>
      <form class="card-b" method="post" action="<?= h(url('/clients/' . $cid . '/erase')) ?>" data-confirm="Permanently erase this client? Files are destroyed and the client key is deleted. This cannot be undone." data-danger>
        <?= csrf_field() ?>
        <p class="small">Destroys every file in the vault, deletes the client key, messages, tracker data and contacts. Invoices are kept de-identified for statutory accounting.</p>
        <label class="field"><span>Type <strong class="mono"><?= h($client['number']) ?></strong> to confirm</span><input type="text" name="confirm" required autocomplete="off" class="mono"></label>
        <label class="check"><input type="checkbox" name="force" value="1"><span>Erase anyway if invoices are unpaid</span></label>
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash') ?>Erase client permanently</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'documents'): ?>
<?php $docsBack = url('/clients/' . $cid, ['tab' => 'documents']); ?>
<div class="grid g-side">
  <div class="card">
    <div class="card-h"><h2>Vault contents</h2><span class="muted small"><?= count($docs) ?> files</span></div>
    <?php include DR_ROOT . '/views/staff/_doclist.php'; ?>
  </div>
  <div class="stack">
    <div class="card"><div class="card-h"><h2>Upload to the vault</h2></div><div class="card-b">
      <?php $clientId = $cid; $caseId = null; $staff = true; $cases = null; include DR_ROOT . '/views/partials/uploader.php'; ?>
    </div></div>
    <div class="card"><div class="card-h"><h2>Folders</h2></div><div class="card-b">
      <?php if ($folders): ?><div class="row mb-2"><?php foreach ($folders as $fname): ?><span class="pill"><?= icon('folder') ?><?= h($fname) ?></span><?php endforeach; ?></div><?php endif; ?>
      <form method="post" action="<?= h(url('/folders')) ?>" class="row"><?= csrf_field() ?><input type="hidden" name="client_id" value="<?= $cid ?>"><input type="hidden" name="_back" value="<?= h($docsBack) ?>">
        <input type="text" name="name" placeholder="New folder name" required class="input-sm grow"><button class="btn btn-ghost btn-sm" type="submit">Add</button></form>
      <p class="hint">Folder names are encrypted with the client key.</p>
    </div></div>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<div class="card">
  <?php if (!$invoices): ?><?php $icon = 'receipt'; $heading = 'No invoices yet'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Number</th><th>Case</th><th>Issued</th><th>Due</th><th class="right">Total</th><th class="right">Paid</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($invoices as $i): ?><tr><td data-l="Number"><a class="row-link mono" href="<?= h(url('/invoices/' . $i['id'])) ?>"><?= h($i['number']) ?></a></td><td data-l="Case" class="ref"><?= h($i['ref'] ?? '') ?></td><td data-l="Issued"><?= h(fdate($i['issued_on'])) ?></td><td data-l="Due"><?= h(fdate($i['due_on'])) ?></td>
    <td data-l="Total" class="right num"><?= h(money((int) $i['total'], $i['currency'])) ?></td><td data-l="Paid" class="right num"><?= h(money((int) $i['paid'], $i['currency'])) ?></td><td data-l="Status"><?= View::badge(\DR\Service\Invoices::isOverdue($i) ? 'high' : $i['status'], \DR\Service\Invoices::isOverdue($i) ? 'Overdue' : null) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'notes'): ?>
<form class="card" method="post" action="<?= h(url('/clients/' . $cid)) ?>">
  <?= csrf_field() ?><input type="hidden" name="section" value="notes">
  <div class="card-h"><h2>Private notes</h2><span class="vault-note"><?= icon('lock') ?>Encrypted with the client key, never visible to the client</span></div>
  <div class="card-b"><textarea name="notes" rows="16"<?= $lead ? '' : ' readonly' ?>><?= h($notes) ?></textarea></div>
  <?php if ($lead): ?><div class="card-f"><button class="btn btn-primary" type="submit">Save notes</button></div><?php endif; ?>
</form>

<?php else: ?>
<div class="card">
  <div class="card-h"><h2>Access log</h2><span class="muted small">Every view, download and change for this client</span></div>
  <?php if (!$activity): ?><?php $icon = 'pulse'; $heading = 'No activity yet'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($activity as $a): ?><tr><td data-l="When" class="nowrap"><?= h(fdate($a['at'], true)) ?></td><td data-l="Who"><?= h($a['user_name'] ?? ($a['role'] ?? 'System')) ?></td><td data-l="Action"><?= h(\DR\Service\Cases::describe($a)) ?></td><td data-l="IP" class="mono small"><?= h($a['ip'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>
