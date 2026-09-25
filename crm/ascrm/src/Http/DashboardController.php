<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\Auth;
use DR\Core\Db;

final class DashboardController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        [$scope, $sp] = Access::caseScope($u);
        $uid = (int) $u['id'];
        $count = static fn(string $where, array $extra = []): int => (int) Db::value("SELECT COUNT(*) FROM cases c WHERE $scope AND $where", $sp + $extra);

        $kpi = [
            'leads' => $count("c.status = 'lead'"),
            'emergency' => $count("c.priority = 'emergency' AND c.status NOT IN ('closed','declined')"),
            'active' => $count("c.status IN ('qualified','nda','active','monitoring')"),
            'tasks' => (int) Db::value("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status = 'open'", [$uid]),
            'overdue_tasks' => (int) Db::value("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status = 'open' AND due_on < ?", [$uid, today()]),
            'deadlines' => (int) Db::value(
                "SELECT COUNT(*) FROM deadlines d LEFT JOIN cases c ON c.id = d.case_id
                 WHERE d.status = 'upcoming' AND d.due_at <= :lim AND (d.case_id IS NULL AND d.created_by = :me OR d.case_id IS NOT NULL AND $scope)",
                $sp + ['lim' => date('Y-m-d H:i:s', time() + 7 * 86400), 'me' => $uid]
            ),
        ];
        if (Auth::atLeast($u, 'admin')) {
            $kpi['outstanding'] = (int) Db::value("SELECT COALESCE(SUM(total - paid), 0) FROM invoices WHERE status IN ('sent','partial')");
            $kpi['overdue_invoices'] = (int) Db::value("SELECT COUNT(*) FROM invoices WHERE status IN ('sent','partial') AND due_on < ?", [today()]);
        }

        $leads = Db::all(
            "SELECT c.* FROM cases c WHERE $scope AND c.status = 'lead'
             ORDER BY CASE c.priority WHEN 'emergency' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, c.created_at ASC LIMIT 6",
            $sp
        );
        $awaiting = Db::all(
            "SELECT c.id, c.ref, c.title, c.priority, m.created_at AS msg_at, cl.display_name
             FROM cases c JOIN clients cl ON cl.id = c.client_id
             JOIN messages m ON m.id = (SELECT MAX(m2.id) FROM messages m2 WHERE m2.case_id = c.id AND m2.internal = 0)
             JOIN users su ON su.id = m.sender_id
             WHERE $scope AND c.status NOT IN ('closed','declined') AND su.role IN ('client','adviser')
             ORDER BY m.created_at ASC LIMIT 8",
            $sp
        );
        $tasks = Db::all(
            "SELECT t.*, c.ref FROM tasks t LEFT JOIN cases c ON c.id = t.case_id
             WHERE t.assignee_id = ? AND t.status = 'open' ORDER BY CASE WHEN t.due_on IS NULL THEN 1 ELSE 0 END, t.due_on, t.id LIMIT 8",
            [$uid]
        );
        $deadlines = Db::all(
            "SELECT d.*, c.ref FROM deadlines d LEFT JOIN cases c ON c.id = d.case_id
             WHERE d.status = 'upcoming' AND d.due_at <= :lim AND (d.case_id IS NULL AND d.created_by = :me OR d.case_id IS NOT NULL AND $scope)
             ORDER BY d.due_at LIMIT 8",
            $sp + ['lim' => date('Y-m-d H:i:s', time() + 14 * 86400), 'me' => $uid]
        );
        $pipeline = [];
        foreach (Db::all("SELECT c.status, COUNT(*) AS n FROM cases c WHERE $scope GROUP BY c.status", $sp) as $r) {
            $pipeline[$r['status']] = (int) $r['n'];
        }
        $stages = [];
        foreach (Db::all("SELECT c.stage, COUNT(*) AS n FROM cases c WHERE $scope AND c.status IN ('active','monitoring','nda','qualified') GROUP BY c.stage", $sp) as $r) {
            $stages[$r['stage']] = (int) $r['n'];
        }
        $removals = Db::one(
            "SELECT SUM(CASE WHEN t.status IN ('approved','suppressed') THEN 1 ELSE 0 END) AS won,
                    SUM(CASE WHEN t.status IN ('submitted','acknowledged','appealed') THEN 1 ELSE 0 END) AS pending,
                    COUNT(*) AS total
             FROM targets t JOIN cases c ON c.id = t.case_id WHERE $scope",
            $sp
        ) ?? [];
        $recent = Db::all(
            "SELECT c.id, c.ref, c.title, c.status, c.priority, c.last_activity_at, cl.display_name FROM cases c LEFT JOIN clients cl ON cl.id = c.client_id
             WHERE $scope AND c.status NOT IN ('lead','declined') ORDER BY c.last_activity_at DESC LIMIT 8",
            $sp
        );
        $this->view('staff/dashboard', compact('kpi', 'leads', 'awaiting', 'tasks', 'deadlines', 'pipeline', 'stages', 'removals', 'recent') + ['title' => 'Dashboard'], 'app');
    }
}
