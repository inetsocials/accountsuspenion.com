<?php if (!empty($flashes)): ?>
<div class="flash-stack" aria-live="polite">
<?php foreach ($flashes as [$type, $msg]): ?>
  <div class="flash <?= h($type) ?>" role="status"><?= icon($type === 'ok' ? 'check' : 'alert') ?><div><?= h($msg) ?></div><button type="button" aria-label="Dismiss"><?= icon('x') ?></button></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
