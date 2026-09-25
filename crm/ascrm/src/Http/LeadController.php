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
use DR\Core\Mailer;
use DR\Core\Request;
use DR\Core\Settings;
use DR\Service\Cases;
use DR\Service\Clients;
use DR\Service\Notify;

/** Website intake: screening, accept (opens client file + vault + invite) or decline. */
final class LeadController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        [$scope, $sp] = Access::caseScope($u);
        $status = Request::oneOf('status', ['lead' => 1, 'declined' => 1, 'all' => 1], 'lead');
        $where = [$scope, $status === 'all' ? "c.status IN ('lead','declined')" : 'c.status = :st'];
        $params = $sp + ($status === 'all' ? [] : ['st' => $status]);
        if ($pr = Request::oneOf('priority', Labels::PRIORITY)) {
            $where[] = 'c.priority = :pr';
            $params['pr'] = $pr;
        }
        if ($src = Request::oneOf('source', Labels::SOURCES)) {
            $where[] = 'c.source = :src';
            $params['src'] = $src;
        }
        $w = implode(' AND ', $where);
        $pg = $this->paginate("SELECT COUNT(*) FROM cases c WHERE $w", $params, 30);
        $rows = Db::all(
            "SELECT c.*, u.name AS lead_name FROM cases c LEFT JOIN users u ON u.id = c.lead_user_id WHERE $w
             ORDER BY CASE c.priority WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, c.created_at DESC
             LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        $this->view('staff/leads', ['title' => 'Leads inbox', 'rows' => $rows, 'pg' => $pg, 'status' => $status, 'filters' => ['status' => $status, 'priority' => $pr, 'source' => $src]], 'app');
    }

    private function loadLead(array $p, array $u): array
    {
        $case = Access::loadCase($u, $this->id($p));
        if (!in_array($case['status'], ['lead', 'declined'], true)) {
            App::redirect('/cases/' . $case['id']);
        }
        return $case;
    }

    public function show(array $p, ?array $u): void
    {
        $case = $this->loadLead($p, $u);
        $intake = Cases::intake($case);
        $email = mb_strtolower((string) ($intake['email'] ?? ''));
        $existingUser = $email !== '' ? Db::one('SELECT u.*, cl.display_name AS client_name, cl.number AS client_number FROM users u LEFT JOIN clients cl ON cl.id = u.client_id WHERE u.email = ?', [$email]) : null;
        $related = $case['intake_email_hash']
            ? Db::all('SELECT id, ref, status, created_at FROM cases WHERE intake_email_hash = ? AND id <> ? ORDER BY id DESC LIMIT 10', [$case['intake_email_hash'], (int) $case['id']])
            : [];
        Audit::log('lead_viewed', 'case', (int) $case['id'], [], $u);
        $this->view('staff/lead', [
            'title' => 'Lead ' . $case['ref'], 'case' => $case, 'intake' => $intake, 'existingUser' => $existingUser, 'related' => $related,
            'screening' => json_decode((string) $case['screening'], true) ?: [], 'staff' => Access::staffList(),
            'leadUser' => $case['lead_user_id'] ? Db::one('SELECT id, name FROM users WHERE id = ?', [(int) $case['lead_user_id']]) : null,
            'activity' => Cases::activity((int) $case['id'], 20),
        ], 'app');
    }

    public function claim(array $p, ?array $u): void
    {
        $case = $this->loadLead($p, $u);
        Db::update('cases', ['lead_user_id' => (int) $u['id'], 'updated_at' => now()], 'id = :id', ['id' => (int) $case['id']]);
        Audit::log('lead_claimed', 'case', (int) $case['id'], [], $u);
        $this->flash('ok', 'You are now the case lead for ' . $case['ref'] . '.');
        App::redirect('/leads/' . $case['id']);
    }

    public function screening(array $p, ?array $u): void
    {
        $case = $this->loadLead($p, $u);
        $checked = array_values(array_intersect(Request::arr('checks'), array_keys(Labels::SCREENING)));
        $data = ['checks' => $checked, 'notes' => Request::text('notes', 4000), 'by' => (int) $u['id'], 'by_name' => $u['name'], 'at' => now()];
        Db::update('cases', ['screening' => json_encode($data), 'updated_at' => now()], 'id = :id', ['id' => (int) $case['id']]);
        Audit::log('lead_screened', 'case', (int) $case['id'], ['checks' => count($checked)], $u);
        $this->flash('ok', 'Screening saved.');
        App::redirect('/leads/' . $case['id']);
    }

    public function accept(array $p, ?array $u): void
    {
        $case = $this->loadLead($p, $u);
        if ($case['status'] !== 'lead') {
            throw new HttpError('Only open leads can be accepted.', 422);
        }
        $screen = json_decode((string) $case['screening'], true) ?: [];
        $missing = array_diff(array_keys(Labels::SCREENING), $screen['checks'] ?? []);
        if ($missing) {
            $this->fail('Complete and save every screening check before accepting. Missing: ' . implode('; ', array_map(fn($k) => Labels::SCREENING[$k], $missing)) . '.', '/leads/' . $case['id']);
        }
        $intake = Cases::intake($case);
        $email = mb_strtolower(trim((string) ($intake['email'] ?? '')));
        $mode = Request::oneOf('client_mode', ['new' => 1, 'existing' => 1], 'new');
        $leadUser = Request::int('lead_user_id') ?: (int) ($case['lead_user_id'] ?: $u['id']);
        if (!Db::value("SELECT id FROM users WHERE id = ? AND role IN ('master','admin','lead','staff') AND status = 'active'", [$leadUser])) {
            $leadUser = (int) $u['id'];
        }
        $ndaRequired = !empty($_POST['nda_required']) ? 1 : 0;
        $title = Request::str('title', 190) ?: $case['title'];

        $client = Db::tx(function () use ($mode, $intake, $email, $u, $case, $leadUser, $ndaRequired, $title) {
            if ($mode === 'existing') {
                $client = Access::loadClient($u, Request::int('existing_client_id'));
            } else {
                $codename = !empty($_POST['codename']);
                $display = Request::str('display_name', 160) ?: ((string) ($intake['name'] ?? '') ?: 'Client ' . $case['ref']);
                $client = Clients::create($display, Request::oneOf('client_type', Labels::CLIENT_TYPE, ($intake['subject_type'] ?? '') === 'business' ? 'organisation' : 'individual'),
                    $codename, Request::oneOf('risk_level', Labels::RISK, 'standard'), (int) $u['id']);
                if ($email !== '') {
                    Db::insert('contacts', [
                        'client_id' => (int) $client['id'], 'kind' => 'primary', 'name' => mb_substr((string) ($intake['name'] ?? $display), 0, 120) ?: $display,
                        'email' => $email, 'organisation' => null, 'notes_enc' => null, 'created_at' => now(),
                    ]);
                }
            }
            Db::update('cases', [
                'client_id' => (int) $client['id'], 'status' => $ndaRequired ? 'nda' : 'active', 'title' => $title, 'lead_user_id' => $leadUser,
                'nda_required' => $ndaRequired, 'opened_at' => now(), 'updated_at' => now(), 'last_activity_at' => now(),
            ], 'id = :id', ['id' => (int) $case['id']]);
            return $client;
        });

        $invited = false;
        if (!empty($_POST['invite']) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $role = ($intake['subject_type'] ?? '') === 'adviser' ? 'adviser' : 'client';
            try {
                Clients::inviteUser($client, $email, (string) ($intake['name'] ?? ''), $role, $u);
                $invited = true;
            } catch (HttpError $e) {
                $this->flash('warn', 'Client file opened, but no invitation was sent: ' . $e->getMessage());
            }
        }
        Audit::log('lead_accepted', 'case', (int) $case['id'], ['client_id' => (int) $client['id'], 'invited' => $invited], $u);
        if ($leadUser !== (int) $u['id']) {
            Notify::user($leadUser, 'case_assigned', 'You are case lead for ' . $case['ref'], '/cases/' . $case['id']);
        }
        $this->flash('ok', 'Lead accepted. Client file ' . $client['number'] . ' opened with its own encrypted vault' . ($invited ? ' and a portal invitation was sent.' : '.'));
        App::redirect('/cases/' . $case['id']);
    }

    public function decline(array $p, ?array $u): void
    {
        $case = $this->loadLead($p, $u);
        $reason = Request::text('reason', 2000);
        if ($reason === '') {
            $this->fail('Record why the lead is declined (kept internally).', '/leads/' . $case['id']);
        }
        Db::update('cases', ['status' => 'declined', 'decline_reason' => $reason, 'closed_at' => now(), 'updated_at' => now()], 'id = :id', ['id' => (int) $case['id']]);
        $told = false;
        if (!empty($_POST['notify'])) {
            $email = (string) (Cases::intake($case)['email'] ?? '');
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $told = Mailer::notice($email, 'About your request ' . $case['ref'],
                    'Thank you for contacting ' . Settings::get('org_name') . '. We have reviewed your request carefully and we are not able to take it on. This is not a judgment on you or your situation. If your circumstances change, you are welcome to start a new case on our website.');
            }
        }
        Audit::log('lead_declined', 'case', (int) $case['id'], ['notified' => $told], $u);
        $this->flash('ok', 'Lead declined' . ($told ? ' and the enquirer was told in writing.' : '.'));
        App::redirect('/leads');
    }
}
