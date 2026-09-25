<div class="page-head">
  <div><h1>Notifications</h1><p class="sub">Updates about your cases. Emails only tell you something is waiting here.</p></div>
  <form method="post" action="<?= h(url('/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit"><?= icon('check') ?>Mark all read</button></form>
</div>
<div class="card">
  <?php if (!$rows): ?>
    <?php $icon = 'bell'; $heading = 'You are all caught up'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
  <?php else: ?>
  <ul class="list">
    <?php foreach ($rows as $n): ?>
    <li>
      <span class="file-ico"><?= icon($n['read_at'] ? 'check' : 'bell') ?></span>
      <div class="grow"><a class="item-link" href="<?= h(url('/notifications/' . $n['id'])) ?>"><?= h($n['title']) ?></a><div class="meta"><?= h(ago($n['created_at'])) ?></div></div>
      <?php if (!$n['read_at']): ?><span class="badge badge-info">New</span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?= pager($pg, '/notifications') ?>
  <?php endif; ?>
</div>
