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
use DR\Service\Notify;

final class TaskController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        $view = Request::oneOf('view', ['mine' => 1, 'created' => 1, 'all' => 1], 'mine');
        if ($view === 'all' && !Auth::atLeast($u, 'admin')) {
            $view = 'mine';
        }
        $status = Request::oneOf('status', Labels::TASK_STATUS + ['any' => 1], 'open');
        $where = [];
        $params = [];
        if ($view === 'mine') {
            $where[] = 't.assignee_id = :me';
            $params['me'] = (int) $u['id'];
        } elseif ($view === 'created') {
            $where[] = 't.created_by = :me';
            $params['me'] = (int) $u['id'];
        }
        if ($status !== 'any') {
            $where[] = 't.status = :st';
            $params['st'] = $status;
        }
        $w = $where ? implode(' AND ', $where) : '1=1';
        $pg = $this->paginate("SELECT COUNT(*) FROM tasks t WHERE $w", $params, 40);
        $rows = Db::all(
            "SELECT t.*, c.ref, us.name AS assignee, cb.name AS creator FROM tasks t LEFT JOIN cases c ON c.id = t.case_id
             LEFT JOIN users us ON us.id = t.assignee_id LEFT JOIN users cb ON cb.id = t.created_by
             WHERE $w ORDER BY CASE t.status WHEN 'open' THEN 0 ELSE 1 END, CASE WHEN t.due_on IS NULL THEN 1 ELSE 0 END, t.due_on, t.id DESC
             LIMIT {$pg['per']} OFFSET {$pg['offset']}",
            $params
        );
        foreach ($rows as &$r) {
            $r['description'] = (string) Crypto::fromSystem('general', $r['description_enc']);
        }
        unset($r);
        [$scope, $sp] = Access::caseScope($u);
        $cases = Db::all("SELECT c.id, c.ref, c.title FROM cases c WHERE $scope AND c.status NOT IN ('closed','declined') ORDER BY c.ref", $sp);
        $this->view('staff/tasks', ['title' => 'Tasks', 'rows' => $rows, 'pg' => $pg, 'view' => $view, 'status' => $status, 'cases' => $cases, 'staff' => Access::staffList()], 'app');
    }

    public function save(array $p, ?array $u): void
    {
        $title = Request::str('title', 190);
        if ($title === '') {
            $this->fail('Enter a task title.', '/tasks');
        }
        $caseId = Request::int('case_id') ?: null;
        if ($caseId) {
            Access::loadCase($u, $caseId);
        }
        $assignee = Request::int('assignee_id') ?: (int) $u['id'];
        if (!in_array($assignee, array_map('intval', array_column(Access::staffList(), 'id')), true)) {
            $assignee = (int) $u['id'];
        }
        $data = [
            'title' => $title, 'description_enc' => Crypto::forSystem('general', Request::text('description', 8000)),
            'priority' => Request::oneOf('priority', Labels::TASK_PRIORITY, 'medium'), 'due_on' => Request::date('due_on'),
            'case_id' => $caseId, 'assignee_id' => $assignee,
        ];
        $id = Request::int('task_id');
        if ($id) {
            $task = $this->loadTask($id, $u);
            Db::update('tasks', $data, 'id = :id', ['id' => (int) $task['id']]);
            $changedAssignee = (int) $task['assignee_id'] !== $assignee;
        } else {
            $id = Db::insert('tasks', $data + ['status' => 'open', 'created_by' => (int) $u['id'], 'created_at' => now(), 'completed_at' => null]);
            $changedAssignee = true;
        }
        if ($changedAssignee && $assignee !== (int) $u['id']) {
            Notify::user($assignee, 'task', 'A task was assigned to you', '/tasks');
        }
        Audit::log('task_saved', $caseId ? 'case' : 'task', $caseId ?: $id, ['task_id' => $id], $u);
        $this->flash('ok', 'Task saved.');
        App::back('/tasks');
    }

    private function loadTask(int $id, array $u): array
    {
        $t = Db::one('SELECT * FROM tasks WHERE id = ?', [$id]);
        if (!$t) {
            throw new HttpError('Task not found.', 404);
        }
        $mine = (int) $t['assignee_id'] === (int) $u['id'] || (int) $t['created_by'] === (int) $u['id'];
        if (!$mine && !Auth::atLeast($u, 'admin')) {
            if (!$t['case_id'] || !Access::caseVisible($u, Db::one('SELECT * FROM cases WHERE id = ?', [(int) $t['case_id']]) ?? [])) {
                throw new HttpError('Task not found.', 404);
            }
        }
        return $t;
    }

    public function status(array $p, ?array $u): void
    {
        $t = $this->loadTask($this->id($p), $u);
        $st = Request::oneOf('status', Labels::TASK_STATUS, 'done');
        Db::update('tasks', ['status' => $st, 'completed_at' => $st === 'done' ? now() : null], 'id = :id', ['id' => (int) $t['id']]);
        if ($st === 'done' && $t['created_by'] && (int) $t['created_by'] !== (int) $u['id']) {
            Notify::user((int) $t['created_by'], 'task_done', 'A task you created was completed', '/tasks?view=created&status=done', false);
        }
        Audit::log('task_' . $st, $t['case_id'] ? 'case' : 'task', $t['case_id'] ?: $t['id'], ['task_id' => (int) $t['id']], $u);
        App::back('/tasks');
    }

    public function delete(array $p, ?array $u): void
    {
        $t = $this->loadTask($this->id($p), $u);
        if ((int) $t['created_by'] !== (int) $u['id'] && !Auth::atLeast($u, 'lead')) {
            throw new HttpError('Only the creator or a case lead can delete a task.', 403);
        }
        Db::run('DELETE FROM tasks WHERE id = ?', [(int) $t['id']]);
        Audit::log('task_deleted', $t['case_id'] ? 'case' : 'task', $t['case_id'] ?: $t['id'], ['task_id' => (int) $t['id']], $u);
        $this->flash('ok', 'Task deleted.');
        App::back('/tasks');
    }
}
