<?php
use DR\Core\Labels;
use DR\Core\Settings;
use DR\Core\View;
$first = explode(' ', trim($user['name']))[0];
$ndaWaiting = array_filter($cases, fn($c) => (int) $c['nda_required'] === 1 && !$c['nda_signed_at'] && $c['status'] !== 'closed');
$unreadTotal = array_sum(array_column($cases, 'unread'));
?>
<div class="hero-card mb-2">
  <div class="row-between">
    <div>
      <div class="eyebrow">Secure client portal</div>
      <h1>Welcome, <?= h($first) ?></h1>
      <p class="mb-0">Everything about your matter is here, in writing and encrypted. We reply <?= h(Settings::get('consult_response_window')) ?>.</p>
    </div>
    <?php if ($unreadTotal): ?><a class="btn btn-primary" href="<?= h(url('/client/cases/' . $cases[array_key_first(array_filter($cases, fn($c) => $c['unread'] > 0))]['id'], ['tab' => 'messages'])) ?>"><?= icon('message') ?><?= $unreadTotal ?> new <?= $unreadTotal === 1 ? 'message' : 'messages' ?></a><?php endif; ?>
  </div>
</div>

<?php foreach ($ndaWaiting as $c): ?>
<div class="alert alert-warn mb-2"><?= icon('pen') ?><div><strong>Please sign the confidentiality agreement for <?= h($c['ref']) ?></strong>It protects you and opens your secure messages and document vault. <a href="<?= h(url('/client/cases/' . $c['id'])) ?>">Review and sign</a></div></div>
<?php endforeach; ?>

<div class="grid g-main">
  <div class="stack">
    <h2 class="mb-0">Your cases</h2>
    <?php if (!$cases): ?>
      <div class="card"><?php $icon = 'briefcase'; $heading = 'No cases yet'; $text = 'When your case lead opens your matter it will appear here.'; include DR_ROOT . '/views/partials/empty.php'; ?></div>
    <?php endif; ?>
    <?php foreach ($cases as $c): $t = $c['targets']; ?>
    <a class="card case-card" href="<?= h(url('/client/cases/' . $c['id'])) ?>">
      <div class="row-between mb-2">
        <div><span class="ref"><?= h($c['ref']) ?></span> <?= View::badge($c['status']) ?><?php if ($c['unread']): ?> <span class="unread"><?= (int) $c['unread'] ?></span><?php endif; ?><h3 class="mt-1 mb-0"><?= h($c['title']) ?></h3>
          <span class="small muted"><?= $user['role'] === 'adviser' ? h($c['display_name']) . ' &middot; ' : '' ?>Case lead: <?= h($c['lead_name'] ?? 'Being assigned') ?></span></div>
        <?= icon('chevron') ?>
      </div>
      <?php $stage = $c['stage']; $status = $c['status']; include DR_ROOT . '/views/partials/stepper.php'; ?>
      <?php if ((int) $t['total'] > 0): ?>
      <div class="mt-2"><div class="row-between small mb-1"><span>Appeal progress</span><span class="muted"><?= (int) $t['won'] ?> of <?= (int) $t['total'] ?> resolved, <?= (int) $t['pending'] ?> awaiting the platform</span></div>
        <div class="bar ok"><i class="<?= wclass($t['won'] / $t['total'] * 100) ?>"></i></div></div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Coming up</h2></div>
      <?php if (!$deadlines): ?><?php $icon = 'calendar'; $heading = 'Nothing scheduled'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($deadlines as $d): ?><li><span class="file-ico"><?= icon('clock') ?></span><div class="grow"><div><?= h($d['title']) ?></div><div class="meta"><?= h($d['ref']) ?> &middot; <?= h(fdate($d['due_at'])) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Recently shared with you</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/client/documents')) ?>">All</a></div>
      <?php if (!$docs): ?><?php $icon = 'folder'; $heading = 'No documents yet'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?><ul class="list"><?php foreach ($docs as $d): ?><li><span class="file-ico"><?= h(strtolower(pathinfo($d['name'], PATHINFO_EXTENSION)) ?: 'file') ?></span><div class="grow"><a class="item-link break" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>"><?= h($d['name']) ?></a><div class="meta"><?= h(fdate($d['created_at'])) ?>, <?= h(human_size((int) $d['size'])) ?></div></div></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php if ($invoices): ?>
    <div class="card">
      <div class="card-h"><h2>Invoices to pay</h2></div>
      <ul class="list"><?php foreach ($invoices as $i): ?><li><div class="grow"><a class="item-link mono" href="<?= h(url('/invoices/' . $i['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= h($i['number']) ?></a><div class="meta">Due <?= h(fdate($i['due_on'])) ?></div></div><strong class="num"><?= h(money((int) $i['total'] - (int) $i['paid'], $i['currency'])) ?></strong></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <div class="card"><div class="card-b small">
      <strong>Need something new?</strong><br>Use the Messages tab on your case. For a new matter, start a new case on our website. We will never ask for your password or one-time codes.
    </div></div>
  </div>
</div>
