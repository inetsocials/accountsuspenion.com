<?php
/* Encrypted chunked uploader. Vars: $clientId, $caseId, $staff (bool), $folders (array), $cases (client picker, optional) */
use DR\Core\Labels;
use DR\Core\Settings;
$staff = $staff ?? false;
?>
<div data-uploader data-client="<?= (int) ($clientId ?? 0) ?>" data-case="<?= (int) ($caseId ?? 0) ?: '' ?>"<?= !empty($needCase) ? ' data-need-case' : '' ?>>
  <?php if ($staff || !empty($cases)): ?>
  <div class="form-grid mb-1">
    <?php if (!empty($cases)): ?>
    <label class="field full"><span>Case</span><select name="case_pick"><option value="">Choose the case</option>
      <?php foreach ($cases as $c): ?><option value="<?= (int) $c['id'] ?>"><?= h($c['ref'] . ' ' . $c['title']) ?></option><?php endforeach; ?>
    </select></label>
    <?php endif; ?>
    <?php if ($staff): ?>
    <label class="field"><span>Who can see it</span><select name="visibility"><?= options(Labels::VISIBILITY, 'internal') ?></select></label>
    <label class="field"><span>Folder</span><select name="folder_id"><option value="">No folder</option><?php foreach ($folders ?? [] as $fid => $fname): ?><option value="<?= (int) $fid ?>"><?= h($fname) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <label class="dropzone">
    <input type="file" multiple aria-label="Choose files to upload">
    <?= icon('upload') ?>
    <strong>Drop files here or click to choose</strong>
    <span class="small">PDF, images, Office files, email, audio and video up to <?= (int) Settings::int('upload_max_mb') ?> MB each</span>
  </label>
  <div class="vault-note mt-1"><?= icon('lock') ?>Sent over TLS, encrypted on arrival piece by piece and stored under a random name in a private vault. The original is never saved unencrypted.</div>
  <ul class="up-list"></ul>
</div>
