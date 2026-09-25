<?php
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Service\Charts;
$labels = array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($months));
$hbars = function (array $data, array $map) {
    $max = max(1, ...array_values($data ?: [0]));
    $out = '<div class="hbar-list">';
    foreach ($data as $k => $v) {
        $out .= '<div class="hb-row"><span class="truncate">' . h($map[$k] ?? ($k === '' ? 'Not set' : ucfirst((string) $k))) . '</span><div class="bar"><i class="' . wclass($v / $max * 100) . '"></i></div><span class="num right">' . (int) $v . '</span></div>';
    }
    return $out . '</div>';
};
$totW = 0; $totL = 0;
foreach ($targets as $t) { $totW += $t['won']; $totL += $t['lost']; }
?>
<div class="page-head">
  <div><h1>Reports</h1><p class="sub">Last twelve months, for the cases you can access.</p></div>
  <?php if (Auth::atLeast($user, 'admin')): ?><a class="btn btn-ghost" href="<?= h(url('/reports/export')) ?>"><?= icon('download') ?>Export CSV</a><?php endif; ?>
</div>
<div class="grid g-4 mb-2">
  <div class="card kpi"><span class="glyph"><?= icon('inbox') ?></span><div class="label">Leads (12 months)</div><div class="value"><?= array_sum(array_column($months, 'leads')) ?></div></div>
  <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Accepted</div><div class="value"><?= array_sum(array_column($months, 'accepted')) ?></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('clock') ?></span><div class="label">Median time to accept</div><div class="value"><?= $medianHours === null ? 'n/a' : ($medianHours < 48 ? round($medianHours) . 'h' : round($medianHours / 24) . 'd') ?></div></div>
  <div class="card kpi ok"><span class="glyph"><?= icon('target') ?></span><div class="label">Resolution rate</div><div class="value"><?= ($totW + $totL) ? round($totW / ($totW + $totL) * 100) . '%' : 'n/a' ?></div><div class="foot">decided requests only</div></div>
</div>
<div class="grid g-2">
  <div class="card span-2">
    <div class="card-h"><h2>Leads and accepted cases per month</h2><div class="legend"><span><i class="la"></i>Leads</span><span><i class="lb"></i>Accepted</span></div></div>
    <div class="card-b"><?= Charts::bars($labels, [['bar-a', 'Leads', array_column($months, 'leads')], ['bar-b', 'Accepted', array_column($months, 'accepted')]]) ?></div>
  </div>
  <?php if (Auth::atLeast($user, 'admin')): ?>
  <div class="card span-2">
    <div class="card-h"><h2>Payments received per month</h2></div>
    <div class="card-b"><?= Charts::bars($labels, [['bar-c', 'Received', array_map(fn($r) => $r['revenue'] / 100, $months)]], 220, fn($v) => "\u{00A3}" . number_format($v)) ?></div>
  </div>
  <?php endif; ?>
  <div class="card"><div class="card-h"><h2>Where leads come from</h2></div><div class="card-b"><?= $hbars($source, Labels::SOURCES) ?></div></div>
  <div class="card"><div class="card-h"><h2>Issues</h2></div><div class="card-b"><?= $hbars($issue, Labels::ISSUES) ?></div></div>
  <div class="card"><div class="card-h"><h2>Case status</h2></div><div class="card-b"><?= $hbars($status, Labels::CASE_STATUS) ?></div></div>
  <div class="card"><div class="card-h"><h2>Platforms</h2></div><div class="card-b"><?= $hbars($juris, Labels::PLATFORMS) ?></div></div>
  <div class="card span-2">
    <div class="card-h"><h2>Outcomes by route</h2></div>
    <?php if (!$targets): ?><?php $icon = 'target'; $heading = 'No tracked submissions yet'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Route</th><th class="right">Tracked</th><th class="right">Won</th><th class="right">Refused</th><th class="right">Pending</th><th>Success</th><th class="right">Median days</th></tr></thead><tbody>
      <?php foreach ($targets as $route => $r): $dec = $r['won'] + $r['lost']; $days = $r['days']; sort($days); $pct = $dec ? $r['won'] / $dec * 100 : 0; ?>
      <tr><td data-l="Route"><?= h(Labels::ROUTES[$route] ?? $route) ?></td><td data-l="Tracked" class="right num"><?= $r['total'] ?></td><td data-l="Won" class="right num text-ok"><?= $r['won'] ?></td><td data-l="Refused" class="right num"><?= $r['lost'] ?></td><td data-l="Pending" class="right num"><?= $r['pending'] ?></td>
      <td data-l="Success"><?php if ($dec): ?><div class="bar ok"><i class="<?= wclass($pct) ?>"></i></div><span class="small"><?= round($pct) ?>%</span><?php else: ?><span class="muted small">Undecided</span><?php endif; ?></td>
      <td data-l="Median days" class="right num"><?= $days ? $days[intdiv(count($days), 2)] : '' ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
  </div>
  <?php if ($workload): ?>
  <div class="card span-2">
    <div class="card-h"><h2>Team workload</h2></div>
    <div class="table-wrap"><table class="table table-stack"><thead><tr><th>Name</th><th>Role</th><th class="right">Leading</th><th class="right">Open tasks</th><th class="right">Overdue</th></tr></thead><tbody>
      <?php foreach ($workload as $w): ?><tr><td data-l="Name"><?= h($w['name']) ?></td><td data-l="Role"><?= h(Labels::ROLES[$w['role']]) ?></td><td data-l="Leading" class="right num"><?= (int) $w['lead_count'] ?></td><td data-l="Open tasks" class="right num"><?= (int) $w['open_tasks'] ?></td><td data-l="Overdue" class="right num <?= $w['overdue'] ? 'text-danger' : '' ?>"><?= (int) $w['overdue'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>
</div>
