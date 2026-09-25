<?php
$stages = ['diagnose' => ['Diagnose', 'Notice and root cause'], 'evidence' => ['Evidence', 'Documents and fixes'], 'appeal' => ['Appeal', 'Official route'], 'protect' => ['Protect', 'Funds and account health']];
$keys = array_keys($stages);
$pos = array_search($stage, $keys, true);
$pos = $pos === false ? 0 : $pos;
$closed = ($status ?? '') === 'closed';
?>
<div class="stepper" role="list" aria-label="Case stage">
<?php foreach ($keys as $i => $k): $cls = ($closed || $i < $pos) ? 'done' : ($i === $pos ? 'current' : ''); ?>
  <div class="step <?= $cls ?>" role="listitem"<?= $cls === 'current' ? ' aria-current="step"' : '' ?>><?= h($stages[$k][0]) ?><small><?= h($stages[$k][1]) ?></small></div>
<?php endforeach; ?>
</div>
