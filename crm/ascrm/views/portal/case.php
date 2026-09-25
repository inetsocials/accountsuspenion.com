<?php
use DR\Core\Labels;
use DR\Core\Settings;
use DR\Core\View;
use DR\Http\TrackerController;
$id = (int) $case['id'];
$tabs = ['overview' => ['Overview', 'home', null], 'messages' => ['Messages', 'message', $unread ?: null], 'documents' => ['Documents', 'folder', null], 'tracker' => ['Appeals and funds', 'target', $targetStats['total'] ?: null], 'invoices' => ['Invoices', 'receipt', null]];
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><a href="<?= h(url('/client')) ?>">My cases</a></div>
    <h1><span class="ref"><?= h($case['ref']) ?></span> <?= h($case['title']) ?></h1>
    <p class="sub row"><?= View::badge($case['status']) ?> <span>Case lead: <?= h($lead['name'] ?? 'Being assigned') ?></span></p>
  </div>
</div>
<nav class="tabs" aria-label="Case sections">
<?php foreach ($tabs as $k => [$lbl, $ic, $cnt]): $locked = $ndaPending && in_array($k, ['messages', 'documents'], true); ?>
  <a href="<?= h(url('/client/cases/' . $id, ['tab' => $k])) ?>"<?= $tab === $k ? ' class="active" aria-current="page"' : '' ?>><?= icon($locked ? 'lock' : $ic) ?><?= h($lbl) ?><?php if ($cnt): ?><span class="count"><?= (int) $cnt ?></span><?php endif; ?></a>
<?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
  <?php if ($ndaPending): ?>
  <div class="card mb-2">
    <div class="card-h"><h2>Engagement and confidentiality agreement</h2><span class="badge badge-warn">Signature needed</span></div>
    <form class="card-b" method="post" action="<?= h(url('/client/cases/' . $id . '/nda')) ?>">
      <?= csrf_field() ?>
      <p>Before we exchange details, please read and sign the agreement below. It sets out how we work: independent, confidential, no guaranteed outcomes, and nothing submitted without your approval. A record of the exact text, time and your connection is kept with your case.</p>
      <div class="nda-doc mb-2"><?= h($ndaText) ?></div>
      <label class="field"><span>Type your full name as your signature</span><input type="text" name="signed_name" required minlength="3" autocomplete="name" value="<?= h($user['name']) ?>"></label>
      <label class="check"><input type="checkbox" name="agree" value="1" required><span>I have read this agreement and I sign it electronically on behalf of myself or the client I represent.</span></label>
      <button class="btn btn-primary mt-1" type="submit"><?= icon('pen') ?>Sign agreement</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="card mb-2"><div class="card-b">
    <?php $stage = $case['stage']; $status = $case['status']; include DR_ROOT . '/views/partials/stepper.php'; ?>
  </div></div>
  <div class="grid g-3 mb-2">
    <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Resolved</div><div class="value"><?= (int) $targetStats['won'] ?></div><div class="foot">of <?= (int) $targetStats['total'] ?> submissions</div></div>
    <div class="card kpi"><span class="glyph"><?= icon('clock') ?></span><div class="label">Awaiting a decision</div><div class="value"><?= (int) $targetStats['pending'] ?></div><div class="foot">with the platform</div></div>
    <div class="card kpi"><span class="glyph"><?= icon('message') ?></span><div class="label">Unread messages</div><div class="value"><?= (int) $unread ?></div><div class="foot"><a href="<?= h(url('/client/cases/' . $id, ['tab' => 'messages'])) ?>">Open messages</a></div></div>
  </div>
  <div class="grid g-2">
    <div class="card">
      <div class="card-h"><h2>Dates to know</h2></div>
      <?php if (!$deadlines): ?><?php $icon = 'calendar'; $heading = 'Nothing scheduled'; $text = 'Your case lead will add key dates here.'; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($deadlines as $d): ?><li><span class="file-ico"><?= icon('clock') ?></span><div class="grow"><div><?= h($d['title']) ?></div><div class="meta"><?= h(fdate($d['due_at'])) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Held funds</h2></div>
      <div class="card-b">
      <?php if (!$fundTotals): ?><p class="muted small mb-0">If a platform is holding a balance, your case lead records it here with the expected release date.</p>
      <?php else: foreach ($fundTotals as $cur => $ft): $pct = $ft['held'] > 0 ? (int) round(100 * $ft['released'] / $ft['held']) : 0; ?>
        <div class="row-between"><span class="muted">Held</span><strong class="num"><?= h(money($ft['held'], $cur)) ?></strong></div>
        <div class="row-between"><span class="muted">Released</span><strong class="num"><?= h(money($ft['released'], $cur)) ?></strong></div>
        <div class="row-between mb-1"><span class="muted">Outstanding</span><strong class="num"><?= h(money($ft['outstanding'], $cur)) ?></strong></div>
        <div class="bar ok mb-1"><i class="<?= h(wclass($pct)) ?>"></i></div><p class="small muted mb-1"><?= $pct ?>% released</p>
      <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'messages'): ?>
