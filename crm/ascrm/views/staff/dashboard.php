<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\View;
use DR\Service\Charts;
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$won = (int) ($removals['won'] ?? 0);
$total = (int) ($removals['total'] ?? 0);
?>
<div class="page-head">
  <div><div class="eyebrow"><?= h(fdate(today())) ?></div><h1><?= h($greet . ', ' . explode(' ', $user['name'])[0]) ?></h1><p class="sub">Here is what needs attention across your cases.</p></div>
  <div class="actions">
    <?php if (Auth::atLeast($user, 'lead')): ?><a class="btn btn-ghost" href="<?= h(url('/cases/new')) ?>"><?= icon('plus') ?>New case</a><?php endif; ?>
    <a class="btn btn-primary" href="<?= h(url('/leads')) ?>"><?= icon('inbox') ?>Open leads inbox</a>
  </div>
</div>

<?php if ($kpi['emergency'] > 0): ?>
<div class="alert alert-danger mb-2"><?= icon('flag') ?><div><strong><?= (int) $kpi['emergency'] ?> priority <?= $kpi['emergency'] === 1 ? 'case with a response deadline is' : 'cases with response deadlines are' ?> open</strong>Committed response window: <?= h(\DR\Core\Settings::get('priority_response_window')) ?>, in writing.</div></div>
<?php endif; ?>

<div class="grid g-4">
  <a class="card kpi<?= $kpi['leads'] ? ' warn' : '' ?>" href="<?= h(url('/leads')) ?>"><span class="glyph"><?= icon('inbox', 'ic ic-lg') ?></span><div class="label">New leads</div><div class="value"><?= (int) $kpi['leads'] ?></div><div class="foot">awaiting screening</div></a>
  <a class="card kpi" href="<?= h(url('/cases')) ?>"><span class="glyph"><?= icon('briefcase', 'ic ic-lg') ?></span><div class="label">Open cases</div><div class="value"><?= (int) $kpi['active'] ?></div><div class="foot"><?= count($awaiting) ?> awaiting a reply</div></a>
  <a class="card kpi<?= $kpi['overdue_tasks'] ? ' danger' : ' ok' ?>" href="<?= h(url('/tasks')) ?>"><span class="glyph"><?= icon('check-square', 'ic ic-lg') ?></span><div class="label">My open tasks</div><div class="value"><?= (int) $kpi['tasks'] ?></div><div class="foot"><?= (int) $kpi['overdue_tasks'] ?> overdue</div></a>
  <?php if (isset($kpi['outstanding'])): ?>
  <a class="card kpi<?= $kpi['overdue_invoices'] ? ' danger' : '' ?>" href="<?= h(url('/invoices', ['status' => 'outstanding'])) ?>"><span class="glyph"><?= icon('receipt', 'ic ic-lg') ?></span><div class="label">Outstanding</div><div class="value"><?= h(money((int) $kpi['outstanding'])) ?></div><div class="foot"><?= (int) $kpi['overdue_invoices'] ?> overdue invoices</div></a>
  <?php else: ?>
  <a class="card kpi" href="<?= h(url('/calendar')) ?>"><span class="glyph"><?= icon('calendar', 'ic ic-lg') ?></span><div class="label">Deadlines this week</div><div class="value"><?= (int) $kpi['deadlines'] ?></div><div class="foot">next seven days</div></a>
  <?php endif; ?>
</div>

