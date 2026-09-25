<?php use DR\Core\Labels; ?>
<details class="menu">
  <summary class="avatar" aria-label="Account menu"><?= h(initials($user['name'])) ?></summary>
  <div class="menu-panel">
    <div class="menu-head"><strong><?= h($user['name']) ?></strong><span><?= h($user['email']) ?></span><br><span><?= h(Labels::ROLES[$user['role']] ?? '') ?></span></div>
    <a href="<?= h(url('/account')) ?>"><?= icon('user') ?>My account and security</a>
    <a href="<?= h(url('/notifications')) ?>"><?= icon('bell') ?>Notifications</a>
    <button type="button" data-theme-toggle><?= icon('moon') ?>Toggle dark mode</button>
    <form method="post" action="<?= h(url('/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?>Sign out</button></form>
  </div>
</details>
