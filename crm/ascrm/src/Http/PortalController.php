<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Core\Session;
use DR\Core\Settings;
use DR\Core\Vault;
use DR\Service\Cases;
use DR\Service\Notify;
use DR\Service\ZipStream;

/** Client and adviser portal. Every query is scoped by Access::caseScope / clientVisible. */
final class PortalController extends Controller
{
    private function clientIds(array $u): array
    {
        if ($u['role'] === 'client') {
            return [(int) $u['client_id']];
        }
        return array_map('intval', array_column(Db::all('SELECT client_id FROM client_advisers WHERE user_id = ?', [(int) $u['id']]), 'client_id'));
    }

    private function inList(array $ids): string
    {
        return $ids ? implode(',', array_map('intval', $ids)) : '0';
    }

    public function home(array $p, ?array $u): void
    {
        [$scope, $sp] = Access::caseScope($u);
        $cases = Db::all(
            "SELECT c.*, cl.display_name, us.name AS lead_name FROM cases c JOIN clients cl ON cl.id = c.client_id LEFT JOIN users us ON us.id = c.lead_user_id
             WHERE $scope AND cl.erased_at IS NULL ORDER BY CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END, c.last_activity_at DESC",
            $sp
        );
        foreach ($cases as &$c) {
            $c['unread'] = Cases::unreadFor((int) $c['id'], $u, false);
            $c['targets'] = Db::one("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('approved','suppressed') THEN 1 ELSE 0 END) AS won,
                SUM(CASE WHEN status IN ('submitted','acknowledged','appealed') THEN 1 ELSE 0 END) AS pending FROM targets WHERE case_id = ? AND client_visible = 1", [(int) $c['id']]);
        }
        unset($c);
        $ids = $this->inList($this->clientIds($u));
        $invoices = Db::all("SELECT * FROM invoices WHERE client_id IN ($ids) AND status IN ('sent','partial') ORDER BY due_on");
        $docs = [];
        foreach (Db::all("SELECT * FROM documents WHERE client_id IN ($ids) AND deleted_at IS NULL AND visibility = 'shared' AND uploaded_role NOT IN ('client','adviser') ORDER BY id DESC LIMIT 5") as $d) {
            if (Access::documentVisible($u, $d)) {
                $docs[] = $d + ['name' => (string) Crypto::fromClient((int) $d['client_id'], $d['name_enc'])];
            }
        }
        $deadlines = Db::all("SELECT d.*, c.ref FROM deadlines d JOIN cases c ON c.id = d.case_id WHERE $scope AND d.client_visible = 1 AND d.status = 'upcoming' ORDER BY d.due_at LIMIT 5", $sp);
        $this->view('portal/home', ['title' => 'Overview', 'cases' => $cases, 'invoices' => $invoices, 'docs' => $docs, 'deadlines' => $deadlines], 'portal');
    }

    private function loadCase(array $p, array $u): array
    {
        $case = Access::loadCase($u, $this->id($p));
        if (Db::value('SELECT erased_at FROM clients WHERE id = ?', [(int) $case['client_id']])) {
            throw new HttpError('Case not found.', 404);
        }
        return $case;
    }

    private function ndaPending(array $case): bool
    {
        return (int) $case['nda_required'] === 1 && !$case['nda_signed_at'];
    }

    public function case(array $p, ?array $u): void
    {
        $case = $this->loadCase($p, $u);
        $tab = Request::oneOf('tab', ['overview' => 1, 'messages' => 1, 'documents' => 1, 'tracker' => 1, 'invoices' => 1], 'overview');
        $nda = $this->ndaPending($case);
        if ($nda && in_array($tab, ['messages', 'documents'], true)) {
            $tab = 'overview';
        }
        $data = [
            'title' => $case['ref'], 'case' => $case, 'tab' => $tab, 'ndaPending' => $nda,
            'lead' => $case['lead_user_id'] ? Db::one('SELECT name FROM users WHERE id = ?', [(int) $case['lead_user_id']]) : null,
            'unread' => Cases::unreadFor((int) $case['id'], $u, false),
            'ndaText' => Settings::get('nda_text'),
            'signature' => Db::one('SELECT signed_name, signed_at FROM nda_signatures WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $case['id']]),
        ];
        $targets = TrackerController::targets($case, true);
        $data['targetStats'] = [
            'total' => count($targets),
            'won' => count(array_filter($targets, fn($t) => in_array($t['status'], ['approved', 'suppressed'], true))),
            'pending' => count(array_filter($targets, fn($t) => in_array($t['status'], ['submitted', 'acknowledged', 'appealed'], true))),
        ];
        switch ($tab) {
            case 'overview':
                $data['deadlines'] = Db::all("SELECT * FROM deadlines WHERE case_id = ? AND client_visible = 1 AND status = 'upcoming' ORDER BY due_at", [(int) $case['id']]);
                $data['fundTotals'] = TrackerController::fundTotals(TrackerController::funds($case, true));
                break;
            case 'messages':
                $data['thread'] = Cases::thread($case, false);
                Cases::markRead((int) $case['id'], (int) $u['id']);
                break;
            case 'documents':
                $data['docs'] = DocumentController::listFor($u, (int) $case['client_id'], (int) $case['id']);
                break;
            case 'tracker':
                $data['targets'] = $targets;
                $data['funds'] = TrackerController::funds($case, true);
                break;
            case 'invoices':
                $data['invoices'] = Db::all("SELECT * FROM invoices WHERE case_id = ? AND status NOT IN ('draft') ORDER BY issued_on DESC", [(int) $case['id']]);
                break;
        }
        $this->view('portal/case', $data, 'portal');
    }

    public function message(array $p, ?array $u): void
    {
        $case = $this->loadCase($p, $u);
        if ($this->ndaPending($case)) {
            throw new HttpError('Please sign the confidentiality agreement first.', 403);
        }
        if ($case['status'] === 'closed') {
            throw new HttpError('This case is closed. Start a new case on our website.', 422);
        }
        if (!\DR\Core\RateLimit::attempt('client_msg:' . $u['id'], 60, 3600)) {
            throw new HttpError('Message limit reached. Please wait a little and try again.', 429);
        }
        $body = Request::text('body', 20000);
        if ($body === '' && !empty($_POST['has_files'])) {
            $body = '(attachment)';
        }
        $mid = Cases::postMessage($case, $u, $body, false);
        Notify::caseTeam($case, 'client_message', 'New client message on ' . $case['ref'], (int) $u['id']);
        Notify::clientUsers((int) $case['client_id'], 'message', 'New message on your case ' . $case['ref'], '/client/cases/' . $case['id'] . '?tab=messages', (int) $u['id']);
        if (Request::wantsJson()) {
            App::json(['ok' => true, 'message_id' => $mid, 'case_id' => (int) $case['id']]);
        }
        App::redirect('/client/cases/' . $case['id'], ['tab' => 'messages']);
    }

    public function signNda(array $p, ?array $u): void
    {
        $case = $this->loadCase($p, $u);
        if (!$this->ndaPending($case)) {
            App::redirect('/client/cases/' . $case['id']);
        }
        $name = Request::str('signed_name', 160);
        if (mb_strlen($name) < 3 || empty($_POST['agree'])) {
            $this->fail('Type your full name and tick the box to sign.', '/client/cases/' . $case['id']);
        }
        $text = Settings::get('nda_text');
        $record = $text . "\n\nCase: " . $case['ref'] . "\nSigned by: " . $name . ' (' . $u['email'] . ")\nSigned at: " . fdate(now(), true) . ' (' . date_default_timezone_get() . ')';
        Db::tx(function () use ($case, $u, $name, $record): void {
            Db::insert('nda_signatures', [
                'case_id' => (int) $case['id'], 'user_id' => (int) $u['id'], 'signed_name' => $name,
                'text_enc' => Crypto::forClient((int) $case['client_id'], $record), 'text_hash' => hash('sha256', $record),
                'ip' => Request::ip(), 'user_agent' => Request::userAgent(), 'signed_at' => now(),
            ]);
            Db::update('cases', ['nda_signed_at' => now(), 'status' => $case['status'] === 'nda' ? 'active' : $case['status'], 'updated_at' => now(), 'last_activity_at' => now()],
                'id = :id', ['id' => (int) $case['id']]);
        });
        Audit::log('nda_signed', 'case', (int) $case['id'], ['hash' => substr(hash('sha256', $record), 0, 16)], $u);
        Notify::caseTeam($case, 'nda', 'Agreement signed on ' . $case['ref'], (int) $u['id']);
        $this->flash('ok', 'Thank you. The agreement is signed and your secure messages and document vault are now open.');
        App::redirect('/client/cases/' . $case['id'], ['tab' => 'messages']);
    }

    public function documents(array $p, ?array $u): void
    {
        [$scope, $sp] = Access::caseScope($u);
        $cases = Db::all("SELECT c.id, c.ref, c.title, c.client_id, c.nda_required, c.nda_signed_at, c.status FROM cases c WHERE $scope ORDER BY c.ref", $sp);
        $docs = [];
        foreach ($this->clientIds($u) as $cid) {
            foreach (DocumentController::listFor($u, $cid) as $d) {
                $docs[] = $d;
            }
        }
        usort($docs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $refs = array_column($cases, 'ref', 'id');
        $this->view('portal/documents', ['title' => 'Documents', 'docs' => $docs, 'cases' => $cases, 'refs' => $refs], 'portal');
    }

    public function invoices(array $p, ?array $u): void
    {
        $ids = $this->inList($this->clientIds($u));
        $rows = Db::all("SELECT i.*, c.ref FROM invoices i LEFT JOIN cases c ON c.id = i.case_id WHERE i.client_id IN ($ids) AND i.status NOT IN ('draft','cancelled') ORDER BY i.issued_on DESC");
        $this->view('portal/invoices', ['title' => 'Invoices', 'rows' => $rows, 'bank' => Settings::get('bank_details')], 'portal');
    }

    /** Subject access style export: JSON records plus every visible document, streamed as a ZIP. */
    public function export(array $p, ?array $u): void
    {
        if (!\DR\Core\RateLimit::attempt('export:' . $u['id'], 3, 3600)) {
            throw new HttpError('You can export up to three times an hour.', 429);
        }
        [$scope, $sp] = Access::caseScope($u);
        $cases = Db::all("SELECT c.* FROM cases c WHERE $scope ORDER BY c.id", $sp);
        Audit::log('data_exported', 'user', (int) $u['id'], ['cases' => count($cases)], $u);
        Session::save();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="my-data-' . date('Y-m-d') . '.zip"');
        header('X-Content-Type-Options: nosniff');
        $zip = new ZipStream(fopen('php://output', 'wb'));
        $profile = ['name' => $u['name'], 'email' => $u['email'], 'role' => Labels::ROLES[$u['role']], 'created' => fdate($u['created_at'], true), 'last_sign_in' => fdate($u['last_login_at'], true)];
        $zip->addString('README.txt', "Your data from " . Settings::get('org_name') . ", exported on " . fdate(now(), true) . " (" . date_default_timezone_get() . ").\n\nprofile.json  your account\ncases/<ref>/case.json  case record, messages, appeal tracker, held funds, invoices\ncases/<ref>/documents/  documents shared with you or uploaded by you\n");
        $zip->addString('profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($cases as $case) {
            $thread = array_map(fn($m) => ['at' => fdate($m['created_at'], true), 'from' => $m['sender_name'], 'message' => $m['body']], Cases::thread($case, false));
            $targets = array_map(fn($t) => ['account_or_item' => $t['url'], 'platform' => $t['platform'], 'route' => Labels::ROUTES[$t['route']] ?? $t['route'],
                'status' => Labels::TARGET_STATUS[$t['status']] ?? $t['status'], 'submitted' => fdate($t['submitted_on']), 'decided' => fdate($t['decided_on']), 'outcome' => $t['outcome']],
                TrackerController::targets($case, true));
            $funds = array_map(fn($f) => ['platform' => $f['platform'], 'held' => money((int) $f['amount'], $f['currency']), 'released' => money((int) $f['released'], $f['currency']),
                'status' => Labels::FUNDS_STATUS[$f['status']] ?? $f['status'], 'held_since' => fdate($f['held_since']), 'expected_release' => fdate($f['expected_on']), 'notes' => $f['notes']],
                TrackerController::funds($case, true));
            $inv = array_map(fn($i) => ['number' => $i['number'], 'issued' => fdate($i['issued_on']), 'due' => fdate($i['due_on']), 'total' => money((int) $i['total'], $i['currency']), 'paid' => money((int) $i['paid'], $i['currency']), 'status' => Labels::INVOICE_STATUS[$i['status']]],
                Db::all("SELECT * FROM invoices WHERE case_id = ? AND status NOT IN ('draft') ORDER BY issued_on", [(int) $case['id']]));
            $record = [
                'reference' => $case['ref'], 'title' => $case['title'], 'status' => Labels::get($case['status']), 'stage' => Labels::get($case['stage']),
                'opened' => fdate($case['opened_at']), 'closed' => fdate($case['closed_at']), 'agreement_signed' => fdate($case['nda_signed_at'], true),
                'messages' => $thread, 'appeal_tracker' => $targets, 'held_funds' => $funds, 'invoices' => $inv,
            ];
            $zip->addString('cases/' . $case['ref'] . '/case.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $used = [];
            foreach (DocumentController::listFor($u, (int) $case['client_id'], (int) $case['id']) as $d) {
                $name = Vault::cleanName($d['name']);
                $base = $name;
                $i = 2;
                while (isset($used[mb_strtolower($name)])) {
                    $name = pathinfo($base, PATHINFO_FILENAME) . " ($i)" . (pathinfo($base, PATHINFO_EXTENSION) ? '.' . pathinfo($base, PATHINFO_EXTENSION) : '');
                    $i++;
                }
                $used[mb_strtolower($name)] = true;
                $zip->addStream('cases/' . $case['ref'] . '/documents/' . $name, function (callable $emit) use ($d): void {
                    Vault::decryptEach($d, $emit);
                });
            }
        }
        $zip->finish();
        exit;
    }
}