<div class="grid g-main mt-3">
  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Leads by priority</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/leads')) ?>">All leads</a></div>
      <?php if (!$leads): ?>
        <?php $icon = 'inbox'; $heading = 'No open leads'; $text = 'New case requests from the website appear here, priority deadlines first.'; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?>
      <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>Reference</th><th>Issue</th><th>Source</th><th>Priority</th><th>Received</th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): ?>
          <tr class="<?= $l['priority'] === 'emergency' ? 'emergency' : '' ?>">
            <td data-l="Reference"><a class="row-link ref" href="<?= h(url('/leads/' . $l['id'])) ?>"><?= h($l['ref']) ?></a></td>
            <td data-l="Issue"><?= h($l['title']) ?></td>
            <td data-l="Source"><span class="pill"><?= h(Labels::SOURCES[$l['source']] ?? $l['source']) ?></span></td>
            <td data-l="Priority"><?= View::badge($l['priority']) ?></td>
            <td data-l="Received" class="nowrap muted"><?= h(ago($l['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-h"><h2>Clients waiting for a reply</h2></div>
      <?php if (!$awaiting): ?>
        <?php $icon = 'message'; $heading = 'Inbox zero'; $text = 'No client is waiting on your team.'; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?>
      <ul class="list">
        <?php foreach ($awaiting as $a): ?>
        <li><span class="file-ico"><?= icon('message') ?></span>
          <div class="grow"><a class="item-link" href="<?= h(url('/cases/' . $a['id'], ['tab' => 'messages'])) ?>"><span class="ref"><?= h($a['ref']) ?></span> <?= h($a['display_name']) ?></a><div class="meta"><?= h($a['title']) ?></div></div>
          <span class="muted small nowrap"><?= h(ago($a['msg_at'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-h"><h2>Recently active cases</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/cases')) ?>">All cases</a></div>
      <?php if (!$recent): ?>
        <?php $icon = 'briefcase'; $heading = 'No cases yet'; $text = 'Accept a lead or open a case to get started.'; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?>
      <div class="table-wrap"><table class="table table-stack">
        <thead><tr><th>Case</th><th>Client</th><th>Status</th><th>Activity</th></tr></thead>
        <tbody><?php foreach ($recent as $r): ?>
          <tr><td data-l="Case" class="title-cell"><a class="row-link" href="<?= h(url('/cases/' . $r['id'])) ?>"><span class="ref"><?= h($r['ref']) ?></span></a><small><?= h(str_limit($r['title'], 60)) ?></small></td>
          <td data-l="Client"><?= h($r['display_name']) ?></td><td data-l="Status"><?= View::badge($r['status']) ?></td><td data-l="Activity" class="muted nowrap"><?= h(ago($r['last_activity_at'])) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-h"><h2>Appeal outcomes</h2></div>
      <div class="card-b">
        <div class="donut-wrap">
          <?= Charts::donut($total ? $won / $total * 100 : 0, 'resolved') ?>
          <div class="small">
            <p class="mb-1"><strong><?= $won ?></strong> of <?= $total ?> tracked submissions reinstated, resolved or released.</p>
            <p class="mb-0 muted"><?php $pend = (int) ($removals['pending'] ?? 0); ?><?= $pend ?> <?= $pend === 1 ? 'request' : 'requests' ?> awaiting a platform decision.</p>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-h"><h2>Pipeline</h2></div>
      <div class="card-b hbar-list">
        <?php $pmax = max(1, ...array_values($pipeline ?: [0])); foreach (['lead', 'nda', 'active', 'monitoring', 'closed'] as $s): $v = $pipeline[$s] ?? 0; ?>
          <div class="hb-row"><span><?= h(Labels::get($s)) ?></span><div class="bar"><i class="<?= wclass($v / $pmax * 100) ?>"></i></div><span class="num right"><?= $v ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-h"><h2>My tasks</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/tasks')) ?>">All</a></div>
      <?php if (!$tasks): ?>
        <?php $icon = 'check-square'; $heading = 'Nothing assigned'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?>
      <ul class="list"><?php foreach ($tasks as $t): $late = $t['due_on'] && $t['due_on'] < today(); ?>
        <li><form method="post" action="<?= h(url('/tasks/' . $t['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="done"><input type="hidden" name="_back" value="<?= h(url('/')) ?>"><button class="icon-btn" type="submit" aria-label="Mark done"><?= icon('check') ?></button></form>
          <div class="grow"><div class="truncate"><?= h($t['title']) ?></div><div class="meta"><?= $t['ref'] ? h($t['ref']) . ' &middot; ' : '' ?><span class="<?= $late ? 'text-danger' : '' ?>"><?= $t['due_on'] ? 'Due ' . h(fdate($t['due_on'])) : 'No due date' ?></span></div></div></li>
      <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-h"><h2>Upcoming deadlines</h2><a class="btn btn-ghost btn-sm" href="<?= h(url('/calendar')) ?>">Calendar</a></div>
      <?php if (!$deadlines): ?>
        <?php $icon = 'calendar'; $heading = 'No deadlines in the next 14 days'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
      <?php else: ?>
      <ul class="list"><?php foreach ($deadlines as $d): $late = $d['due_at'] < now(); ?>
        <li><span class="file-ico"><?= icon('clock') ?></span><div class="grow"><div class="truncate"><?= h($d['title']) ?></div><div class="meta"><?= $d['ref'] ? h($d['ref']) . ' &middot; ' : '' ?><span class="<?= $late ? 'text-danger' : '' ?>"><?= h(fdate($d['due_at'], true)) ?></span></div></div></li>
      <?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</div>
