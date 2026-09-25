<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Service\Clients;

final class ClientController extends Controller
{
    /** SQL restricting clients (alias cl) to those the user may see. */
    private function scope(array $u): array
    {
        if (Auth::atLeast($u, 'admin')) {
            return ['1=1', []];
        }
        return ['cl.id IN (SELECT c.client_id FROM cases c LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = :cs_u1 WHERE c.client_id IS NOT NULL AND (c.lead_user_id = :cs_u2 OR s.user_id IS NOT NULL))',
            ['cs_u1' => (int) $u['id'], 'cs_u2' => (int) $u['id']]];
    }

    public function index(array $p, ?array $u): void
    {
        [$scope, $params] = $this->scope($u);
        $where = [$scope, 'cl.erased_at IS NULL'];
        $q = Request::str('q', 80);
        if ($q !== '') {
            $where[] = '(cl.display_name LIKE :q1 OR cl.number LIKE :q2)';
            $params += ['q1' => '%' . $q . '%', 'q2' => '%' . $q . '%'];
        }
        $status = Request::oneOf('status', ['active' => 1, 'inactive' => 1], '');
        if ($status) {
            $where[] = 'cl.status = :st';
            $params['st'] = $status;
        }
        $w = implode(' AND ', $where);
        $pg = $this->paginate("SELECT COUNT(*) FROM clients cl WHERE $w", $params, 30);
        $rows = Db::all(
            "SELECT cl.*,
               (SELECT COUNT(*) FROM cases c WHERE c.client_id = cl.id AND c.status IN ('qualified','nda','active','monitoring')) AS open_cases,
               (SELECT COUNT(*) FROM cases c WHERE c.client_id = cl.id) AS all_cases,
               (SELECT MAX(c.last_activity_at) FROM cases c WHERE c.client_id = cl.id) AS last_activity,
               (SELECT COUNT(*) FROM documents d WHERE d.client_id = cl.id AND d.deleted_at IS NULL) AS docs
             FROM clients cl WHERE $w ORDER BY cl.display_name LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        $this->view('staff/clients', ['title' => 'Clients', 'rows' => $rows, 'pg' => $pg, 'q' => $q, 'status' => $status], 'app');
    }

    public function createForm(array $p, ?array $u): void
    {
        $this->view('staff/client_new', ['title' => 'New client'], 'app');
    }

    public function create(array $p, ?array $u): void
    {
        $name = Request::str('display_name', 160);
        if ($name === '') {
            $this->fail('Enter a client name or codename.', '/clients/new');
        }
        $email = mb_strtolower(Request::str('email', 190));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('The contact email address is not valid.', '/clients/new');
        }
        $client = Clients::create($name, Request::oneOf('type', Labels::CLIENT_TYPE, 'individual'), !empty($_POST['codename']),
            Request::oneOf('risk_level', Labels::RISK, 'standard'), (int) $u['id']);
        $notes = Request::text('notes', 20000);
        if ($notes !== '') {
            Db::update('clients', ['notes_enc' => Crypto::forClient((int) $client['id'], $notes)], 'id = :id', ['id' => (int) $client['id']]);
        }
        $contact = Request::str('contact_name', 120);
        if ($contact !== '' || $email !== '') {
            Db::insert('contacts', ['client_id' => (int) $client['id'], 'kind' => 'primary', 'name' => $contact ?: $name, 'email' => $email ?: null,
                'organisation' => Request::str('organisation', 160) ?: null, 'notes_enc' => null, 'created_at' => now()]);
        }
        Audit::log('client_created', 'client', (int) $client['id'], [], $u);
        if (!empty($_POST['invite']) && $email !== '') {
            try {
                Clients::inviteUser($client, $email, $contact, 'client', $u);
                $this->flash('ok', 'Client created and portal invitation sent.');
            } catch (HttpError $e) {
                $this->flash('warn', 'Client created. Invitation not sent: ' . $e->getMessage());
            }
        } else {
            $this->flash('ok', 'Client ' . $client['number'] . ' created with its own encrypted vault.');
        }
        App::redirect('/clients/' . $client['id']);
    }

    public function show(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $cid = (int) $client['id'];
        $tab = Request::oneOf('tab', ['overview' => 1, 'documents' => 1, 'invoices' => 1, 'notes' => 1, 'activity' => 1], 'overview');
        [$scope, $sp] = Access::caseScope($u);
        $data = [
            'title' => $client['display_name'], 'client' => $client, 'tab' => $tab,
            'cases' => Db::all("SELECT c.*, us.name AS lead_name FROM cases c LEFT JOIN users us ON us.id = c.lead_user_id WHERE $scope AND c.client_id = :cid ORDER BY c.last_activity_at DESC", $sp + ['cid' => $cid]),
            'contacts' => Db::all('SELECT * FROM contacts WHERE client_id = ? ORDER BY id', [$cid]),
            'users' => Db::all("SELECT u.* FROM users u WHERE (u.role = 'client' AND u.client_id = ?) OR (u.role = 'adviser' AND u.id IN (SELECT user_id FROM client_advisers WHERE client_id = ?)) ORDER BY u.role, u.name", [$cid, $cid]),
        ];
        if ($tab === 'documents') {
            $data['docs'] = DocumentController::listFor($u, $cid);
            $data['folders'] = DocumentController::foldersFor($cid);
        } elseif ($tab === 'invoices') {
            $data['invoices'] = Db::all('SELECT i.*, c.ref FROM invoices i LEFT JOIN cases c ON c.id = i.case_id WHERE i.client_id = ? ORDER BY i.issued_on DESC, i.id DESC', [$cid]);
        } elseif ($tab === 'notes') {
            $data['notes'] = (string) Crypto::fromClient($cid, $client['notes_enc']);
        } elseif ($tab === 'activity') {
            $data['activity'] = Db::all(
                "SELECT a.*, us.name AS user_name FROM audit_log a LEFT JOIN users us ON us.id = a.user_id
                 WHERE (a.entity = 'client' AND a.entity_id = ?) OR (a.entity = 'document' AND a.meta LIKE ?) ORDER BY a.id DESC LIMIT 100",
                [(string) $cid, '{"client_id":' . $cid . ',%']
            );
        }
        $screen = json_decode((string) $client['screening'], true) ?: [];
        $data['screening'] = $screen;
        $this->view('staff/client', $data, 'app');
    }

