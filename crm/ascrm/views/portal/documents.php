<?php use DR\Core\Settings; $open = array_values(array_filter($cases, fn($c) => $c['status'] !== 'closed' && !((int) $c['nda_required'] === 1 && !$c['nda_signed_at']))); ?>
<div class="page-head"><div><h1>Documents</h1><p class="sub">Every file here is encrypted with a key unique to you and stored under a random name.</p></div></div>
<div class="grid g-side">
  <div class="card">
    <?php if (!$docs): ?><?php $icon = 'folder'; $heading = 'No documents yet'; $text = ''; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Document</th><th>Case</th><th>From</th><th>Date</th><th></th></tr></thead><tbody>
      <?php foreach ($docs as $d): ?><tr>
        <td data-l="Document"><div class="row"><span class="file-ico"><?= h(strtolower(pathinfo($d['name'], PATHINFO_EXTENSION)) ?: 'file') ?></span><div class="title-cell break"><a class="row-link" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>"><?= h($d['name']) ?></a><small><?= h(human_size((int) $d['size'])) ?></small></div></div></td>
        <td data-l="Case" class="ref"><?= h($refs[$d['case_id']] ?? '') ?></td>
        <td data-l="From"><?= in_array($d['uploaded_role'], ['client', 'adviser'], true) ? ((int) $d['uploaded_by'] === (int) $user['id'] ? 'You' : h($d['uploader'] ?? '')) : h(Settings::get('org_name')) ?></td>
        <td data-l="Date" class="nowrap"><?= h(fdate($d['created_at'])) ?></td>
        <td class="right nowrap"><?php if (in_array($d['mime'], ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)): ?><a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/view')) ?>" target="_blank" rel="noopener"><?= icon('eye') ?></a><?php endif; ?><a class="btn btn-ghost btn-xs" href="<?= h(url('/documents/' . $d['id'] . '/download')) ?>" aria-label="Download"><?= icon('download') ?></a></td>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
  <div class="card"><div class="card-h"><h2>Upload</h2></div><div class="card-b">
    <?php if (!$open): ?><p class="muted small mb-0">Uploads open once you have an active case with a signed confidentiality agreement.</p>
    <?php else: $clientId = (int) $open[0]['client_id']; $caseId = null; $staff = false; $cases = $open; $needCase = true; include DR_ROOT . '/views/partials/uploader.php'; endif; ?>
  </div></div>
</div>
