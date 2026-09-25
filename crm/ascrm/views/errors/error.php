<?php
$titles = [400 => 'Bad request', 401 => 'Please sign in', 403 => 'Access denied', 404 => 'Not found', 405 => 'Not allowed', 410 => 'No longer available', 413 => 'Too large', 419 => 'Session expired', 422 => 'Please check and try again', 429 => 'Slow down', 503 => 'Maintenance', 500 => 'Something went wrong'];
?>
<div class="card"><div class="card-b">
  <div class="eyebrow">Error <?= (int) $code ?></div>
  <h1><?= h($titles[$code] ?? 'Error') ?></h1>
  <p class="lead"><?= h($message) ?></p>
  <div class="row mt-2">
    <a class="btn btn-primary" href="<?= h(url('/')) ?>">Go to the portal</a>
    <a class="btn btn-ghost" href="<?= h(url('/login')) ?>">Sign in</a>
  </div>
</div></div>