<div class="card">
  <div class="card-h"><h2>Secure messages</h2><span class="vault-note"><?= icon('lock') ?>Encrypted with your personal key</span></div>
  <div class="thread">
    <?php if (!$thread): ?><?php $icon = 'message'; $heading = 'Start the conversation'; $text = 'Write to your case team below. We reply ' . Settings::get('consult_response_window') . '.'; include DR_ROOT . '/views/partials/empty.php'; ?><?php endif; ?>
    <?php foreach ($thread as $m): $mine = (int) $m['sender_id'] === (int) $user['id']; ?>
    <div class="msg <?= $mine ? 'mine' : '' ?>">
      <span class="avatar sm"><?= h(initials($m['sender_name'] ?? '?')) ?></span>
      <div class="bubble">
        <div class="who"><?= h($mine ? 'You' : ($m['sender_name'] ?? '')) ?><?= !$mine && !in_array($m['sender_role'], ['client', 'adviser'], true) ? ', ' . h(Settings::get('org_name')) : '' ?>, <?= h(fdate($m['created_at'], true)) ?></div>
        <?php if ($m['body'] !== '(attachment)'): ?><div class="body"><?= h($m['body']) ?></div><?php endif; ?>
        <?php if ($m['attachments']): ?><div class="att"><?php foreach ($m['attachments'] as $a): ?><a href="<?= h(url('/documents/' . $a['id'] . '/download')) ?>"><?= icon('paperclip') ?><?= h($a['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($case['status'] !== 'closed'): ?>
  <form class="composer" method="post" action="<?= h(url('/client/cases/' . $id . '/messages')) ?>" data-compose="/client/cases/<?= $id ?>/messages">
    <?= csrf_field() ?>
    <label class="sr-only" for="msg-body">Message</label>
    <textarea id="msg-body" name="body" placeholder="Write to your case team. Include links to anything you want us to look at." required></textarea>
    <div class="bar-row">
      <label class="btn btn-ghost btn-sm"><?= icon('paperclip') ?>Attach files<input type="file" name="files" multiple hidden></label>
      <span class="chips row"></span><span class="spacer"></span>
      <button class="btn btn-primary" type="submit"><?= icon('message') ?>Send securely</button>
    </div>
    <ul class="up-list"></ul>
  </form>
  <?php else: ?><div class="card-f small muted">This case is closed. For a new matter, start a new case on our website.</div><?php endif; ?>
</div>

<?php elseif ($tab === 'documents'): ?>
<div class="grid g-side">
  <div class="card">
    <div class="card-h"><h2>Documents</h2></div>
    <?php if (!$docs): ?><?php $icon = 'folder'; $heading = 'No documents yet'; $text = 'Upload screenshots, letters or evidence. Everything is encrypted on arrival.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><ul class="list"><?php foreach ($docs as $d): ?>
      <li><span class="file-ico"><?= h(strtolower(pathinfo($d['name'], PATHINFO_EXTENSION)) ?: 'file') ?></span>
        <div class="grow"><a class="item-link break" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>"><?= h($d['name']) ?></a><div class="meta"><?= h(fdate($d['created_at'], true)) ?>, <?= h(human_size((int) $d['size'])) ?>, <?= in_array($d['uploaded_role'], ['client', 'adviser'], true) ? ((int) $d['uploaded_by'] === (int) $user['id'] ? 'uploaded by you' : 'uploaded by ' . h($d['uploader'] ?? '')) : 'from ' . h(Settings::get('org_name')) ?></div></div>
        <?php if (in_array($d['mime'], ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)): ?><a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/view')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>View</a><?php endif; ?>
        <a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>" aria-label="Download"><?= icon('download') ?></a></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div>
  <?php if ($case['status'] !== 'closed'): ?>
  <div class="card"><div class="card-h"><h2>Share documents</h2></div><div class="card-b">
    <?php $clientId = (int) $case['client_id']; $caseId = $id; $staff = false; $cases = null; include DR_ROOT . '/views/partials/uploader.php'; ?>
  </div></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'tracker'): ?>
<div class="card">
  <div class="card-h"><h2>Submissions to the platform</h2><span class="muted small">Updated by your case team as the platform responds</span></div>
  <?php if (!$targets): ?><?php $icon = 'target'; $heading = 'Nothing submitted yet'; $text = 'Once your appeal, Plan of Action or request is submitted, it appears here with its status.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Account or item</th><th>Route</th><th>Status</th><th>Submitted</th><th>Decided</th></tr></thead>
    <tbody><?php foreach ($targets as $t): ?>
      <tr><td data-l="Account or item" class="title-cell break"><?= h(str_limit($t['url'], 80)) ?><small><?= h($t['platform']) ?></small><?php if ($t['outcome']): ?><small class="pre"><?= h($t['outcome']) ?></small><?php endif; ?></td>
      <td data-l="Route"><?= h(Labels::ROUTES[$t['route']] ?? '') ?></td>
      <td data-l="Status"><?= View::badge($t['status'], Labels::TARGET_STATUS[$t['status']] ?? '') ?></td>
      <td data-l="Submitted" class="nowrap"><?= h(fdate($t['submitted_on'])) ?></td>
      <td data-l="Decided" class="nowrap"><?= h(fdate($t['decided_on'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>
<div class="card">
  <div class="card-h"><h2>Held funds</h2></div>
  <?php if (!$funds): ?><?php $icon = 'receipt'; $heading = 'No held balances recorded'; $text = 'If a platform is holding your money, each balance appears here with its expected release date.'; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack">
    <thead><tr><th>Platform</th><th class="right">Held</th><th class="right">Released</th><th>Status</th><th>Expected release</th></tr></thead>
    <tbody><?php foreach ($funds as $f): ?>
      <tr><td data-l="Platform" class="title-cell"><?= h($f['platform']) ?><?php if ($f['notes']): ?><small class="pre"><?= h($f['notes']) ?></small><?php endif; ?></td>
      <td data-l="Held" class="right num"><?= h(money((int) $f['amount'], $f['currency'])) ?></td>
      <td data-l="Released" class="right num"><?= h(money((int) $f['released'], $f['currency'])) ?></td>
      <td data-l="Status"><?= View::badge($f['status'], Labels::FUNDS_STATUS[$f['status']] ?? '') ?></td>
      <td data-l="Expected release" class="nowrap"><?= h(fdate($f['expected_on'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <?php if (!$invoices): ?><?php $icon = 'receipt'; $heading = 'No invoices for this case'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Number</th><th>Issued</th><th>Due</th><th class="right">Total</th><th class="right">Balance</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($invoices as $i): ?><tr><td data-l="Number" class="mono"><?= h($i['number']) ?></td><td data-l="Issued"><?= h(fdate($i['issued_on'])) ?></td><td data-l="Due"><?= h(fdate($i['due_on'])) ?></td><td data-l="Total" class="right num"><?= h(money((int) $i['total'], $i['currency'])) ?></td><td data-l="Balance" class="right num"><?= h(money((int) $i['total'] - (int) $i['paid'], $i['currency'])) ?></td><td data-l="Status"><?= View::badge($i['status']) ?></td><td class="right"><a class="btn btn-ghost btn-xs" href="<?= h(url('/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= icon('printer') ?>View</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>
<?php endif; ?>
