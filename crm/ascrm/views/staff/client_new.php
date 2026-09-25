<?php use DR\Core\Labels; ?>
<div class="page-head"><div><div class="eyebrow">Clients</div><h1>New client</h1><p class="sub">Creates a client number, a unique data key and a private vault folder with a 64-character random name.</p></div></div>
<form class="card" method="post" action="<?= h(url('/clients')) ?>">
  <?= csrf_field() ?>
  <div class="card-b form-grid">
    <label class="field"><span>Client name or codename</span><input type="text" name="display_name" required value="<?= h(old('display_name')) ?>"></label>
    <label class="field"><span>Type</span><select name="type"><?= options(Labels::CLIENT_TYPE, old('type', 'individual')) ?></select></label>
    <label class="check full"><input type="checkbox" name="codename" value="1"><span>Use as a codename so the real identity is not shown in lists</span></label>
    <label class="field"><span>Risk level</span><select name="risk_level"><?= options(Labels::RISK, old('risk_level', 'standard')) ?></select></label>
    <div></div>
    <label class="field"><span>Primary contact name</span><input type="text" name="contact_name" value="<?= h(old('contact_name')) ?>"></label>
    <label class="field"><span>Primary contact email</span><input type="email" name="email" value="<?= h(old('email')) ?>"></label>
    <label class="field"><span>Company</span><input type="text" name="organisation" value="<?= h(old('organisation')) ?>"></label>
    <div></div>
    <label class="field full"><span>Private notes</span><textarea name="notes" rows="4" placeholder="Encrypted with this client's key"><?= h(old('notes')) ?></textarea></label>
    <label class="check full"><input type="checkbox" name="invite" value="1"><span>Send a portal invitation to the contact email</span></label>
  </div>
  <div class="card-f row-between"><a class="btn btn-ghost" href="<?= h(url('/clients')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= icon('lock') ?>Create client and vault</button></div>
</form>
