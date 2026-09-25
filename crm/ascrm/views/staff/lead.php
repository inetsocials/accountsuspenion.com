<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\Settings;
use DR\Core\View;
use DR\Service\Cases;
$checks = $screening['checks'] ?? [];
$allChecked = !array_diff(array_keys(Labels::SCREENING), $checks);
$canAct = Auth::atLeast($user, 'lead');
$open = $case['status'] === 'lead';
$window = Settings::get($case['priority'] === 'emergency' ? 'priority_response_window' : 'consult_response_window');
$due = strtotime($case['created_at']) + ($case['priority'] === 'emergency' ? 14400 : 86400);
?>
<div class="page-head">
  <div>
    <div class="eyebrow">Lead</div>
    <h1><span class="ref"><?= h($case['ref']) ?></span> <?= h($case['title']) ?></h1>
    <p class="sub row"><?= View::badge($case['priority']) ?> <?= View::badge($case['status']) ?> <span class="pill"><?= h(Labels::SOURCES[$case['source']] ?? '') ?></span> <span>Received <?= h(fdate($case['created_at'], true)) ?></span></p>
  </div>
  <?php if ($open && $canAct && (int) $case['lead_user_id'] !== (int) $user['id']): ?>
  <form method="post" action="<?= h(url('/leads/' . $case['id'] . '/claim')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('user') ?>Claim as case lead</button></form>
  <?php endif; ?>
</div>

<?php if ($open): ?>
<div class="alert <?= time() > $due ? 'alert-danger' : 'alert-info' ?> mb-2"><?= icon('clock') ?><div><strong>Reply <?= h($window) ?></strong><?= time() > $due ? 'The committed response window has passed. Reply in writing now.' : 'Target reply by ' . h(fdate(date('Y-m-d H:i:s', $due), true)) . '. Reply in writing. Never ask for passwords or one-time codes.' ?></div></div>
<?php else: ?>
<div class="alert alert-warn mb-2"><?= icon('alert') ?><div><strong>Declined</strong><span class="pre"><?= h((string) $case['decline_reason']) ?></span></div></div>
<?php endif; ?>

