<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Auth;
use DR\Core\Db;

final class NotificationController extends Controller
{
    public function index(array $p, ?array $u): void
    {
        $pg = $this->paginate('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [(int) $u['id']], 30);
        $rows = Db::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $pg['per'] . ' OFFSET ' . $pg['offset'], [(int) $u['id']]);
        $this->view('shared/notifications', ['title' => 'Notifications', 'rows' => $rows, 'pg' => $pg], Auth::isStaff($u) ? 'app' : 'portal');
    }

    public function readAll(array $p, ?array $u): void
    {
        Db::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [now(), (int) $u['id']]);
        App::back('/notifications');
    }

    public function open(array $p, ?array $u): void
    {
        $n = Db::one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$this->id($p), (int) $u['id']]);
        if (!$n) {
            App::redirect('/notifications');
        }
        if (!$n['read_at']) {
            Db::update('notifications', ['read_at' => now()], 'id = :id', ['id' => (int) $n['id']]);
        }
        $link = (string) $n['link'];
        // Client links for staff and staff links for clients are translated to the right workspace.
        if (!Auth::isStaff($u) && preg_match('#^/cases/(\d+)$#', $link, $m)) {
            $link = '/client/cases/' . $m[1];
        }
        App::redirect(preg_match('#^/[A-Za-z0-9/_\-?=&]*$#', $link) ? $link : '/');
    }
}
