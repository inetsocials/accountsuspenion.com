<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\Labels;

final class ReportController extends Controller
{
    private function data(array $u): array
    {
        [$scope, $sp] = Access::caseScope($u);
        $group = static function (string $col) use ($scope, $sp): array {
            $out = [];
            foreach (Db::all("SELECT c.$col AS k, COUNT(*) AS n FROM cases c WHERE $scope GROUP BY c.$col ORDER BY n DESC", $sp) as $r) {
                $out[(string) $r['k']] = (int) $r['n'];
            }
            return $out;
        };
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[date('Y-m', strtotime(date('Y-m-01') . " -$i months"))] = ['leads' => 0, 'accepted' => 0, 'revenue' => 0];
        }
        $since = array_key_first($months) . '-01 00:00:00';
        foreach (Db::all("SELECT c.created_at, c.opened_at, c.source FROM cases c WHERE $scope AND c.created_at >= :since", $sp + ['since' => $since]) as $r) {
            $m = substr($r['created_at'], 0, 7);
            if (isset($months[$m]) && $r['source'] !== 'manual') {
                $months[$m]['leads']++;
            }
            $o = $r['opened_at'] ? substr($r['opened_at'], 0, 7) : null;
            if ($o && isset($months[$o])) {
                $months[$o]['accepted']++;
            }
        }
        if (Auth::atLeast($u, 'admin')) {
            foreach (Db::all('SELECT p.paid_on, p.amount FROM payments p WHERE p.paid_on >= ?', [substr($since, 0, 10)]) as $r) {
                $m = substr($r['paid_on'], 0, 7);
                if (isset($months[$m])) {
                    $months[$m]['revenue'] += (int) $r['amount'];
                }
            }
        }
        $targets = [];
        foreach (Db::all("SELECT t.route, t.status, t.submitted_on, t.decided_on FROM targets t JOIN cases c ON c.id = t.case_id WHERE $scope", $sp) as $t) {
            $r = &$targets[$t['route']];
            $r ??= ['total' => 0, 'won' => 0, 'lost' => 0, 'pending' => 0, 'days' => []];
            $r['total']++;
            if (in_array($t['status'], ['approved', 'suppressed', 'partial'], true)) {
                $r['won']++;
            } elseif ($t['status'] === 'refused') {
                $r['lost']++;
            } elseif (in_array($t['status'], ['submitted', 'acknowledged', 'appealed'], true)) {
                $r['pending']++;
            }
            if ($t['submitted_on'] && $t['decided_on']) {
                $r['days'][] = max(0, (int) round((strtotime($t['decided_on']) - strtotime($t['submitted_on'])) / 86400));
            }
            unset($r);
        }
        uasort($targets, fn($a, $b) => $b['total'] <=> $a['total']);
        $leadTime = Db::all("SELECT c.created_at, c.opened_at FROM cases c WHERE $scope AND c.opened_at IS NOT NULL AND c.source <> 'manual' AND c.created_at >= :since", $sp + ['since' => $since]);
        $hours = array_map(fn($r) => max(0, (strtotime($r['opened_at']) - strtotime($r['created_at'])) / 3600), $leadTime);
        sort($hours);
        $median = $hours ? $hours[intdiv(count($hours), 2)] : null;
        $workload = Db::all(
            "SELECT us.id, us.name, us.role,
               (SELECT COUNT(*) FROM cases c WHERE c.lead_user_id = us.id AND c.status IN ('qualified','nda','active','monitoring')) AS leading,
               (SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = us.id AND t.status = 'open') AS open_tasks,
               (SELECT COUNT(*) FROM tasks t WHERE t.assignee_id = us.id AND t.status = 'open' AND t.due_on < ?) AS overdue
             FROM users us WHERE us.role IN ('master','admin','lead','staff') AND us.status = 'active' ORDER BY leading DESC, us.name",
            [today()]
        );
        return [
            'status' => $group('status'), 'source' => $group('source'), 'issue' => $group('issue'), 'stage' => $group('stage'),
            'priority' => $group('priority'), 'juris' => $group('platform'), 'months' => $months, 'targets' => $targets,
            'medianHours' => $median, 'workload' => Auth::atLeast($u, 'admin') ? $workload : [],
        ];
    }

    public function index(array $p, ?array $u): void
    {
        $this->view('staff/reports', ['title' => 'Reports'] + $this->data($u), 'app');
    }

    public function export(array $p, ?array $u): void
    {
        $d = $this->data($u);
        Audit::log('report_exported', 'system', null, [], $u);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ascv-report-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Month', 'Leads', 'Accepted', 'Revenue received (' . \DR\Core\Settings::get('currency') . ')'], ',', '"', '\\');
        foreach ($d['months'] as $m => $r) {
            fputcsv($out, [date('m/Y', strtotime($m . '-01')), $r['leads'], $r['accepted'], number_format($r['revenue'] / 100, 2, '.', '')], ',', '"', '\\');
        }
        fputcsv($out, [], ',', '"', '\\');
        fputcsv($out, ['Route', 'Total', 'Resolved or released', 'Rejected', 'Pending', 'Success rate', 'Median days to decision'], ',', '"', '\\');
        foreach ($d['targets'] as $route => $r) {
            $decided = $r['won'] + $r['lost'];
            $days = $r['days'];
            sort($days);
            fputcsv($out, [Labels::ROUTES[$route] ?? $route, $r['total'], $r['won'], $r['lost'], $r['pending'],
                $decided ? round($r['won'] / $decided * 100) . '%' : '', $days ? $days[intdiv(count($days), 2)] : ''], ',', '"', '\\');
        }
        fclose($out);
    }
}
