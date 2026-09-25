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
use DR\Core\Numbers;
use DR\Core\Request;
use DR\Service\Cases;
use DR\Service\Notify;

final class CaseController extends Controller
{
    public const OPEN = ['qualified', 'nda', 'active', 'monitoring'];

    public function index(array $p, ?array $u): void
    {
        [$scope, $params] = Access::caseScope($u);
        $where = [$scope, "c.status NOT IN ('lead','declined')"];
        $status = Request::oneOf('status', ['open' => 1, 'closed' => 1, 'all' => 1] + Labels::CASE_STATUS, 'open');
        if ($status === 'open') {
            $where[] = "c.status IN ('qualified','nda','active','monitoring')";
        } elseif ($status !== 'all') {
            $where[] = 'c.status = :st';
            $params['st'] = $status;
        }
        foreach (['stage' => Labels::STAGES, 'priority' => Labels::PRIORITY] as $f => $set) {
            if ($v = Request::oneOf($f, $set)) {
                $where[] = "c.$f = :$f";
                $params[$f] = $v;
            }
        }
        if ($lead = Request::int('lead')) {
            $where[] = 'c.lead_user_id = :lead';
            $params['lead'] = $lead;
        }
        if (($mine = Request::str('mine', 1)) === '1') {
            $where[] = '(c.lead_user_id = :me1 OR EXISTS (SELECT 1 FROM case_staff s2 WHERE s2.case_id = c.id AND s2.user_id = :me2))';
            $params += ['me1' => (int) $u['id'], 'me2' => (int) $u['id']];
        }
        $q = Request::str('q', 80);
        if ($q !== '') {
            $where[] = '(c.ref LIKE :q1 OR c.title LIKE :q2 OR cl.display_name LIKE :q3 OR cl.number LIKE :q4)';
            $params += ['q1' => "%$q%", 'q2' => "%$q%", 'q3' => "%$q%", 'q4' => "%$q%"];
        }
        $sort = Request::oneOf('sort', ['activity' => 1, 'priority' => 1, 'opened' => 1, 'ref' => 1], 'activity');
        $order = match ($sort) {
            'priority' => "CASE c.priority WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, c.last_activity_at DESC",
            'opened' => 'c.opened_at DESC',
            'ref' => 'c.ref',
            default => 'c.last_activity_at DESC',
        };
        $w = implode(' AND ', $where);
        $pg = $this->paginate("SELECT COUNT(*) FROM cases c LEFT JOIN clients cl ON cl.id = c.client_id WHERE $w", $params, 30);
        $rows = Db::all(
            "SELECT c.*, cl.display_name, cl.number AS client_number, us.name AS lead_name,
               (SELECT COUNT(*) FROM targets t WHERE t.case_id = c.id) AS targets,
               (SELECT COUNT(*) FROM targets t WHERE t.case_id = c.id AND t.status IN ('approved','suppressed')) AS won
             FROM cases c LEFT JOIN clients cl ON cl.id = c.client_id LEFT JOIN users us ON us.id = c.lead_user_id
             WHERE $w ORDER BY $order LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        foreach ($rows as &$r) {
            $r['unread'] = Cases::unreadFor((int) $r['id'], $u, true);
        }
        unset($r);
        $this->view('staff/cases', [
            'title' => 'Cases', 'rows' => $rows, 'pg' => $pg, 'staff' => Access::staffList(),
            'filters' => ['status' => $status, 'stage' => Request::str('stage', 20), 'priority' => Request::str('priority', 20), 'lead' => $lead ?: '', 'q' => $q, 'sort' => $sort, 'mine' => $mine],
        ], 'app');
    }

    public function createForm(array $p, ?array $u): void
    {
        $clients = Auth::atLeast($u, 'admin')
            ? Db::all("SELECT id, number, display_name FROM clients WHERE erased_at IS NULL AND status = 'active' ORDER BY display_name")
            : Db::all("SELECT DISTINCT cl.id, cl.number, cl.display_name FROM clients cl JOIN cases c ON c.client_id = cl.id LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = ?
                       WHERE cl.erased_at IS NULL AND cl.status = 'active' AND (c.lead_user_id = ? OR s.user_id IS NOT NULL) ORDER BY cl.display_name", [(int) $u['id'], (int) $u['id']]);
        $this->view('staff/case_new', ['title' => 'New case', 'clients' => $clients, 'staff' => Access::staffList(), 'clientId' => Request::int('client')], 'app');
    }

    public function create(array $p, ?array $u): void
    {
        $client = Access::loadClient($u, Request::int('client_id'));
        $title = Request::str('title', 190);
        if ($title === '') {
            $this->fail('Enter a case title.', '/cases/new');
        }
        $lead = Request::int('lead_user_id') ?: (int) $u['id'];
        $nda = !empty($_POST['nda_required']) ? 1 : 0;
        $id = Db::insert('cases', [
            'ref' => Numbers::caseRef(), 'client_id' => (int) $client['id'], 'title' => $title, 'status' => $nda ? 'nda' : 'active',
            'stage' => Request::oneOf('stage', Labels::STAGES, 'diagnose'), 'priority' => Request::oneOf('priority', Labels::PRIORITY, 'medium'),
            'source' => 'manual', 'subject_type' => Request::oneOf('subject_type', Labels::SUBJECT) ?: null,
            'issue' => Request::oneOf('issue', Labels::ISSUES) ?: null, 'jurisdiction' => Request::oneOf('jurisdiction', Labels::JURIS) ?: null,
            'platform' => Request::oneOf('platform', Labels::PLATFORMS) ?: 'other', 'appeal_history' => Request::oneOf('appeal_history', Labels::HISTORY) ?: null,
            'urgency' => 1, 'services' => Request::text('services', 2000) ?: null, 'lead_user_id' => $lead, 'nda_required' => $nda,
            'nda_signed_at' => null, 'intake_enc' => null, 'intake_email_hash' => null, 'decline_reason' => null, 'screening' => null,
            'opened_at' => now(), 'closed_at' => null, 'created_at' => now(), 'updated_at' => now(), 'last_activity_at' => now(),
        ]);
        Audit::log('case_created', 'case', $id, ['client_id' => (int) $client['id']], $u);
        if ($lead !== (int) $u['id']) {
            Notify::user($lead, 'case_assigned', 'You are case lead for a new case', '/cases/' . $id);
        }
        Notify::clientUsers((int) $client['id'], 'case_opened', 'A new case has been opened for you', '/client/cases/' . $id);
        $this->flash('ok', 'Case opened.');
        App::redirect('/cases/' . $id);
    }

    public function show(array $p, ?array $u): void
    {
        $case = Access::loadCase($u, $this->id($p));
        if (in_array($case['status'], ['lead', 'declined'], true)) {
            App::redirect('/leads/' . $case['id']);
        }
        $cid = (int) $case['client_id'];
        $tab = Request::oneOf('tab', ['overview' => 1, 'messages' => 1, 'appeals' => 1, 'funds' => 1, 'documents' => 1, 'work' => 1, 'invoices' => 1, 'activity' => 1], 'overview');
        $client = Db::one('SELECT * FROM clients WHERE id = ?', [$cid]);
        $data = [
            'title' => $case['ref'] . ' ' . $case['title'], 'case' => $case, 'client' => $client, 'tab' => $tab,
            'lead' => $case['lead_user_id'] ? Db::one('SELECT id, name, role FROM users WHERE id = ?', [(int) $case['lead_user_id']]) : null,
            'team' => Db::all('SELECT u.id, u.name, u.role FROM case_staff s JOIN users u ON u.id = s.user_id WHERE s.case_id = ? ORDER BY u.name', [(int) $case['id']]),
            'staff' => Access::staffList(), 'unread' => Cases::unreadFor((int) $case['id'], $u, true),
            'counts' => [
                'targets' => (int) Db::value('SELECT COUNT(*) FROM targets WHERE case_id = ?', [(int) $case['id']]),
                'won' => (int) Db::value("SELECT COUNT(*) FROM targets WHERE case_id = ? AND status IN ('approved','suppressed')", [(int) $case['id']]),
                'pending' => (int) Db::value("SELECT COUNT(*) FROM targets WHERE case_id = ? AND status IN ('submitted','acknowledged','appealed')", [(int) $case['id']]),
                'docs' => (int) Db::value('SELECT COUNT(*) FROM documents WHERE case_id = ? AND deleted_at IS NULL', [(int) $case['id']]),
                'tasks' => (int) Db::value("SELECT COUNT(*) FROM tasks WHERE case_id = ? AND status = 'open'", [(int) $case['id']]),
                'messages' => (int) Db::value('SELECT COUNT(*) FROM messages WHERE case_id = ?', [(int) $case['id']]),
                'funds' => (int) Db::value('SELECT COUNT(*) FROM funds WHERE case_id = ?', [(int) $case['id']]),
            ],
            'nda' => Db::one('SELECT signed_name, signed_at, ip, text_hash FROM nda_signatures WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $case['id']]),
        ];
        switch ($tab) {
            case 'overview':
                $data['intake'] = Cases::intake($case);
                $data['activity'] = Cases::activity((int) $case['id'], 8);
                $data['deadlines'] = Db::all("SELECT * FROM deadlines WHERE case_id = ? AND status = 'upcoming' ORDER BY due_at LIMIT 5", [(int) $case['id']]);
                break;
            case 'messages':
                $data['thread'] = Cases::thread($case, true);
                Cases::markRead((int) $case['id'], (int) $u['id']);
                break;
            case 'appeals':
                $data['targets'] = TrackerController::targets($case, false);
                break;
            case 'funds':
                $data['funds'] = TrackerController::funds($case, false);
                break;
            case 'documents':
                $data['docs'] = DocumentController::listFor($u, $cid, (int) $case['id']);
                $data['folders'] = DocumentController::foldersFor($cid);
                break;
            case 'work':
                $data['tasks'] = Db::all('SELECT t.*, us.name AS assignee FROM tasks t LEFT JOIN users us ON us.id = t.assignee_id WHERE t.case_id = ? ORDER BY t.status, t.due_on', [(int) $case['id']]);
                foreach ($data['tasks'] as &$t) {
                    $t['description'] = (string) Crypto::fromSystem('general', $t['description_enc']);
                }
                unset($t);
                $data['deadlines'] = Db::all('SELECT * FROM deadlines WHERE case_id = ? ORDER BY status DESC, due_at', [(int) $case['id']]);
                foreach ($data['deadlines'] as &$d) {
                    $d['notes'] = (string) Crypto::fromSystem('general', $d['notes_enc']);
                }
                unset($d);
                break;
            case 'invoices':
                $data['invoices'] = Db::all('SELECT * FROM invoices WHERE case_id = ? ORDER BY issued_on DESC', [(int) $case['id']]);
                break;
            case 'activity':
                $data['activity'] = Cases::activity((int) $case['id'], 200);
                break;
        }
        $this->view('staff/case', $data, 'app');
    }

    public function update(array $p, ?array $u): void
    {
        $case = Access::loadCase($u, $this->id($p));
        $isLeadish = Auth::atLeast($u, 'admin') || (int) $case['lead_user_id'] === (int) $u['id'];
        $status = Request::oneOf('status', Labels::CASE_STATUS, $case['status']);
        if (in_array($status, ['lead', 'declined'], true)) {
            $status = $case['status'];
        }
        $stage = Request::oneOf('stage', Labels::STAGES, $case['stage']);
        $upd = [
            'title' => Request::str('title', 190) ?: $case['title'], 'status' => $status, 'stage' => $stage,
            'priority' => Request::oneOf('priority', Labels::PRIORITY, $case['priority']),
            'issue' => Request::oneOf('issue', Labels::ISSUES, (string) $case['issue']) ?: null,
            'jurisdiction' => Request::oneOf('jurisdiction', Labels::JURIS, (string) $case['jurisdiction']) ?: null,
            'platform' => Request::oneOf('platform', Labels::PLATFORMS, (string) $case['platform']) ?: null,
            'services' => Request::text('services', 2000) ?: null, 'updated_at' => now(), 'last_activity_at' => now(),
        ];
        if ($isLeadish) {
            $lead = Request::int('lead_user_id');
            if ($lead && Db::value("SELECT id FROM users WHERE id = ? AND role IN ('master','admin','lead','staff') AND status = 'active'", [$lead])) {
                $upd['lead_user_id'] = $lead;
            }
            $upd['nda_required'] = !empty($_POST['nda_required']) ? 1 : 0;
            if ($upd['nda_required'] === 0 && $status === 'nda') {
                $upd['status'] = $status = 'active';
            }
        } elseif ($status !== $case['status'] && in_array($status, ['closed'], true)) {
            throw new HttpError('Only the case lead or an admin can close a case.', 403);
        }
        if ($status === 'closed' && $case['status'] !== 'closed') {
            $upd['closed_at'] = now();
        } elseif ($status !== 'closed') {
            $upd['closed_at'] = null;
        }
        Db::update('cases', $upd, 'id = :id', ['id' => (int) $case['id']]);
        $changed = array_filter(['status' => $status !== $case['status'] ? $status : null, 'stage' => $stage !== $case['stage'] ? $stage : null]);
        Audit::log('case_updated', 'case', (int) $case['id'], $changed, $u);
        if ($changed) {
            Notify::clientUsers((int) $case['client_id'], 'case_update', 'Your case ' . $case['ref'] . ' has been updated', '/client/cases/' . $case['id']);
        }
        if (isset($upd['lead_user_id']) && (int) $upd['lead_user_id'] !== (int) $case['lead_user_id'] && (int) $upd['lead_user_id'] !== (int) $u['id']) {
            Notify::user((int) $upd['lead_user_id'], 'case_assigned', 'You are now case lead for ' . $case['ref'], '/cases/' . $case['id']);
        }
        $this->flash('ok', 'Case updated.');
        App::redirect('/cases/' . $case['id']);
    }

    public function team(array $p, ?array $u): void
    {
        $case = Access::loadCase($u, $this->id($p));
        if (!Auth::atLeast($u, 'admin') && (int) $case['lead_user_id'] !== (int) $u['id']) {
            throw new HttpError('Only the case lead or an admin can change the team.', 403);
        }
        $valid = array_column(Access::staffList(), 'id');
        $ids = array_values(array_unique(array_filter(array_map('intval', Request::arr('staff')), fn($i) => in_array($i, array_map('intval', $valid), true))));
        $before = array_map('intval', array_column(Db::all('SELECT user_id FROM case_staff WHERE case_id = ?', [(int) $case['id']]), 'user_id'));
        Db::tx(function () use ($case, $ids): void {
            Db::run('DELETE FROM case_staff WHERE case_id = ?', [(int) $case['id']]);
            foreach ($ids as $id) {
                Db::insert('case_staff', ['case_id' => (int) $case['id'], 'user_id' => $id, 'created_at' => now()]);
            }
        });
        foreach (array_diff($ids, $before) as $new) {
            if ($new !== (int) $u['id']) {
                Notify::user($new, 'case_assigned', 'You were added to case ' . $case['ref'], '/cases/' . $case['id']);
            }
        }
        Audit::log('team_updated', 'case', (int) $case['id'], ['staff' => $ids], $u);
        $this->flash('ok', 'Case team updated.');
        App::redirect('/cases/' . $case['id']);
    }

    public function message(array $p, ?array $u): void
    {
        $case = Access::loadCase($u, $this->id($p));
        $internal = !empty($_POST['internal']);
        $body = Request::text('body', 20000);
        if ($body === '' && Request::wantsJson() && !empty($_POST['has_files'])) {
            $body = '(attachment)';
        }
        $mid = Cases::postMessage($case, $u, $body, $internal);
        if ($internal) {
            Notify::caseTeam($case, 'note', 'Internal note on ' . $case['ref'], (int) $u['id'], false);
        } else {
            Notify::clientUsers((int) $case['client_id'], 'message', 'New message on your case ' . $case['ref'], '/client/cases/' . $case['id'] . '?tab=messages');
            Notify::caseTeam($case, 'message_staff', 'Message sent on ' . $case['ref'], (int) $u['id'], false);
        }
        if (Request::wantsJson()) {
            App::json(['ok' => true, 'message_id' => $mid, 'case_id' => (int) $case['id']]);
        }
        App::redirect('/cases/' . $case['id'], ['tab' => 'messages']);
    }
}
