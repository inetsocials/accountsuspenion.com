<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Audit;
use DR\Core\Db;
use DR\Core\Request;

final class AuditController extends Controller
{
    private function filters(): array
    {
        $where = ['1=1'];
        $params = [];
        if ($a = Request::str('action', 60)) {
            $where[] = 'a.action = :a';
            $params['a'] = $a;
        }
        if ($uid = Request::int('user')) {
            $where[] = 'a.user_id = :u';
            $params['u'] = $uid;
        }
        if ($e = Request::str('entity', 40)) {
            $where[] = 'a.entity = :e';
            $params['e'] = $e;
            if ($eid = Request::str('entity_id', 40)) {
                $where[] = 'a.entity_id = :eid';
                $params['eid'] = $eid;
            }
        }
        if ($f = Request::date('from')) {
            $where[] = 'a.at >= :f';
            $params['f'] = $f . ' 00:00:00';
        }
        if ($t = Request::date('to')) {
            $where[] = 'a.at <= :t';
            $params['t'] = $t . ' 23:59:59';
        }
        if (Request::str('security', 1) === '1') {
            $where[] = "a.action IN ('login_failed','account_locked','mfa_failed','mfa_locked','access_denied','sudo_failed','upload_rejected','mfa_reset','password_reset','key_rotated','client_erased','backup_created','backup_downloaded')";
        }
        return [implode(' AND ', $where), $params];
    }

    public function index(array $p, ?array $u): void
    {
        [$w, $params] = $this->filters();
        $pg = $this->paginate("SELECT COUNT(*) FROM audit_log a WHERE $w", $params, 50);
        $rows = Db::all("SELECT a.*, us.name AS user_name, us.email AS user_email FROM audit_log a LEFT JOIN users us ON us.id = a.user_id WHERE $w ORDER BY a.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
        $actions = array_column(Db::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
        $users = Db::all('SELECT id, name, role FROM users ORDER BY name');
        $this->view('admin/audit', ['title' => 'Audit log', 'rows' => $rows, 'pg' => $pg, 'actions' => $actions, 'users' => $users, 'q' => array_filter([
            'action' => Request::str('action', 60), 'user' => Request::int('user') ?: '', 'entity' => Request::str('entity', 40), 'entity_id' => Request::str('entity_id', 40),
            'from' => Request::str('from', 10), 'to' => Request::str('to', 10), 'security' => Request::str('security', 1),
        ])], 'app');
    }

    public function export(array $p, ?array $u): void
    {
        [$w, $params] = $this->filters();
        Audit::log('audit_exported', 'system', null, [], $u);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['When (UK)', 'User', 'Role', 'Action', 'Entity', 'Entity ID', 'IP', 'Details'], ',', '"', '\\');
        $st = Db::run("SELECT a.*, us.email AS user_email FROM audit_log a LEFT JOIN users us ON us.id = a.user_id WHERE $w ORDER BY a.id DESC LIMIT 50000", $params);
        while ($r = $st->fetch()) {
            fputcsv($out, [fdate($r['at'], true), $r['user_email'] ?? '', $r['role'] ?? '', $r['action'], $r['entity'] ?? '', $r['entity_id'] ?? '', $r['ip'] ?? '', $r['meta'] ?? ''], ',', '"', '\\');
        }
        fclose($out);
    }
}