    public function update(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $section = Request::oneOf('section', ['details' => 1, 'notes' => 1], 'details');
        if ($section === 'notes') {
            Db::update('clients', ['notes_enc' => Crypto::forClient((int) $client['id'], Request::text('notes', 50000)), 'updated_at' => now()], 'id = :id', ['id' => (int) $client['id']]);
            Audit::log('client_notes_updated', 'client', (int) $client['id'], [], $u);
            $this->flash('ok', 'Notes saved (encrypted with the client key).');
            App::redirect('/clients/' . $client['id'], ['tab' => 'notes']);
        }
        $name = Request::str('display_name', 160);
        if ($name === '') {
            $this->fail('Enter a client name or codename.', '/clients/' . $client['id']);
        }
        Db::update('clients', [
            'display_name' => $name, 'type' => Request::oneOf('type', Labels::CLIENT_TYPE, $client['type']),
            'is_codename' => !empty($_POST['codename']) ? 1 : 0, 'risk_level' => Request::oneOf('risk_level', Labels::RISK, $client['risk_level']),
            'status' => Request::oneOf('status', ['active' => 1, 'inactive' => 1], $client['status']), 'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $client['id']]);
        Audit::log('client_updated', 'client', (int) $client['id'], [], $u);
        $this->flash('ok', 'Client updated.');
        App::redirect('/clients/' . $client['id']);
    }

    public function inviteUser(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $email = mb_strtolower(Request::str('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Enter a valid email address.', '/clients/' . $client['id']);
        }
        $role = Request::oneOf('role', ['client' => 1, 'adviser' => 1], 'client');
        try {
            Clients::inviteUser($client, $email, Request::str('name', 120), $role, $u);
        } catch (HttpError $e) {
            $this->fail($e->getMessage(), '/clients/' . $client['id']);
        }
        $this->flash('ok', ($role === 'adviser' ? 'Adviser' : 'Portal user') . ' invited. The invitation link is valid for seven days.');
        App::redirect('/clients/' . $client['id']);
    }

    public function resendInvite(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $user = Db::one('SELECT * FROM users WHERE id = ?', [$this->id($p, 'uid')]);
        $belongs = $user && (($user['role'] === 'client' && (int) $user['client_id'] === (int) $client['id'])
            || ($user['role'] === 'adviser' && Db::value('SELECT 1 FROM client_advisers WHERE client_id = ? AND user_id = ?', [(int) $client['id'], (int) $user['id']])));
        if (!$belongs || $user['status'] !== 'invited') {
            throw new HttpError('Invitation not found.', 404);
        }
        Clients::sendInvite($user);
        Audit::log('invite_resent', 'user', (int) $user['id'], [], $u);
        $this->flash('ok', 'A fresh invitation was sent. Earlier links no longer work.');
        App::redirect('/clients/' . $client['id']);
    }

    public function addContact(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $name = Request::str('name', 120);
        $email = mb_strtolower(Request::str('email', 190));
        if ($name === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $this->fail('Enter a contact name and, if given, a valid email address.', '/clients/' . $client['id']);
        }
        Db::insert('contacts', [
            'client_id' => (int) $client['id'], 'kind' => Request::oneOf('kind', ['primary' => 1, 'billing' => 1, 'legal' => 1, 'adviser' => 1, 'other' => 1], 'other'),
            'name' => $name, 'email' => $email ?: null, 'organisation' => Request::str('organisation', 160) ?: null,
            'notes_enc' => Crypto::forClient((int) $client['id'], Request::text('notes', 2000)), 'created_at' => now(),
        ]);
        Audit::log('contact_added', 'client', (int) $client['id'], [], $u);
        $this->flash('ok', 'Contact added.');
        App::redirect('/clients/' . $client['id']);
    }

    public function deleteContact(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        Db::run('DELETE FROM contacts WHERE id = ? AND client_id = ?', [$this->id($p, 'cid'), (int) $client['id']]);
        Audit::log('contact_deleted', 'client', (int) $client['id'], [], $u);
        $this->flash('ok', 'Contact removed.');
        App::redirect('/clients/' . $client['id']);
    }

    public function erase(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, $this->id($p));
        $this->requireSudo(url('/clients/' . $client['id']));
        if (Request::str('confirm', 20) !== $client['number']) {
            $this->fail('Type the client number exactly to confirm erasure.', '/clients/' . $client['id']);
        }
        $open = (int) Db::value("SELECT COUNT(*) FROM invoices WHERE client_id = ? AND status IN ('sent','partial')", [(int) $client['id']]);
        if ($open > 0 && empty($_POST['force'])) {
            $this->fail('This client has unpaid invoices. Tick "erase anyway" to continue.', '/clients/' . $client['id']);
        }
        $r = Clients::erase($client, $u);
        $this->flash('ok', 'Client erased. ' . $r['files'] . ' encrypted files destroyed and the client key deleted. Invoices were kept for accounting, de-identified.');
        App::redirect('/clients');
    }
}
