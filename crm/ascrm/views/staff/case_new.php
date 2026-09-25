<?php use DR\Core\Labels; ?>
<div class="page-head"><div><div class="eyebrow">Cases</div><h1>New case</h1><p class="sub">For existing clients. Website enquiries become cases when you accept the lead.</p></div></div>
<?php if (!$clients): ?>
  <div class="card"><?php $icon = 'users'; $heading = 'No clients available'; $text = 'Create a client first.'; include DR_ROOT . '/views/partials/empty.php'; ?><div class="card-f center"><a class="btn btn-primary" href="<?= h(url('/clients/new')) ?>">New client</a></div></div>
<?php else: ?>
<form class="card" method="post" action="<?= h(url('/cases')) ?>">
  <?= csrf_field() ?>
  <div class="card-b form-grid">
    <label class="field"><span>Client</span><select name="client_id" required><option value="">Choose client</option><?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected((int) $c['id'] === (int) $clientId) ?>><?= h($c['number'] . ' ' . $c['display_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Case title</span><input type="text" name="title" required value="<?= h(old('title')) ?>" placeholder="For example: Amazon deactivation, related account"></label>
    <label class="field"><span>Issue</span><select name="issue"><?= options(Labels::ISSUES, old('issue'), true) ?></select></label>
    <label class="field"><span>Platform</span><select name="platform" required><?= options(Labels::PLATFORMS, old('platform'), true) ?></select></label>
    <label class="field"><span>Appeals so far</span><select name="appeal_history"><?= options(Labels::HISTORY, old('appeal_history', 'none')) ?></select></label>
    <label class="field"><span>Client type</span><select name="subject_type"><?= options(Labels::SUBJECT, old('subject_type'), true) ?></select></label>
    <label class="field"><span>Country</span><select name="jurisdiction"><?= options(Labels::JURIS, old('jurisdiction', 'US'), true) ?></select></label>
    <label class="field"><span>Priority</span><select name="priority"><?= options(Labels::PRIORITY, old('priority', 'medium')) ?></select></label>
    <label class="field"><span>Starting stage</span><select name="stage"><?= options(Labels::STAGES, 'diagnose') ?></select></label>
    <label class="field"><span>Case lead</span><select name="lead_user_id"><?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected((int) $s['id'] === (int) $user['id']) ?>><?= h($s['name']) ?></option><?php endforeach; ?></select></label>
    <label class="field full"><span>Services in scope</span><textarea name="services" rows="3" placeholder="For example: case review, Plan of Action, held funds release request"><?= h(old('services')) ?></textarea></label>
    <label class="check full"><input type="checkbox" name="nda_required" value="1" checked><span>Client must sign the engagement agreement in the portal before messaging or uploading</span></label>
  </div>
  <div class="card-f row-between"><a class="btn btn-ghost" href="<?= h(url('/cases')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Open case</button></div>
</form>
<?php endif; ?>
