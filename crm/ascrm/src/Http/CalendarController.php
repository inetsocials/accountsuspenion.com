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

/** Month calendar of deadlines, due tasks and invoice due dates, plus deadline management. */
final class CalendarController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        $m = Request::str('m', 7);
        $start = preg_match('/^\d{4}-\d{2}$/', $m) && checkdate((int) substr($m, 5, 2), 1, (int) substr($m, 0, 4)) ? $m . '-01' : date('Y-m-01');
        $first = new \DateTimeImmutable($start);
        $gridStart = $first->modify('-' . (((int) $first->format('N')) - 1) . ' days');
        $last = $first->modify('last day of this month');
        $gridEnd = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
        $from = $gridStart->format('Y-m-d');
        $to = $gridEnd->format('Y-m-d');

        [$scope, $sp] = Access::caseScope($u);
        $events = [];
        $deadlines = Db::all(
            "SELECT d.*, c.ref FROM deadlines d LEFT JOIN cases c ON c.id = d.case_id
             WHERE d.due_at >= :f AND d.due_at < :t AND (d.case_id IS NULL AND d.created_by = :me OR d.case_id IS NOT NULL AND $scope)
             ORDER BY d.due_at",
            $sp + ['f' => $from . ' 00:00:00', 't' => $gridEnd->modify('+1 day')->format('Y-m-d') . ' 00:00:00', 'me' => (int) $u['id']]
        );
        foreach ($deadlines as $d) {
            $events[substr($d['due_at'], 0, 10)][] = ['type' => 'deadline', 'tone' => $d['status'] === 'upcoming' ? ($d['due_at'] < now() ? 'danger' : 'warn') : 'neutral',
                'label' => substr($d['due_at'], 11, 5) . ' ' . $d['title'], 'ref' => $d['ref'], 'id' => (int) $d['id'], 'case_id' => $d['case_id'], 'status' => $d['status']];
        }
        $taskSql = Auth::atLeast($u, 'admin') ? '1=1' : 't.assignee_id = ' . (int) $u['id'];
        foreach (Db::all("SELECT t.*, c.ref FROM tasks t LEFT JOIN cases c ON c.id = t.case_id WHERE $taskSql AND t.status = 'open' AND t.due_on BETWEEN ? AND ?", [$from, $to]) as $t) {
            $events[$t['due_on']][] = ['type' => 'task', 'tone' => 'info', 'label' => $t['title'], 'ref' => $t['ref'], 'id' => (int) $t['id'], 'case_id' => $t['case_id'], 'status' => 'open'];
        }
        if (Auth::atLeast($u, 'admin')) {
            foreach (Db::all("SELECT i.id, i.number, i.due_on, i.total, i.paid, i.currency FROM invoices i WHERE i.status IN ('sent','partial') AND i.due_on BETWEEN ? AND ?", [$from, $to]) as $i) {
                $events[$i['due_on']][] = ['type' => 'invoice', 'tone' => 'ok', 'label' => $i['number'] . ' due ' . money((int) $i['total'] - (int) $i['paid'], $i['currency']), 'ref' => null, 'id' => (int) $i['id'], 'case_id' => null, 'status' => 'due'];
            }
        }
        $agenda = Db::all(
            "SELECT d.*, c.ref FROM deadlines d LEFT JOIN cases c ON c.id = d.case_id
             WHERE d.status = 'upcoming' AND d.due_at <= :lim AND (d.case_id IS NULL AND d.created_by = :me OR d.case_id IS NOT NULL AND $scope) ORDER BY d.due_at LIMIT 30",
            $sp + ['lim' => date('Y-m-d H:i:s', time() + 14 * 86400), 'me' => (int) $u['id']]
        );
        foreach ($agenda as &$a) {
            $a['notes'] = (string) Crypto::fromSystem('general', $a['notes_enc']);
        }
        unset($a);
        $cases = Db::all("SELECT c.id, c.ref, c.title FROM cases c WHERE $scope AND c.status NOT IN ('closed','declined') ORDER BY c.ref", $sp);
        $this->view('staff/calendar', [
            'title' => 'Calendar', 'first' => $first, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'events' => $events, 'agenda' => $agenda, 'cases' => $cases,
            'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'),
        ], 'app');
    }

    public function save(array $p, ?array $u): void
    {
        $title = Request::str('title', 190);
        $due = Request::datetime('due_at');
        if ($title === '' || !$due) {
            $this->fail('Enter a title and a due date and time.', '/calendar');
        }
        $caseId = Request::int('case_id') ?: null;
        if ($caseId) {
            Access::loadCase($u, $caseId);
        }
        $data = [
            'title' => $title, 'kind' => Request::oneOf('kind', Labels::DEADLINE_KINDS, 'other'), 'due_at' => $due,
            'remind_days' => max(0, min(30, Request::int('remind_days', 2))), 'case_id' => $caseId,
            'notes_enc' => Crypto::forSystem('general', Request::text('notes', 4000)), 'client_visible' => !empty($_POST['client_visible']) ? 1 : 0,
        ];
        $id = Request::int('deadline_id');
        if ($id) {
            $d = $this->load($id, $u);
            Db::update('deadlines', $data + ['reminded_at' => $d['due_at'] !== $due ? null : $d['reminded_at']], 'id = :id', ['id' => $id]);
        } else {
            $id = Db::insert('deadlines', $data + ['status' => 'upcoming', 'reminded_at' => null, 'created_by' => (int) $u['id'], 'created_at' => now()]);
        }
        Audit::log('deadline_saved', $caseId ? 'case' : 'deadline', $caseId ?: $id, ['deadline_id' => $id], $u);
        $this->flash('ok', 'Deadline saved. Reminders go to the case team ' . $data['remind_days'] . ' day(s) before.');
        App::back('/calendar');
    }

    private function load(int $id, array $u): array
    {
        $d = Db::one('SELECT * FROM deadlines WHERE id = ?', [$id]);
        if (!$d) {
            throw new HttpError('Deadline not found.', 404);
        }
        if ($d['case_id']) {
            Access::loadCase($u, (int) $d['case_id']);
        } elseif ((int) $d['created_by'] !== (int) $u['id'] && !Auth::atLeast($u, 'admin')) {
            throw new HttpError('Deadline not found.', 404);
        }
        return $d;
    }

    public function status(array $p, ?array $u): void
    {
        $d = $this->load($this->id($p), $u);
        $st = Request::oneOf('status', Labels::DEADLINE_STATUS, 'completed');
        Db::update('deadlines', ['status' => $st], 'id = :id', ['id' => (int) $d['id']]);
        Audit::log('deadline_' . $st, $d['case_id'] ? 'case' : 'deadline', $d['case_id'] ?: $d['id'], [], $u);
        App::back('/calendar');
    }

    public function delete(array $p, ?array $u): void
    {
        $d = $this->load($this->id($p), $u);
        Db::run('DELETE FROM deadlines WHERE id = ?', [(int) $d['id']]);
        Audit::log('deadline_deleted', $d['case_id'] ? 'case' : 'deadline', $d['case_id'] ?: $d['id'], [], $u);
        $this->flash('ok', 'Deadline deleted.');
        App::back('/calendar');
    }
}
