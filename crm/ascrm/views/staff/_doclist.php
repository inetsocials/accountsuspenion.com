<?php
/* Vars: $docs, $folders, $user, $docsBack */
use DR\Core\Auth;
use DR\Core\Labels;
use DR\Core\View;
$canLead = Auth::atLeast($user, 'lead');
?>
<?php if (!$docs): ?>
  <?php $icon = 'lock'; $heading = 'The vault is empty'; $text = 'Uploads are encrypted on arrival with this client\'s own key.'; include DR_ROOT . '/views/partials/empty.php'; ?>
<?php else: ?>
<div class="table-wrap"><table class="table table-stack">
  <thead><tr><th>Document</th><th>Sharing</th><th>Folder</th><th>Uploaded</th><th class="right">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): $ext = strtolower(pathinfo($d['name'], PATHINFO_EXTENSION)); ?>
    <tr>
      <td data-l="Document"><div class="row"><span class="file-ico"><?= h($ext ?: 'file') ?></span><div class="title-cell break"><a class="row-link" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>"><?= h($d['name']) ?></a><small><?= h(human_size((int) $d['size'])) ?><?= (int) $d['version'] > 1 ? ', version ' . (int) $d['version'] : '' ?><?= !(int) $d['is_current'] ? ', superseded' : '' ?><?= $d['message_id'] ? ', message attachment' : '' ?></small></div></div></td>
      <td data-l="Sharing"><?= View::badge($d['visibility'] === 'shared' ? 'active' : ($d['visibility'] === 'confidential' ? 'high' : 'draft'), Labels::VISIBILITY[$d['visibility']] ?? $d['visibility']) ?></td>
      <td data-l="Folder"><?= h($folders[(int) $d['folder_id']] ?? '') ?></td>
      <td data-l="Uploaded" class="nowrap"><?= h(fdate($d['created_at'], true)) ?><br><span class="muted small"><?= h($d['uploader'] ?? '') ?> (<?= h(Labels::ROLES[$d['uploaded_role']] ?? '') ?>)</span></td>
      <td data-l="Actions" class="right nowrap">
        <?php if (in_array($d['mime'], ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)): ?><a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/view')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?>View</a><?php endif; ?>
        <a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>"><?= icon('download') ?></a>
        <details class="menu inline"><summary class="btn btn-ghost btn-xs" aria-label="More"><?= icon('sliders') ?></summary>
          <div class="menu-panel">
            <form method="post" action="<?= h(url('/documents/' . $d['id'] . '/update')) ?>" class="card-b">
              <?= csrf_field() ?><input type="hidden" name="_back" value="<?= h($docsBack) ?>">
              <label class="field"><span>Sharing</span><select name="visibility" class="input-sm"><?= options(Labels::VISIBILITY, $d['visibility']) ?></select></label>
              <label class="field"><span>Folder</span><select name="folder_id" class="input-sm"><option value="">No folder</option><?php foreach ($folders as $fid => $fname): ?><option value="<?= (int) $fid ?>"<?= selected((int) $d['folder_id'] === (int) $fid) ?>><?= h($fname) ?></option><?php endforeach; ?></select></label>
              <button class="btn btn-primary btn-sm btn-block" type="submit">Save</button>
            </form>
            <?php if ($canLead || (int) $d['uploaded_by'] === (int) $user['id']): ?>
            <form method="post" action="<?= h(url('/documents/' . $d['id'] . '/delete')) ?>" data-confirm="Delete this version and destroy its encrypted file? This cannot be undone." data-danger><?= csrf_field() ?><input type="hidden" name="_back" value="<?= h($docsBack) ?>"><button type="submit" class="text-danger"><?= icon('trash') ?>Delete and destroy file</button></form>
            <?php endif; ?>
          </div>
        </details>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
