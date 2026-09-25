<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\View;
use DR\Service\Cases;

$id = (int) $case['id'];
$isLead = Auth::atLeast($user, 'admin') || (int) $case['lead_user_id'] === (int) $user['id'];
$tabs = [
    'overview' => ['Overview', 'home', null], 'messages' => ['Messages', 'message', $unread ?: null], 'appeals' => ['Appeals', 'target', $counts['targets'] ?: null],
    'funds' => ['Held funds', 'receipt', $counts['funds'] ?: null], 'documents' => ['Vault', 'lock', $counts['docs'] ?: null], 'work' => ['Tasks', 'check-square', $counts['tasks'] ?: null],
    'invoices' => ['Invoices', 'receipt', null], 'activity' => ['Activity', 'pulse', null],
];
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><a href="<?= h(url('/clients/' . $client['id'])) ?>"><?= h($client['number'] . ' ' . $client['display_name']) ?></a></div>
    <h1><span class="ref"><?= h($case['ref']) ?></span> <?= h($case['title']) ?></h1>
    <p class="sub row"><?= View::badge($case['status']) ?> <?= View::badge($case['priority']) ?> <span class="pill"><?= h(Labels::STAGES[$case['stage']] ?? '') ?></span>
      <?php if ((int) $case['nda_required'] === 1): ?><span class="pill"><?= icon($case['nda_signed_at'] ? 'check' : 'clock') ?>Agreement <?= $case['nda_signed_at'] ? 'signed ' . h(fdate($case['nda_signed_at'])) : 'awaiting signature' ?></span><?php endif; ?>
      <span class="muted">Lead: <?= h($lead['name'] ?? 'Unassigned') ?></span></p>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="<?= h(url('/cases/' . $id, ['tab' => 'messages'])) ?>"><?= icon('message') ?>Message client</a>
    <?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-ghost" href="<?= h(url('/invoices/new', ['client' => $client['id'], 'case' => $id])) ?>"><?= icon('receipt') ?>Invoice</a><?php endif; ?>
  </div>
</div>

