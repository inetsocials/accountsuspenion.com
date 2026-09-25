<?php use DR\Core\Settings; ?>
<div class="page-head"><div><div class="eyebrow">Invoices</div><h1>New invoice</h1><p class="sub">Saved as a draft first. Issue it when ready and the client is notified in the portal.</p></div></div>
<form class="card" method="post" action="<?= h(url('/invoices')) ?>">
  <?= csrf_field() ?>
  <div class="card-b form-grid">
    <label class="field"><span>Client</span><select name="client_id" required data-client-select><option value="">Choose client</option><?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected((int) $c['id'] === (int) $clientId) ?>><?= h($c['number'] . ' ' . $c['display_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Case (optional)</span><select name="case_id" data-case-select><option value="">Not linked to a case</option><?php foreach ($cases as $c): ?><option value="<?= (int) $c['id'] ?>" data-client="<?= (int) $c['client_id'] ?>"<?= selected((int) $c['id'] === (int) $caseId) ?>><?= h($c['ref'] . ' ' . str_limit($c['title'], 50)) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>Issue date</span><input type="date" name="issued_on" value="<?= h(today()) ?>"></label>
    <label class="field"><span>Due date</span><input type="date" name="due_on" value="<?= h(date('Y-m-d', strtotime('+' . Settings::int('invoice_terms_days') . ' days'))) ?>"></label>
    <label class="field"><span>Currency</span><select name="currency"><?= options(['USD' => 'USD', 'CAD' => 'CAD', 'GBP' => 'GBP', 'EUR' => 'EUR'], Settings::get('currency')) ?></select></label>
    <label class="field"><span>Discount (amount, before tax)</span><input type="number" name="discount" step="0.01" min="0" value="0"></label>
    <div class="full"><?php $items = []; $currency = Settings::get('currency'); include DR_ROOT . '/views/staff/_items.php'; ?></div>
    <label class="field full mt-2"><span>Notes on the invoice</span><textarea name="notes" rows="2" placeholder="For example: Case review and Plan of Action"></textarea></label>
  </div>
  <div class="card-f row-between"><a class="btn btn-ghost" href="<?= h(url('/invoices')) ?>">Cancel</a><button class="btn btn-primary" type="submit">Save draft</button></div>
</form>
