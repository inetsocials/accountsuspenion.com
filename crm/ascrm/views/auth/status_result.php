<div class="card"><div class="card-b">
  <div class="eyebrow">Case status</div>
  <h1 class="ref"><?= h($case['ref']) ?></h1>
  <p><span class="badge badge-<?= $label === 'Closed' ? 'neutral' : ($label === 'Received' ? 'warn' : 'ok') ?>"><?= h($label) ?></span> <span class="muted small">Received <?= h(fdate($case['created_at'], true)) ?></span></p>
  <p class="lead"><?= h($text) ?></p>
  <a class="btn btn-primary btn-block" href="<?= h(url('/login')) ?>">Client sign in</a>
</div></div>
