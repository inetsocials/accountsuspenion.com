<?php use DR\Core\Auth; use DR\Core\Labels; use DR\Core\View; $admin = Auth::atLeast($user, 'admin'); ?>
<div class="page-head"><div><div class="eyebrow">Website</div><h1>Testimonials</h1><p class="sub">Only genuine client words, with written consent on file. Published entries appear on the matching website pages. The website shows an average rating once five or more are published.</p></div></div>
<div class="alert alert-info mb-2"><?= icon('shield') ?><div><strong>Verified reviews only</strong>The FTC rule on fake reviews and testimonials (16 CFR Part 465) prohibits invented, paid-for-without-disclosure or misattributed reviews. Record the consent reference for every entry. A different admin approves before anything goes live.</div></div>
<div class="grid g-4 mb-2">
  <div class="card kpi ok"><span class="glyph"><?= icon('check') ?></span><div class="label">Published</div><div class="value"><?= (int) $published ?></div></div>
  <div class="card kpi"><span class="glyph"><?= icon('chart') ?></span><div class="label">Average rating</div><div class="value"><?= $average !== null ? h(number_format($average, 1)) : 'n/a' ?></div><div class="foot"><?= $published >= 5 ? 'Shown on the website' : 'Shown from 5 published reviews' ?></div></div>
</div>
<div class="grid g-side">
  <div class="card">
    <?php if (!$rows): ?><?php $icon = 'message'; $heading = 'No testimonials yet'; $text = 'Ask satisfied clients for a written testimonial and permission to publish it, then record it here.'; include DR_ROOT . '/views/partials/empty.php'; ?>
    <?php else: ?><div class="table-wrap"><table class="table table-stack">
      <thead><tr><th>Client and words</th><th>Rating</th><th>Shown on</th><th>Status</th><th class="right"></th></tr></thead>
      <tbody><?php foreach ($rows as $r): ?>
        <tr>
          <td data-l="Client" class="title-cell"><span><?= h($r['name']) ?><?= $r['role'] ? ', ' . h($r['role']) : '' ?></span><small class="pre"><?= h(str_limit($r['body'], 220)) ?></small><small>Given <?= h(fdate($r['given_on'])) ?>. Consent: <?= h($r['consent_ref']) ?>. Entered by <?= h($r['creator'] ?? '') ?><?= $r['approver'] ? ', approved by ' . h($r['approver']) : '' ?>.</small></td>
          <td data-l="Rating" class="num"><?= (int) $r['rating'] ?> / 5</td>
          <td data-l="Shown on"><?= h(Labels::PLATFORMS[$r['platform'] ?? ''] ?? '') ?><?= $r['service'] ? '<br><span class="small muted">' . h(Labels::SERVICES[$r['service']] ?? '') . '</span>' : '' ?><?= !$r['platform'] && !$r['service'] ? '<span class="small muted">Home page</span>' : '' ?></td>
          <td data-l="Status"><?= View::badge($r['status']) ?></td>
          <td data-l="" class="right nowrap">
            <button type="button" class="btn btn-ghost btn-xs" data-fill="#t-form" data-title="Edit testimonial" data-values="<?= h(json_encode(['id' => $r['id'], 'name' => $r['name'], 'role' => $r['role'], 'platform' => $r['platform'], 'service' => $r['service'], 'rating' => (string) $r['rating'], 'body' => $r['body'], 'given_on' => $r['given_on'], 'consent_ref' => $r['consent_ref']])) ?>"><?= icon('pen') ?>Edit</button>
            <?php if ($admin && $r['status'] !== 'published'): ?><form class="inline" method="post" action="<?= h(url('/cms/testimonials/' . $r['id'] . '/approve')) ?>" data-confirm="Publish this testimonial? Confirm the consent record exists and the words are the client's own."><?= csrf_field() ?><button class="btn btn-primary btn-xs" type="submit"><?= icon('check') ?>Approve</button></form><?php endif; ?>
            <?php if ($admin && $r['status'] === 'published'): ?><form class="inline" method="post" action="<?= h(url('/cms/testimonials/' . $r['id'] . '/hide')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-xs" type="submit">Hide</button></form><?php endif; ?>
            <?php if ($admin): ?><form class="inline" method="post" action="<?= h(url('/cms/testimonials/' . $r['id'] . '/delete')) ?>" data-confirm="Delete this testimonial?" data-danger><?= csrf_field() ?><button class="btn btn-link btn-xs" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div><?php endif; ?>
  </div>
  <form class="card" id="t-form" method="post" action="<?= h(url('/cms/testimonials')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="">
    <div class="card-h"><h2 data-form-title>Add a testimonial</h2></div>
    <div class="card-b">
      <label class="field"><span>Name as the client agreed to be shown</span><input type="text" name="name" required maxlength="120" placeholder="For example: Maria G."></label>
      <label class="field"><span>Descriptor (optional)</span><input type="text" name="role" maxlength="120" placeholder="For example: Amazon seller, Texas"></label>
      <div class="form-grid">
        <label class="field"><span>Platform page</span><select name="platform"><?= options(Labels::PLATFORMS, null, true, 'None') ?></select></label>
        <label class="field"><span>Service page</span><select name="service"><?= options(Labels::SERVICES, null, true, 'None') ?></select></label>
        <label class="field"><span>Rating the client gave</span><select name="rating" required><?= options(['5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1'], '5') ?></select></label>
        <label class="field"><span>Date given</span><input type="date" name="given_on" required max="<?= h(today()) ?>"></label>
      </div>
      <label class="field"><span>Exact words (no edits beyond typos)</span><textarea name="body" rows="5" required minlength="20" maxlength="1500"></textarea></label>
      <label class="field"><span>Consent record</span><input type="text" name="consent_ref" required minlength="6" maxlength="200" placeholder="For example: signed consent form in vault, AS-1A2B3C4D, 08/14/2026"></label>
      <button class="btn btn-primary btn-block mt-1" type="submit">Save for approval</button>
    </div>
  </form>
</div>