<div class="grid g-main">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Intake (decrypted for you, access logged)</h2><span class="vault-note"><?= icon('lock') ?>Sealed at rest</span></div>
      <div class="card-b">
        <?php if (!$intake): ?><p class="muted">The intake contents were purged under the retention schedule.</p><?php else: ?>
        <dl class="props">
          <dt>Name</dt><dd><?= h(($intake['name'] ?? '') ?: 'Not given') ?></dd>
          <dt>Reply email</dt><dd><?= h($intake['email'] ?? '') ?></dd>
          <dt>Platform</dt><dd><?= h(Labels::PLATFORMS[$intake['platform'] ?? ''] ?? '') ?><?= !empty($intake['platform_other']) ? ': ' . h($intake['platform_other']) : '' ?></dd>
          <dt>Issue</dt><dd><?= h(Labels::ISSUES[$intake['issue'] ?? ''] ?? '') ?></dd>
          <dt>Appeals so far</dt><dd><?= h(Labels::HISTORY[$intake['history'] ?? ''] ?? '') ?></dd>
          <dt>Urgency</dt><dd><?= h(Labels::URGENCY_IN[$intake['urgency'] ?? ''] ?? '') ?></dd>
          <?php if (!empty($intake['score'])): ?><dt>Readiness Score</dt><dd><?= h((string) $intake['score']) ?></dd><?php endif; ?>
          <dt>Submitted from</dt><dd><?= h(($intake['page'] ?? '') ?: 'Website') ?></dd>
        </dl>
        <h3 class="mt-2">What they told us</h3>
        <div class="nda-doc"><?= h(($intake['details'] ?? '') ?: 'No details given.') ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($existingUser || $related): ?>
    <div class="card">
      <div class="card-h"><h2>Matches</h2></div>
      <div class="card-b">
        <?php if ($existingUser): ?><p><?= icon('user') ?> This email already has a <?= h(Labels::ROLES[$existingUser['role']] ?? '') ?> account<?= $existingUser['client_name'] ? ' on client file <a href="' . h(url('/clients/' . $existingUser['client_id'])) . '">' . h($existingUser['client_number'] . ' ' . $existingUser['client_name']) . '</a>' : '' ?>. Accept into the existing client file to keep one vault.</p><?php endif; ?>
        <?php if ($related): ?><p class="mb-1">Earlier requests from the same email:</p><div class="row"><?php foreach ($related as $r): ?><a class="pill" href="<?= h(url(in_array($r['status'], ['lead', 'declined'], true) ? '/leads/' . $r['id'] : '/cases/' . $r['id'])) ?>"><?= h($r['ref']) ?> <?= h(Labels::get($r['status'])) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-h"><h2>Activity</h2></div>
      <div class="card-b"><ul class="timeline"><?php foreach ($activity as $a): ?><li><?= h(Cases::describe($a)) ?><?= $a['user_name'] ? ' by ' . h($a['user_name']) : '' ?><span class="when"><?= h(fdate($a['at'], true)) ?></span></li><?php endforeach; ?></ul></div>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>1. Ethics and risk screening</h2><?= $allChecked ? '<span class="badge badge-ok">Complete</span>' : '<span class="badge badge-warn">Required</span>' ?></div>
      <form class="card-b" method="post" action="<?= h(url('/leads/' . $case['id'] . '/screening')) ?>">
        <?= csrf_field() ?>
        <?php foreach (Labels::SCREENING as $k => $label): ?>
          <label class="check"><input type="checkbox" name="checks[]" value="<?= h($k) ?>"<?= checked(in_array($k, $checks, true)) ?><?= ($open && $canAct) ? '' : ' disabled' ?>><span><?= h($label) ?></span></label>
        <?php endforeach; ?>
        <label class="field mt-1"><span>Screening notes</span><textarea name="notes" rows="3"<?= ($open && $canAct) ? '' : ' disabled' ?>><?= h($screening['notes'] ?? '') ?></textarea></label>
        <?php if (!empty($screening['by_name'])): ?><p class="hint">Last saved by <?= h($screening['by_name']) ?>, <?= h(fdate($screening['at'], true)) ?></p><?php endif; ?>
        <?php if ($open && $canAct): ?><button class="btn btn-ghost btn-block" type="submit">Save screening</button><?php endif; ?>
      </form>
    </div>

    <?php if ($open && $canAct): ?>
    <div class="card">
      <div class="card-h"><h2>2. Accept and open client file</h2></div>
      <form class="card-b" method="post" action="<?= h(url('/leads/' . $case['id'] . '/accept')) ?>" data-confirm="Accept this lead? A client file and a new encrypted vault will be created.">
        <?= csrf_field() ?>
        <?php if ($existingUser && $existingUser['client_id']): ?>
          <label class="check"><input type="radio" name="client_mode" value="existing" checked><span>Add to existing client <?= h($existingUser['client_number'] . ' ' . $existingUser['client_name']) ?></span></label>
          <input type="hidden" name="existing_client_id" value="<?= (int) $existingUser['client_id'] ?>">
          <label class="check"><input type="radio" name="client_mode" value="new"><span>Open a separate new client file</span></label>
        <?php else: ?>
          <input type="hidden" name="client_mode" value="new">
        <?php endif; ?>
        <label class="field mt-1"><span>Client display name</span><input type="text" name="display_name" value="<?= h($intake['name'] ?? '') ?>" placeholder="Name or codename"></label>
        <label class="check"><input type="checkbox" name="codename" value="1"><span>This is a codename (hide the real name in lists)</span></label>
        <div class="form-grid">
          <label class="field"><span>Type</span><select name="client_type"><?= options(Labels::CLIENT_TYPE, 'individual') ?></select></label>
          <label class="field"><span>Risk level</span><select name="risk_level"><?= options(Labels::RISK, ($intake['urgency'] ?? '') === 'revenue' ? 'elevated' : 'standard') ?></select></label>
        </div>
        <label class="field"><span>Case title</span><input type="text" name="title" value="<?= h($case['title']) ?>"></label>
        <label class="field"><span>Case lead</span><select name="lead_user_id"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'] === (int) ($case['lead_user_id'] ?: $user['id'])) ?>><?= h($s['name']) ?> (<?= h(Labels::ROLES[$s['role']]) ?>)</option><?php endforeach; ?></select></label>
        <label class="check"><input type="checkbox" name="nda_required" value="1" checked><span>Client must sign the engagement agreement before messaging or uploading</span></label>
        <label class="check"><input type="checkbox" name="invite" value="1" checked><span>Email a portal invitation to <?= h($intake['email'] ?? '') ?></span></label>
        <button class="btn btn-primary btn-block mt-1" type="submit"<?= $allChecked ? '' : ' disabled' ?>><?= icon('check') ?>Accept lead</button>
        <?php if (!$allChecked): ?><p class="hint center">Complete and save every screening check first.</p><?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="card-h"><h2>Or decline</h2></div>
      <form class="card-b" method="post" action="<?= h(url('/leads/' . $case['id'] . '/decline')) ?>" data-confirm="Decline this lead?" data-danger>
        <?= csrf_field() ?>
        <label class="field"><span>Internal reason</span><textarea name="reason" rows="3" required placeholder="For example: wants help opening a new account to get around the ban"></textarea></label>
        <label class="check"><input type="checkbox" name="notify" value="1" checked><span>Send a courteous written decline (no reasons given)</span></label>
        <button class="btn btn-soft-danger btn-block" type="submit">Decline lead</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
