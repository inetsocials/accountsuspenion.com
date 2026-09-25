<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Numbers;
use DR\Core\Request;
use DR\Core\Settings;
use DR\Service\Invoices;
use DR\Service\Notify;

/** Invoicing with optional sales tax, discounts and manual payment recording (no card processing). */
final class InvoiceController extends Controller
{
    private function load(int $id, array $u): array
    {
        $inv = Db::one('SELECT * FROM invoices WHERE id = ?', [$id]);
        if (!$inv) {
            throw new HttpError('Invoice not found.', 404);
        }
        if (Auth::isStaff($u)) {
            if (!Auth::atLeast($u, 'admin')) {
                Access::loadClient($u, (int) $inv['client_id']);
            }
        } elseif (!Access::clientVisible($u, (int) $inv['client_id']) || in_array($inv['status'], ['draft'], true)) {
            throw new HttpError('Invoice not found.', 404);
        }
        return $inv;
    }

    public function index(array $p, ?array $u): void
    {
        $where = ['1=1'];
        $params = [];
        if (!Auth::atLeast($u, 'admin')) {
            $where[] = 'i.client_id IN (SELECT c.client_id FROM cases c LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = :u1 WHERE c.lead_user_id = :u2 OR s.user_id IS NOT NULL)';
            $params += ['u1' => (int) $u['id'], 'u2' => (int) $u['id']];
        }
        $baseWhere = implode(' AND ', $where);
        $baseParams = $params;
        $status = Request::oneOf('status', Labels::INVOICE_STATUS + ['overdue' => 1, 'outstanding' => 1], '');
        if ($status === 'overdue') {
            $where[] = "i.status IN ('sent','partial') AND i.due_on < :today";
            $params['today'] = today();
        } elseif ($status === 'outstanding') {
            $where[] = "i.status IN ('sent','partial')";
        } elseif ($status !== '') {
            $where[] = 'i.status = :st';
            $params['st'] = $status;
        }
        $w = implode(' AND ', $where);
        $pg = $this->paginate("SELECT COUNT(*) FROM invoices i WHERE $w", $params, 30);
        $rows = Db::all(
            "SELECT i.*, cl.display_name, cl.number AS client_number, c.ref FROM invoices i JOIN clients cl ON cl.id = i.client_id LEFT JOIN cases c ON c.id = i.case_id
             WHERE $w ORDER BY i.issued_on DESC, i.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        $totals = Db::one(
            "SELECT COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') THEN i.total - i.paid ELSE 0 END), 0) AS outstanding,
                    COALESCE(SUM(CASE WHEN i.status IN ('sent','partial') AND i.due_on < :t2 THEN i.total - i.paid ELSE 0 END), 0) AS overdue,
                    COALESCE(SUM(CASE WHEN i.status <> 'cancelled' THEN i.paid ELSE 0 END), 0) AS received
             FROM invoices i WHERE $baseWhere",
            $baseParams + ['t2' => today()]
        );
        $this->view('staff/invoices', ['title' => 'Invoices', 'rows' => $rows, 'pg' => $pg, 'status' => $status, 'totals' => $totals], 'app');
    }

    public function createForm(array $p, ?array $u): void
    {
        $clients = Auth::atLeast($u, 'admin')
            ? Db::all("SELECT id, number, display_name FROM clients WHERE erased_at IS NULL ORDER BY display_name")
            : Db::all('SELECT DISTINCT cl.id, cl.number, cl.display_name FROM clients cl JOIN cases c ON c.client_id = cl.id LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = ?
                       WHERE cl.erased_at IS NULL AND (c.lead_user_id = ? OR s.user_id IS NOT NULL) ORDER BY cl.display_name', [(int) $u['id'], (int) $u['id']]);
        [$scope, $sp] = Access::caseScope($u);
        $cases = Db::all("SELECT c.id, c.ref, c.title, c.client_id FROM cases c WHERE $scope AND c.client_id IS NOT NULL ORDER BY c.ref", $sp);
        $this->view('staff/invoice_new', [
            'title' => 'New invoice', 'clients' => $clients, 'cases' => $cases, 'catalog' => Db::all('SELECT * FROM catalog WHERE active = 1 ORDER BY name'),
            'clientId' => Request::int('client'), 'caseId' => Request::int('case'),
        ], 'app');
    }

    /** Parse item rows from the form. Returns list of [description, qty_x100, unit, vat_bp]. */
    private function items(): array
    {
        $desc = $_POST['item_desc'] ?? [];
        $qty = $_POST['item_qty'] ?? [];
        $unit = $_POST['item_unit'] ?? [];
        $vat = $_POST['item_vat'] ?? [];
        if (!is_array($desc)) {
            return [];
        }
        $out = [];
        foreach (array_values($desc) as $i => $d) {
            $d = is_string($d) ? trim(mb_substr($d, 0, 255)) : '';
            if ($d === '') {
                continue;
            }
            $q = (int) round(((float) (is_string($qty[$i] ?? null) ? $qty[$i] : '1')) * 100);
            $q = max(1, min(1000000, $q));
            $up = to_minor(is_string($unit[$i] ?? null) ? $unit[$i] : '0');
            $vb = (int) round(((float) (is_string($vat[$i] ?? null) ? $vat[$i] : '0')) * 100);
            $out[] = [$d, $q, max(-100000000, min(100000000, $up)), max(0, min(10000, $vb))];
            if (count($out) >= 60) {
                break;
            }
        }
        return $out;
    }

    private function writeItems(int $invoiceId, array $items): void
    {
        Db::run('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
        foreach ($items as $i => [$d, $q, $up, $vb]) {
            Db::insert('invoice_items', ['invoice_id' => $invoiceId, 'description' => $d, 'qty_x100' => $q, 'unit_price' => $up, 'vat_bp' => $vb,
                'line_total' => Invoices::lineNet($q, $up), 'sort' => $i]);
        }
    }

    public function create(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, Request::int('client_id'));
        $caseId = Request::int('case_id') ?: null;
        if ($caseId) {
            $case = Access::loadCase($u, $caseId);
            if ((int) $case['client_id'] !== (int) $client['id']) {
                $this->fail('That case belongs to a different client.', '/invoices/new');
            }
        }
        $items = $this->items();
        if (!$items) {
            $this->fail('Add at least one line item.', '/invoices/new');
        }
        $issued = Request::date('issued_on') ?? today();
        $due = Request::date('due_on') ?? date('Y-m-d', strtotime($issued . ' +' . Settings::int('invoice_terms_days') . ' days'));
        $id = Db::tx(function () use ($client, $caseId, $issued, $due, $items, $u): int {
            $id = Db::insert('invoices', [
                'number' => Numbers::invoiceNumber(), 'client_id' => (int) $client['id'], 'case_id' => $caseId, 'status' => 'draft',
                'currency' => Request::oneOf('currency', ['USD' => 1, 'CAD' => 1, 'GBP' => 1, 'EUR' => 1], Settings::get('currency')), 'issued_on' => $issued,
                'due_on' => $due < $issued ? $issued : $due, 'subtotal' => 0, 'discount' => max(0, to_minor(Request::str('discount', 20))), 'vat' => 0, 'total' => 0, 'paid' => 0,
                'notes' => Request::text('notes', 4000) ?: null, 'created_by' => (int) $u['id'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->writeItems($id, $items);
            return $id;
        });
        Invoices::recalc($id);
        Audit::log('invoice_created', 'invoice', $id, ['client_id' => (int) $client['id']], $u);
        $this->flash('ok', 'Draft invoice created. Review it, then issue it to the client.');
        App::redirect('/invoices/' . $id);
    }

    public function show(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        $this->view('staff/invoice', [
            'title' => 'Invoice ' . $inv['number'], 'inv' => $inv,
            'client' => Db::one('SELECT * FROM clients WHERE id = ?', [(int) $inv['client_id']]),
            'case' => $inv['case_id'] ? Db::one('SELECT id, ref, title FROM cases WHERE id = ?', [(int) $inv['case_id']]) : null,
            'items' => Db::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort, id', [(int) $inv['id']]),
            'payments' => Db::all('SELECT p.*, us.name AS by_name FROM payments p LEFT JOIN users us ON us.id = p.created_by WHERE p.invoice_id = ? ORDER BY p.paid_on, p.id', [(int) $inv['id']]),
            'catalog' => Db::all('SELECT * FROM catalog WHERE active = 1 ORDER BY name'),
        ], 'app');
    }

    public function update(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        if ($inv['status'] !== 'draft') {
            throw new HttpError('Only draft invoices can be edited. Cancel and re-issue if needed.', 422);
        }
        $items = $this->items();
        if (!$items) {
            $this->fail('Add at least one line item.', '/invoices/' . $inv['id']);
        }
        $issued = Request::date('issued_on') ?? $inv['issued_on'];
        $due = Request::date('due_on') ?? $inv['due_on'];
        Db::tx(function () use ($inv, $items, $issued, $due): void {
            Db::update('invoices', ['issued_on' => $issued, 'due_on' => $due < $issued ? $issued : $due, 'discount' => max(0, to_minor(Request::str('discount', 20))),
                'notes' => Request::text('notes', 4000) ?: null, 'updated_at' => now()], 'id = :id', ['id' => (int) $inv['id']]);
            $this->writeItems((int) $inv['id'], $items);
        });
        Invoices::recalc((int) $inv['id']);
        Audit::log('invoice_updated', 'invoice', (int) $inv['id'], [], $u);
        $this->flash('ok', 'Invoice saved.');
        App::redirect('/invoices/' . $inv['id']);
    }

    public function send(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        if ($inv['status'] !== 'draft') {
            throw new HttpError('This invoice has already been issued.', 422);
        }
        if ((int) $inv['total'] <= 0) {
            $this->fail('The invoice total must be above zero.', '/invoices/' . $inv['id']);
        }
        Db::update('invoices', ['status' => 'sent', 'updated_at' => now()], 'id = :id', ['id' => (int) $inv['id']]);
        Invoices::recalc((int) $inv['id']);
        Notify::clientUsers((int) $inv['client_id'], 'invoice', 'A new invoice is available: ' . $inv['number'], '/client/invoices');
        Audit::log('invoice_sent', $inv['case_id'] ? 'case' : 'invoice', $inv['case_id'] ?: $inv['id'], ['invoice_id' => (int) $inv['id']], $u);
        $this->flash('ok', 'Invoice issued. The client was notified to view it in the portal.');
        App::redirect('/invoices/' . $inv['id']);
    }

    public function cancel(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        if ((int) $inv['paid'] > 0) {
            throw new HttpError('Remove recorded payments before cancelling, or issue a credit note.', 422);
        }
        Db::update('invoices', ['status' => 'cancelled', 'updated_at' => now()], 'id = :id', ['id' => (int) $inv['id']]);
        Audit::log('invoice_cancelled', 'invoice', (int) $inv['id'], [], $u);
        $this->flash('ok', 'Invoice cancelled.');
        App::redirect('/invoices/' . $inv['id']);
    }

    public function payment(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        if (!in_array($inv['status'], ['sent', 'partial', 'paid'], true)) {
            throw new HttpError('Issue the invoice before recording payments.', 422);
        }
        $amount = to_minor(Request::str('amount', 20));
        if ($amount === 0) {
            $this->fail('Enter the amount received (negative for a refund).', '/invoices/' . $inv['id']);
        }
        $ref = Request::str('reference', 120);
        $paidOn = Request::date('paid_on') ?? today();
        // Idempotency: the same amount, date and reference is not recorded twice.
        if (Db::value('SELECT id FROM payments WHERE invoice_id = ? AND amount = ? AND paid_on = ? AND COALESCE(reference, \'\') = ?', [(int) $inv['id'], $amount, $paidOn, $ref])) {
            $this->fail('That payment is already recorded.', '/invoices/' . $inv['id']);
        }
        Db::insert('payments', ['invoice_id' => (int) $inv['id'], 'amount' => $amount, 'method' => Request::oneOf('method', Labels::PAY_METHODS, 'bank_transfer'),
            'paid_on' => $paidOn, 'reference' => $ref ?: null, 'created_by' => (int) $u['id'], 'created_at' => now()]);
        $new = Invoices::recalc((int) $inv['id']);
        Audit::log('payment_recorded', 'invoice', (int) $inv['id'], ['amount' => $amount], $u);
        if ($new['status'] === 'paid' && $inv['status'] !== 'paid') {
            Notify::clientUsers((int) $inv['client_id'], 'invoice_paid', 'Payment received for ' . $inv['number'] . '. Thank you.', '/client/invoices');
        }
        $this->flash('ok', 'Payment recorded.');
        App::redirect('/invoices/' . $inv['id']);
    }

    public function deletePayment(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        Db::run('DELETE FROM payments WHERE id = ? AND invoice_id = ?', [$this->id($p, 'pid'), (int) $inv['id']]);
        Invoices::recalc((int) $inv['id']);
        Audit::log('payment_deleted', 'invoice', (int) $inv['id'], [], $u);
        $this->flash('ok', 'Payment removed.');
        App::redirect('/invoices/' . $inv['id']);
    }

    public function printView(array $p, ?array $u): void
    {
        $inv = $this->load($this->id($p), $u);
        if (!Auth::isStaff($u) && $inv['status'] === 'cancelled') {
            throw new HttpError('Invoice not found.', 404);
        }
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [(int) $inv['client_id']]);
        $billing = Db::one("SELECT * FROM contacts WHERE client_id = ? ORDER BY CASE kind WHEN 'billing' THEN 0 WHEN 'primary' THEN 1 ELSE 2 END, id LIMIT 1", [(int) $inv['client_id']]);
        $this->view('shared/invoice_print', [
            'title' => $inv['number'], 'inv' => $inv, 'client' => $client, 'billing' => $billing,
            'case' => $inv['case_id'] ? Db::one('SELECT ref, title FROM cases WHERE id = ?', [(int) $inv['case_id']]) : null,
            'items' => Db::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort, id', [(int) $inv['id']]),
            'payments' => Db::all('SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_on', [(int) $inv['id']]),
        ], 'print');
    }
}