<nav class="tabs" aria-label="Case sections">
<?php foreach ($tabs as $k => [$lbl, $ic, $cnt]): ?><a href="<?= h(url('/cases/' . $id, ['tab' => $k])) ?>"<?= $tab === $k ? ' class="active" aria-current="page"' : '' ?>><?= icon($ic) ?><?= h($lbl) ?><?php if ($cnt): ?><span class="count"><?= (int) $cnt ?></span><?php endif; ?></a><?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
<div class="card mb-2"><div class="card-b"><?php $stage = $case['stage']; $status = $case['status']; include DR_ROOT . '/views/partials/stepper.php'; ?></div></div>
<div class="grid g-main">
  <div class="stack">
    <form class="card" method="post" action="<?= h(url('/cases/' . $id)) ?>">
      <?= csrf_field() ?>
      <div class="card-h"><h2>Case details</h2><button class="btn btn-primary btn-sm" type="submit">Save</button></div>
      <div class="card-b form-grid">
        <label class="field full"><span>Title</span><input type="text" name="title" value="<?= h($case['title']) ?>" required></label>
        <label class="field"><span>Status</span><select name="status"><?= options(array_diff_key(Labels::CASE_STATUS, ['lead' => 1, 'declined' => 1]), $case['status']) ?></select></label>
        <label class="field"><span>Stage</span><select name="stage"><?= options(Labels::STAGES, $case['stage']) ?></select></label>
        <label class="field"><span>Priority</span><select name="priority"><?= options(Labels::PRIORITY, $case['priority']) ?></select></label>
        <label class="field"><span>Issue</span><select name="issue"><?= options(Labels::ISSUES, (string) $case['issue'], true) ?></select></label>
        <label class="field"><span>Platform</span><select name="platform"><?= options(Labels::PLATFORMS, (string) $case['platform'], true) ?></select></label>
        <label class="field"><span>Country</span><select name="jurisdiction"><?= options(Labels::JURIS, (string) $case['jurisdiction'], true) ?></select></label>
        <?php if ($isLead): ?>
        <label class="field"><span>Case lead</span><select name="lead_user_id"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'] === (int) $case['lead_user_id']) ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></label>
        <label class="check full"><input type="checkbox" name="nda_required" value="1"<?= checked((int) $case['nda_required'] === 1) ?>><span>Engagement agreement required before the client can message or upload</span></label>
        <?php endif; ?>
        <label class="field full"><span>Services in scope</span><textarea name="services" rows="3"><?= h((string) $case['services']) ?></textarea></label>
      </div>
    </form>

    <?php if ($intake): ?>
    <details class="card">
      <summary class="card-h"><h2>Original website intake</h2><span class="muted small">Encrypted. Opening is logged.</span></summary>
      <div class="card-b">
        <dl class="props">
          <dt>Name</dt><dd><?= h(($intake['name'] ?? '') ?: 'Not given') ?></dd>
          <dt>Email</dt><dd><?= h($intake['email'] ?? '') ?></dd>
          <dt>Platform</dt><dd><?= h(Labels::PLATFORMS[$intake['platform'] ?? ''] ?? '') ?><?= !empty($intake['platform_other']) ? ': ' . h($intake['platform_other']) : '' ?></dd>
          <dt>Issue</dt><dd><?= h(Labels::ISSUES[$intake['issue'] ?? ''] ?? '') ?></dd>
          <dt>Appeals so far</dt><dd><?= h(Labels::HISTORY[$intake['history'] ?? ''] ?? '') ?></dd>
          <dt>Urgency</dt><dd><?= h(Labels::URGENCY_IN[$intake['urgency'] ?? ''] ?? '') ?></dd>
        </dl>
        <div class="nda-doc mt-1"><?= h(($intake['details'] ?? '') ?: 'No details given.') ?></div>
      </div>
    </details>
    <?php endif; ?>

    <div class="card">
      <div class="card-h"><h2>Recent activity</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/cases/' . $id, ['tab' => 'activity'])) ?>">Full log</a></div>
      <div class="card-b"><ul class="timeline"><?php foreach ($activity as $a): ?><li><?= h(Cases::describe($a)) ?><?= $a['user_name'] ? ' by ' . h($a['user_name']) : '' ?><span class="when"><?= h(fdate($a['at'], true)) ?></span></li><?php endforeach; ?></ul></div>
    </div>
  </div>

  <div class="stack">
    <div class="card"><div class="card-b">
      <div class="grid g-2">
        <div><div class="muted small">Resolved submissions</div><div><strong class="num"><?= (int) $counts['won'] ?></strong> <span class="muted">of <?= (int) $counts['targets'] ?></span></div></div>
        <div><div class="muted small">Awaiting platforms</div><strong class="num"><?= (int) $counts['pending'] ?></strong></div>
        <div><div class="muted small">Messages</div><strong class="num"><?= (int) $counts['messages'] ?></strong></div>
        <div><div class="muted small">Documents</div><strong class="num"><?= (int) $counts['docs'] ?></strong></div>
      </div>
      <p class="small muted mt-2 mb-0">Opened <?= h(fdate($case['opened_at'])) ?><?= $case['closed_at'] ? ', closed ' . h(fdate($case['closed_at'])) : '' ?>. Source: <?= h(Labels::SOURCES[$case['source']] ?? '') ?>.</p>
    </div></div>

    <div class="card">
      <div class="card-h"><h2>Case team</h2></div>
      <div class="card-b">
        <div class="row mb-1"><span class="avatar sm"><?= h(initials($lead['name'] ?? '?')) ?></span><span><strong><?= h($lead['name'] ?? 'Unassigned') ?></strong> <span class="muted small">Case lead</span></span></div>
        <?php foreach ($team as $t): ?><div class="row mb-1"><span class="avatar sm"><?= h(initials($t['name'])) ?></span><span><?= h($t['name']) ?> <span class="muted small"><?= h(Labels::ROLES[$t['role']]) ?></span></span></div><?php endforeach; ?>
        <?php if ($isLead): ?>
        <details class="mt-1"><summary class="btn btn-ghost btn-sm">Change team</summary>
          <form method="post" action="<?= h(url('/cases/' . $id . '/team')) ?>" class="mt-1"><?= csrf_field() ?>
            <?php $in = array_column($team, 'id'); foreach ($staff as $s): if ((int) $s['id'] === (int) $case['lead_user_id']) { continue; } ?>
              <label class="check"><input type="checkbox" name="staff[]" value="<?= (int) $s['id'] ?>"<?= checked(in_array($s['id'], $in)) ?>><span><?= h($s['name']) ?> <span class="muted small"><?= h(Labels::ROLES[$s['role']]) ?></span></span></label>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-sm mt-1" type="submit">Save team</button>
          </form>
        </details>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-h"><h2>Upcoming deadlines</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/cases/' . $id, ['tab' => 'work'])) ?>">Manage</a></div>
      <?php if (!$deadlines): ?><?php $icon = 'clock'; $heading = 'No open deadlines'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($deadlines as $d): ?><li><span class="file-ico"><?= icon('clock') ?></span><div class="grow"><div><?= h($d['title']) ?></div><div class="meta <?= $d['due_at'] < now() ? 'text-danger' : '' ?>"><?= h(fdate($d['due_at'], true)) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>

    <?php if ($nda): ?>
    <div class="card"><div class="card-h"><h2>Agreement record</h2></div><div class="card-b small">
      <dl class="props"><dt>Signed by</dt><dd><?= h($nda['signed_name']) ?></dd><dt>When</dt><dd><?= h(fdate($nda['signed_at'], true)) ?></dd><dt>IP address</dt><dd class="mono"><?= h($nda['ip']) ?></dd><dt>Text hash</dt><dd class="mono"><?= h(substr($nda['text_hash'], 0, 24)) ?>&hellip;</dd></dl>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'messages'): ?>
