<div class="page-head"><div><h1>Security, keys and backups</h1><p class="sub">Master admin controls. Actions here ask you to confirm your identity.</p></div></div>
<?php if ($maintenance): ?><div class="alert alert-warn mb-2"><?= icon('alert') ?><div><strong>Maintenance mode is on</strong>Only master admins can use the portal.</div></div><?php endif; ?>
<div class="grid g-4 mb-2">
  <div class="card kpi ok"><span class="glyph"><?= icon('lock') ?></span><div class="label">Client keys</div><div class="value"><?= (int) $clients ?></div><div class="foot">one per client, wrapped by the master key</div></div>
  <div class="card kpi"><span class="glyph"><?= icon('archive') ?></span><div class="label">Encrypted vault</div><div class="value"><?= h(human_size((int) $vaultBytes)) ?></div><div class="foot"><?= (int) $vaultFiles ?> files</div></div>
  <div class="card kpi <?= $failed24 > 20 ? 'danger' : '' ?>"><span class="glyph"><?= icon('alert') ?></span><div class="label">Failed or denied (24h)</div><div class="value"><?= (int) $failed24 ?></div><div class="foot"><a href="<?= h(url('/admin/audit', ['security' => 1])) ?>">Review</a></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('clock') ?></span><div class="label">Scheduler last ran</div><div class="value small"><?= $cronLast ? h(ago($cronLast)) : 'Never' ?></div><div class="foot"><?= $cronLast && strtotime($cronLast) < time() - 7200 ? '<span class="text-danger">Check the cron job</span>' : 'every 15 minutes recommended' ?></div></div>
</div>
<div class="grid g-2">
  <div class="card">
    <div class="card-h"><h2>Master encryption key</h2></div>
    <div class="card-b">
      <dl class="props mb-2"><dt>Fingerprint</dt><dd class="mono"><?= h($fingerprint) ?></dd><dt>Location</dt><dd class="mono small break"><?= h($keyPath) ?></dd><dt>Last rotated</dt><dd><?= $lastRotation ? h(fdate($lastRotation, true)) : 'Never' ?></dd></dl>
      <p class="small">Rotation creates a new master key and re-wraps every client key, intake, authenticator secret and sealed setting in one transaction. Files do not need re-encrypting. The previous key is kept as <code>.prev</code> so older backups stay restorable.</p>
      <form method="post" action="<?= h(url('/admin/security/rotate')) ?>" data-confirm="Rotate the master key now? Download a fresh backup afterwards and store the new key offline." data-danger><?= csrf_field() ?><button class="btn btn-danger" type="submit"><?= icon('key') ?>Rotate master key</button></form>
    </div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Encrypted backups</h2>
      <form method="post" action="<?= h(url('/admin/security/backup')) ?>"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit"><?= icon('archive') ?>Create backup</button></form></div>
    <?php if (!$backups): ?><?php $icon = 'archive'; $heading = 'No backups yet'; $text = 'Database backups are encrypted with a key derived from the master key. Copy storage/vault separately; its files are already encrypted.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><ul class="list"><?php foreach ($backups as $b): ?><li><span class="file-ico"><?= icon('archive') ?></span><div class="grow"><div class="mono small"><?= h($b['name']) ?></div><div class="meta"><?= h(fdate($b['at'], true)) ?>, <?= h(human_size((int) $b['size'])) ?></div></div><a class="btn btn-ghost btn-xs" href="<?= h(url('/admin/security/backup/' . $b['name'])) ?>"><?= icon('download') ?>Download</a></li><?php endforeach; ?></ul>
    <div class="card-f small muted">Backups older than 30 days are removed automatically. Restore with <code>php cli/tool.php backup:restore FILE</code>.</div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-h"><h2>Scheduler</h2></div>
    <div class="card-b small">
      <p>Runs deadline reminders, removes abandoned uploads, expired sessions and tokens, and applies retention. Add one of these in hPanel &gt; Advanced &gt; Cron Jobs, every 15 minutes:</p>
      <code class="secret">php <?= h(DR_ROOT) ?>/cli/cron.php</code>
      <p class="mt-1 mb-1">Or call this private URL from an external scheduler (keep it secret):</p>
      <code class="secret" id="cron-url"><?= h($cronUrl) ?></code>
      <button type="button" class="btn btn-ghost btn-xs mt-1" data-copy="#cron-url">Copy URL</button>
    </div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Retention and maintenance</h2></div>
    <div class="card-b">
      <p class="small">Declined lead details are erased after the period set in Settings, and old audit rows are removed. This also runs automatically each night.</p>
      <div class="row">
        <form method="post" action="<?= h(url('/admin/security/retention')) ?>" data-confirm="Apply the retention schedule now?"><?= csrf_field() ?><button class="btn btn-ghost" type="submit"><?= icon('refresh') ?>Apply retention now</button></form>
        <form method="post" action="<?= h(url('/admin/security/maintenance')) ?>" data-confirm="<?= $maintenance ? 'Turn maintenance mode off?' : 'Turn maintenance mode on? Clients and staff will be locked out until you turn it off.' ?>"><?= csrf_field() ?><button class="btn <?= $maintenance ? 'btn-primary' : 'btn-soft-danger' ?>" type="submit"><?= icon('server') ?><?= $maintenance ? 'End maintenance' : 'Start maintenance' ?></button></form>
      </div>
    </div>
  </div>
</div>
