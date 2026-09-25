<?php
use DR\Core\Settings;
$groups = [];
foreach ($fields as $k => $f) { $groups[$f[2]][$k] = $f; }
?>
<div class="page-head"><div><h1>Settings</h1><p class="sub">Master admin only. Every change is audited.</p></div>
  <form method="post" action="<?= h(url('/admin/settings/test-mail')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('mail') ?>Send test email</button></form></div>
<form method="post" action="<?= h(url('/admin/settings')) ?>" class="stack">
  <?= csrf_field() ?>
  <?php foreach ($groups as $g => $items): ?>
  <div class="card">
    <div class="card-h"><h2><?= h($g) ?></h2></div>
    <div class="card-b form-grid">
      <?php foreach ($items as $k => [$label, $type, , $help]): $v = $type === 'password' ? '' : Settings::get($k); $full = $type === 'textarea'; ?>
        <label class="field<?= $full ? ' full' : '' ?>"><span><?= h($label) ?></span>
        <?php if ($type === 'textarea'): ?><textarea name="<?= h($k) ?>" rows="<?= $k === 'nda_text' ? 14 : 3 ?>"><?= h($v) ?></textarea>
        <?php elseif (str_starts_with($type, 'select:')): $opts = []; foreach (explode(',', substr($type, 7)) as $o) { [$ov, $ol] = explode('=', $o, 2); $opts[$ov] = $ol; } ?><select name="<?= h($k) ?>"><?= options($opts, $v) ?></select>
        <?php else: ?><input type="<?= h($type) ?>" name="<?= h($k) ?>" value="<?= h($v) ?>"<?= $type === 'password' ? ' autocomplete="new-password" placeholder="' . (Settings::get($k) !== '' ? 'Saved (hidden)' : 'Not set') . '"' : '' ?>>
        <?php endif; ?>
        <?php if ($help): ?><span class="hint"><?= h($help) ?></span><?php endif; ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="row"><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>