<div class="card">
  <div class="card-h"><h2>Secure messages with <?= h($client['display_name']) ?></h2><span class="vault-note"><?= icon('lock') ?>Encrypted with the client key. Yellow notes are internal only.</span></div>
  <div class="thread">
    <?php if (!$thread): ?><?php $icon = 'message'; $heading = 'No messages yet'; $text = 'Start the written conversation below. The client is emailed a content-free notice.'; include DR_ROOT . '/views/partials/empty.php'; ?><?php endif; ?>
    <?php foreach ($thread as $m): $mine = (int) $m['sender_id'] === (int) $user['id']; $cls = $m['internal'] ? 'note' : ($mine ? 'mine' : ''); ?>
    <div class="msg <?= $cls ?>">
      <span class="avatar sm"><?= h(initials($m['sender_name'] ?? '?')) ?></span>
      <div class="bubble">
        <div class="who"><?= h($m['sender_name'] ?? 'Unknown') ?><?= in_array($m['sender_role'], ['client', 'adviser'], true) ? ' (' . h(Labels::ROLES[$m['sender_role']]) . ')' : '' ?><?= $m['internal'] ? ', internal note' : '' ?>, <?= h(fdate($m['created_at'], true)) ?></div>
        <?php if ($m['body'] !== '(attachment)'): ?><div class="body"><?= h($m['body']) ?></div><?php endif; ?>
        <?php if ($m['attachments']): ?><div class="att"><?php foreach ($m['attachments'] as $a): ?><a href="<?= h(url('/documents/' . $a['id'] . '/download')) ?>"><?= icon('paperclip') ?><?= h($a['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <form class="composer" method="post" action="<?= h(url('/cases/' . $id . '/messages')) ?>" data-compose="/cases/<?= $id ?>/messages">
    <?= csrf_field() ?>
    <label class="sr-only" for="msg-body">Message</label>
    <textarea id="msg-body" name="body" placeholder="Write to the client. Plain, precise and in writing." required></textarea>
    <div class="bar-row">
      <label class="btn btn-ghost btn-sm"><?= icon('paperclip') ?>Attach<input type="file" name="files" multiple hidden></label>
      <span class="chips row"></span>
      <label class="check mb-0"><input type="checkbox" name="internal" value="1"><span>Internal note (team only)</span></label>
      <span class="spacer"></span>
      <button class="btn btn-primary" type="submit"><?= icon('message') ?>Send</button>
    </div>
    <ul class="up-list"></ul>
  </form>
</div>

<?php elseif ($tab === 'appeals'): ?>
<?php $won = count(array_filter($targets, fn($t) => in_array($t['status'], ['approved', 'suppressed'], true))); ?>
<div class="grid g-4 mb-2">
  <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Reinstated, resolved or released</div><div class="value"><?= $won ?></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('clock') ?></span><div class="label">Awaiting decision</div><div class="value"><?= count(array_filter($targets, fn($t) => in_array($t['status'], ['submitted', 'acknowledged', 'appealed'], true))) ?></div></div>
  <div class="card kpi danger"><span class="glyph"><?= icon('x') ?></span><div class="label">Rejected</div><div class="value"><?= count(array_filter($targets, fn($t) => $t['status'] === 'refused')) ?></div></div>
  <div class="card kpi warn"><span class="glyph"><?= icon('pen') ?></span><div class="label">Drafted</div><div class="value"><?= count(array_filter($targets, fn($t) => $t['status'] === 'drafted')) ?></div></div>
</div>
<div class="card">
  <div class="card-h"><h2>Submissions to platforms</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/cases/' . $id . '/targets.csv')) ?>"><?= icon('download') ?>Export CSV</a></div>
  <?php if (!$targets): ?><?php $icon = 'target'; $heading = 'No submissions tracked yet'; $text = 'Add each appeal, Plan of Action, verification or release request you prepare, with its route and status.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Account or item</th><th>Route</th><th>Strength</th><th>Status</th><th>Submitted</th><th>Decided</th><th>Client</th><th class="right"></th></tr></thead>
    <tbody><?php foreach ($targets as $t): ?>
      <tr>
        <td data-l="Account or item" class="title-cell break"><span><?= h(str_limit($t['url'], 70)) ?></span><small><?= h($t['platform']) ?><?= $t['platform_ref'] ? ', ref ' . h($t['platform_ref']) : '' ?></small></td>
        <td data-l="Route"><?= h(Labels::ROUTES[$t['route']] ?? $t['route']) ?></td>
        <td data-l="Strength"><?= View::badge($t['strength'], Labels::STRENGTH[$t['strength']] ?? '') ?></td>
        <td data-l="Status"><?= View::badge($t['status'], Labels::TARGET_STATUS[$t['status']] ?? '') ?></td>
        <td data-l="Submitted" class="nowrap"><?= h(fdate($t['submitted_on'])) ?></td>
        <td data-l="Decided" class="nowrap"><?= h(fdate($t['decided_on'])) ?></td>
        <td data-l="Client"><?= $t['client_visible'] ? icon('eye') : '<span class="muted small">Hidden</span>' ?></td>
        <td data-l="" class="right nowrap">
          <button type="button" class="btn btn-ghost btn-xs" data-fill="#target-form" data-title="Edit submission" data-values="<?= h(json_encode(['target_id' => $t['id'], 'url' => $t['url'], 'platform' => $t['platform'], 'route' => $t['route'], 'strength' => $t['strength'], 'status' => $t['status'], 'platform_ref' => $t['platform_ref'], 'submitted_on' => $t['submitted_on'], 'decided_on' => $t['decided_on'], 'outcome' => $t['outcome'], 'client_visible' => (int) $t['client_visible']])) ?>"><?= icon('pen') ?>Edit</button>
          <form class="inline" method="post" action="<?= h(url('/cases/' . $id . '/targets/' . $t['id'] . '/delete')) ?>" data-confirm="Delete this submission record?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<form class="card" id="target-form" method="post" action="<?= h(url('/cases/' . $id . '/targets')) ?>">
  <?= csrf_field() ?><input type="hidden" name="target_id" value="">
  <div class="card-h"><h2 data-form-title>Add a submission</h2><span class="vault-note"><?= icon('lock') ?>Identifiers and outcomes are encrypted with the client key</span></div>
  <div class="card-b form-grid">
    <label class="field full"><span>Account, listing, channel or case identifier</span><input type="text" name="url" required maxlength="500" placeholder="For example: Seller ID A1B2C3, ASIN B0XXXX, channel URL or support case number"></label>
    <label class="field"><span>Platform</span><input type="text" name="platform" placeholder="Defaults to the case platform" value="<?= h(Labels::PLATFORMS[$case['platform'] ?? ''] ?? '') ?>"></label>
    <label class="field"><span>Route</span><select name="route"><?= options(Labels::ROUTES, 'account_health') ?></select></label>
    <label class="field"><span>Case strength</span><select name="strength"><?= options(Labels::STRENGTH, 'case-dependent') ?></select></label>
    <label class="field"><span>Status</span><select name="status"><?= options(Labels::TARGET_STATUS, 'drafted') ?></select></label>
    <label class="field"><span>Platform reference</span><input type="text" name="platform_ref" placeholder="Ticket, case or appeal number"></label>
    <div class="form-grid full">
      <label class="field"><span>Submitted on</span><input type="date" name="submitted_on"></label>
      <label class="field"><span>Decided on</span><input type="date" name="decided_on"></label>
    </div>
    <label class="field full"><span>Outcome notes</span><textarea name="outcome" rows="3"></textarea></label>
    <label class="check full"><input type="checkbox" name="client_visible" value="1" checked><span>Show this submission and its status to the client</span></label>
  </div>
  <div class="card-f"><button class="btn btn-primary" type="submit">Save submission</button></div>
</form>

<?php elseif ($tab === 'funds'): ?>
<?php
$byCur = [];
foreach ($funds as $f) {
    $c = $f['currency'];
    $byCur[$c]['held'] = ($byCur[$c]['held'] ?? 0) + (int) $f['amount'];
    $byCur[$c]['released'] = ($byCur[$c]['released'] ?? 0) + (int) $f['released'];
}
$main = $byCur ? array_key_first($byCur) : \DR\Core\Settings::get('currency');
$held = (int) ($byCur[$main]['held'] ?? 0);
$rel = (int) ($byCur[$main]['released'] ?? 0);
$pct = $held > 0 ? (int) round(100 * min($rel, $held) / $held) : 0;
?>
<div class="grid g-4 mb-2">
  <div class="card kpi warn"><span class="glyph"><?= icon('lock') ?></span><div class="label">Held (<?= h($main) ?>)</div><div class="value num"><?= h(money($held, $main)) ?></div></div>
  <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Released</div><div class="value num"><?= h(money($rel, $main)) ?></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('clock') ?></span><div class="label">Still outstanding</div><div class="value num"><?= h(money(max(0, $held - $rel), $main)) ?></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('chart') ?></span><div class="label">Released so far</div><div class="value"><?= $pct ?>%</div><div class="bar ok mt-1"><i class="<?= h(wclass($pct)) ?>"></i></div></div>
</div>
<?php if (count($byCur) > 1): ?><p class="small muted">Totals above are for <?= h($main) ?>. Other currencies are listed in the table.</p><?php endif; ?>
<div class="card">
  <div class="card-h"><h2>Held balances</h2><span class="vault-note"><?= icon('lock') ?>References and notes are encrypted with the client key</span></div>
  <?php if (!$funds): ?><?php $icon = 'receipt'; $heading = 'No held funds recorded'; $text = 'Record each balance a platform is holding, with the date it was held and the expected release date.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Platform</th><th class="right">Held</th><th class="right">Released</th><th>Status</th><th>Held since</th><th>Expected</th><th>Client</th><th class="right"></th></tr></thead>
    <tbody><?php foreach ($funds as $f): ?>
      <tr>
        <td data-l="Platform" class="title-cell"><span><?= h($f['platform']) ?></span><?php if ($f['reference']): ?><small>Ref <?= h($f['reference']) ?></small><?php endif; ?></td>
        <td data-l="Held" class="right num"><?= h(money((int) $f['amount'], $f['currency'])) ?></td>
        <td data-l="Released" class="right num"><?= h(money((int) $f['released'], $f['currency'])) ?></td>
        <td data-l="Status"><?= View::badge($f['status'], Labels::FUNDS_STATUS[$f['status']] ?? '') ?></td>
        <td data-l="Held since" class="nowrap"><?= h(fdate($f['held_since'])) ?></td>
        <td data-l="Expected" class="nowrap <?= $f['expected_on'] && $f['expected_on'] < today() && !in_array($f['status'], ['released', 'forfeited'], true) ? 'text-danger' : '' ?>"><?= h(fdate($f['expected_on'])) ?></td>
        <td data-l="Client"><?= $f['client_visible'] ? icon('eye') : '<span class="muted small">Hidden</span>' ?></td>
        <td data-l="" class="right nowrap">
          <button type="button" class="btn btn-ghost btn-xs" data-fill="#funds-form" data-title="Edit held balance" data-values="<?= h(json_encode(['fund_id' => $f['id'], 'platform' => $f['platform'], 'amount' => number_format((int) $f['amount'] / 100, 2, '.', ''), 'released' => number_format((int) $f['released'] / 100, 2, '.', ''), 'currency' => $f['currency'], 'status' => $f['status'], 'held_since' => $f['held_since'], 'expected_on' => $f['expected_on'], 'reference' => $f['reference'], 'notes' => $f['notes'], 'client_visible' => (int) $f['client_visible']])) ?>"><?= icon('pen') ?>Edit</button>
          <form class="inline" method="post" action="<?= h(url('/cases/' . $id . '/funds/' . $f['id'] . '/delete')) ?>" data-confirm="Delete this held balance record?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
        </td>
      </tr>
      <?php if ($f['notes']): ?><tr class="sub-row"><td colspan="8" class="small muted pre"><?= h($f['notes']) ?></td></tr><?php endif; ?>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<form class="card" id="funds-form" method="post" action="<?= h(url('/cases/' . $id . '/funds')) ?>">
  <?= csrf_field() ?><input type="hidden" name="fund_id" value="">
  <div class="card-h"><h2 data-form-title>Record a held balance</h2></div>
  <div class="card-b form-grid">
    <label class="field"><span>Platform</span><input type="text" name="platform" required maxlength="60" value="<?= h(Labels::PLATFORMS[$case['platform'] ?? ''] ?? '') ?>"></label>
    <label class="field"><span>Currency</span><select name="currency"><?= options(Labels::CURRENCIES, \DR\Core\Settings::get('currency')) ?></select></label>
    <label class="field"><span>Amount held</span><input type="number" name="amount" step="0.01" min="0" required></label>
    <label class="field"><span>Released so far</span><input type="number" name="released" step="0.01" min="0" value="0"></label>
    <label class="field"><span>Status</span><select name="status"><?= options(Labels::FUNDS_STATUS, 'held') ?></select></label>
    <label class="field"><span>Platform reference</span><input type="text" name="reference" maxlength="120" placeholder="Settlement, disbursement or ticket number"></label>
    <label class="field"><span>Held since</span><input type="date" name="held_since"></label>
    <label class="field"><span>Expected release</span><input type="date" name="expected_on"></label>
    <label class="field full"><span>Notes</span><textarea name="notes" rows="2" placeholder="Release conditions, reserve terms, open disputes"></textarea></label>
    <label class="check full"><input type="checkbox" name="client_visible" value="1" checked><span>Show this balance to the client</span></label>
  </div>
  <div class="card-f"><button class="btn btn-primary" type="submit">Save balance</button></div>
</form>

<?php elseif ($tab === 'documents'): ?>
<?php $docsBack = url('/cases/' . $id, ['tab' => 'documents']); ?>
<div class="grid g-side">
  <div class="card"><div class="card-h"><h2>Case documents</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/clients/' . $client['id'], ['tab' => 'documents'])) ?>">Whole client vault</a></div>
    <?php include DR_ROOT . '/views/staff/_doclist.php'; ?></div>
  <div class="card"><div class="card-h"><h2>Upload</h2></div><div class="card-b">
    <?php $clientId = (int) $client['id']; $caseId = $id; $staff = true; $cases = null; include DR_ROOT . '/views/partials/uploader.php'; ?>
  </div></div>
</div>

<?php elseif ($tab === 'work'): ?>
<div class="grid g-2">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Tasks</h2></div>
      <?php if (!$tasks): ?><?php $icon = 'check-square'; $heading = 'No tasks'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($tasks as $t): ?>
        <li><?php if ($t['status'] === 'open'): ?><form method="post" action="<?= h(url('/tasks/' . $t['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="done"><input type="hidden" name="_back" value="<?= h(url('/cases/' . $id, ['tab' => 'work'])) ?>"><button class="icon-btn" type="submit" aria-label="Mark done"><?= icon('check') ?></button></form><?php else: ?><span class="file-ico"><?= icon('check') ?></span><?php endif; ?>
          <div class="grow"><div class="<?= $t['status'] !== 'open' ? 'muted' : '' ?>"><?= h($t['title']) ?></div><div class="meta"><?= h($t['assignee'] ?? '') ?><?= $t['due_on'] ? ' &middot; due ' . h(fdate($t['due_on'])) : '' ?></div><?php if ($t['description']): ?><div class="small pre muted"><?= h($t['description']) ?></div><?php endif; ?></div>
          <?= View::badge($t['status'] === 'open' ? $t['priority'] : $t['status'], $t['status'] === 'open' ? ucfirst($t['priority']) : null) ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <form class="card" method="post" action="<?= h(url('/tasks')) ?>"><?= csrf_field() ?><input type="hidden" name="case_id" value="<?= $id ?>"><input type="hidden" name="_back" value="<?= h(url('/cases/' . $id, ['tab' => 'work'])) ?>">
      <div class="card-h"><h2>Add task</h2></div>
      <div class="card-b form-grid">
        <label class="field full"><span>Task</span><input type="text" name="title" required></label>
        <label class="field"><span>Assign to</span><select name="assignee_id"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'] === (int) $user['id']) ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Due</span><input type="date" name="due_on"></label>
        <label class="field"><span>Priority</span><select name="priority"><?= options(Labels::TASK_PRIORITY, 'medium') ?></select></label>
        <label class="field full"><span>Details</span><textarea name="description" rows="2"></textarea></label>
      </div>
      <div class="card-f"><button class="btn btn-primary btn-sm" type="submit">Add task</button></div>
    </form>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Deadlines</h2></div>
      <?php if (!$deadlines): ?><?php $icon = 'clock'; $heading = 'No deadlines'; $text = 'Track platform response dates, appeal windows and expected fund releases.'; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($deadlines as $d): ?>
        <li><span class="file-ico"><?= icon('clock') ?></span><div class="grow"><div><?= h($d['title']) ?> <?= $d['client_visible'] ? '<span class="pill">' . icon('eye') . 'Client</span>' : '' ?></div><div class="meta <?= $d['status'] === 'upcoming' && $d['due_at'] < now() ? 'text-danger' : '' ?>"><?= h(Labels::DEADLINE_KINDS[$d['kind']] ?? '') ?> &middot; <?= h(fdate($d['due_at'], true)) ?></div></div>
        <?php if ($d['status'] === 'upcoming'): ?><form method="post" action="<?= h(url('/deadlines/' . $d['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="completed"><input type="hidden" name="_back" value="<?= h(url('/cases/' . $id, ['tab' => 'work'])) ?>"><button class="btn btn-ghost btn-xs" type="submit">Done</button></form><?php else: ?><?= View::badge($d['status']) ?><?php endif; ?></li>
      <?php endforeach; ?></ul><?php endif; ?>
    </div>
    <form class="card" method="post" action="<?= h(url('/deadlines')) ?>"><?= csrf_field() ?><input type="hidden" name="case_id" value="<?= $id ?>"><input type="hidden" name="_back" value="<?= h(url('/cases/' . $id, ['tab' => 'work'])) ?>">
      <div class="card-h"><h2>Add deadline</h2></div>
      <div class="card-b form-grid">
        <label class="field full"><span>Title</span><input type="text" name="title" required placeholder="For example: Amazon reply due on Plan of Action"></label>
        <label class="field"><span>Type</span><select name="kind"><?= options(Labels::DEADLINE_KINDS, 'platform_response') ?></select></label>
        <label class="field"><span>Due</span><input type="datetime-local" name="due_at" required></label>
        <label class="field"><span>Remind team (days before)</span><input type="number" name="remind_days" value="2" min="0" max="30"></label>
        <label class="check"><input type="checkbox" name="client_visible" value="1"><span>Show to client</span></label>
        <label class="field full"><span>Notes</span><textarea name="notes" rows="2"></textarea></label>
      </div>
      <div class="card-f"><button class="btn btn-primary btn-sm" type="submit">Add deadline</button></div>
    </form>
  </div>
</div>

<?php elseif ($tab === 'invoices'): ?>
<div class="card">
  <div class="card-h"><h2>Invoices for this case</h2><?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-primary btn-sm" href="<?= h(url('/invoices/new', ['client' => $client['id'], 'case' => $id])) ?>"><?= icon('plus') ?>New invoice</a><?php endif; ?></div>
  <?php if (!$invoices): ?><?php $icon = 'receipt'; $heading = 'No invoices'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Number</th><th>Issued</th><th>Due</th><th class="right">Total</th><th class="right">Paid</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($invoices as $i): ?><tr><td data-l="Number"><a class="row-link mono" href="<?= h(url('/invoices/' . $i['id'])) ?>"><?= h($i['number']) ?></a></td><td data-l="Issued"><?= h(fdate($i['issued_on'])) ?></td><td data-l="Due"><?= h(fdate($i['due_on'])) ?></td><td data-l="Total" class="right num"><?= h(money((int) $i['total'], $i['currency'])) ?></td><td data-l="Paid" class="right num"><?= h(money((int) $i['paid'], $i['currency'])) ?></td><td data-l="Status"><?= View::badge($i['status']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <div class="card-h"><h2>Activity and access log</h2><span class="muted small">Recorded without any case content</span></div>
  <div class="table-wrap"><table class="table table-stack"><thead><tr><th>When</th><th>Who</th><th>What</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($activity as $a): ?><tr><td data-l="When" class="nowrap"><?= h(fdate($a['at'], true)) ?></td><td data-l="Who"><?= h($a['user_name'] ?? ucfirst((string) ($a['role'] ?? 'system'))) ?></td><td data-l="What"><?= h(Cases::describe($a)) ?></td><td data-l="IP" class="mono small"><?= h($a['ip'] ?? '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
