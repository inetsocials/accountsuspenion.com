<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Auth;
use DR\Core\Db;
use DR\Core\Labels;
use DR\Core\Request;

/** Command palette data (Ctrl+K). Returns only rows the user may see. */
final class SearchController extends Controller
{
    public function query(array $p, ?array $u): void
    {
        $q = Request::str('q', 80);
        $staff = Auth::isStaff($u);
        $pages = $staff ? [
            ['Dashboard', '/'], ['Leads inbox', '/leads'], ['Cases', '/cases'], ['Clients', '/clients'], ['Tasks', '/tasks'],
            ['Calendar and deadlines', '/calendar'], ['Invoices', '/invoices'], ['Notifications', '/notifications'], ['My account', '/account'],
        ] : [['Overview', '/client'], ['Documents', '/client/documents'], ['Invoices', '/client/invoices'], ['Notifications', '/notifications'], ['My account', '/account']];
        if ($staff && Auth::atLeast($u, 'lead')) {
            array_push($pages, ['New case', '/cases/new'], ['New client', '/clients/new'], ['New invoice', '/invoices/new'], ['Reports', '/reports'],
                ['Blog posts', '/cms/posts'], ['New blog post', '/cms/posts/new'], ['Testimonials', '/cms/testimonials']);
        }
        if (Auth::atLeast($u, 'admin')) {
            array_push($pages, ['Users and roles', '/admin/users'], ['Service catalog', '/admin/catalog'], ['Audit log', '/admin/audit'], ['Website redirects', '/cms/redirects']);
        }
        if ($u['role'] === 'master') {
            array_push($pages, ['Settings', '/admin/settings'], ['Security, keys and backups', '/admin/security']);
        }
        $out = [];
        $needle = mb_strtolower($q);
        foreach ($pages as [$label, $path]) {
            if ($needle === '' || str_contains(mb_strtolower($label), $needle)) {
                $out[] = ['type' => 'Page', 'label' => $label, 'url' => url($path)];
            }
        }
        if (mb_strlen($q) >= 2) {
            [$scope, $params] = Access::caseScope($u);
            $like = '%' . $q . '%';
            $rows = Db::all(
                "SELECT c.id, c.ref, c.title, c.status FROM cases c WHERE $scope AND (c.ref LIKE :q1 OR c.title LIKE :q2) ORDER BY c.last_activity_at DESC LIMIT 8",
                $params + ['q1' => $like, 'q2' => $like]
            );
            foreach ($rows as $r) {
                $path = !$staff ? '/client/cases/' . $r['id'] : (in_array($r['status'], ['lead', 'declined'], true) ? '/leads/' . $r['id'] : '/cases/' . $r['id']);
                $out[] = ['type' => 'Case', 'label' => $r['ref'] . '  ' . $r['title'], 'meta' => Labels::get($r['status']), 'url' => url($path)];
            }
            if ($staff) {
                $cl = Auth::atLeast($u, 'admin')
                    ? Db::all('SELECT id, number, display_name FROM clients WHERE erased_at IS NULL AND (number LIKE ? OR display_name LIKE ?) ORDER BY display_name LIMIT 6', [$like, $like])
                    : Db::all(
                        'SELECT DISTINCT cl.id, cl.number, cl.display_name FROM clients cl JOIN cases c ON c.client_id = cl.id
                         LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = ?
                         WHERE cl.erased_at IS NULL AND (c.lead_user_id = ? OR s.user_id IS NOT NULL) AND (cl.number LIKE ? OR cl.display_name LIKE ?) LIMIT 6',
                        [(int) $u['id'], (int) $u['id'], $like, $like]
                    );
                foreach ($cl as $r) {
                    $out[] = ['type' => 'Client', 'label' => $r['number'] . '  ' . $r['display_name'], 'url' => url('/clients/' . $r['id'])];
                }
            }
        }
        App::json(['ok' => true, 'results' => array_slice($out, 0, 20)]);
    }
}
