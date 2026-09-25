<?php
declare(strict_types=1);

namespace DR\Service;

use DR\Core\App;
use DR\Core\Db;
use DR\Core\Mailer;
use DR\Core\Settings;

/**
 * In-app notifications plus content-free email notices.
 * Titles never include client content: only references and event types.
 */
final class Notify
{
    public static function user(int $userId, string $kind, string $title, string $link, bool $email = true): void
    {
        $u = Db::one("SELECT id, email, status FROM users WHERE id = ?", [$userId]);
        if (!$u || $u['status'] !== 'active') {
            return;
        }
        // Throttle email: one per kind + link per fifteen minutes while earlier ones are unread.
        $recent = (int) Db::value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND kind = ? AND link = ? AND read_at IS NULL AND created_at > ?',
            [$userId, $kind, $link, date('Y-m-d H:i:s', time() - 900)]
        );
        Db::insert('notifications', [
            'user_id' => $userId, 'kind' => mb_substr($kind, 0, 40), 'title' => mb_substr($title, 0, 190),
            'link' => mb_substr($link, 0, 255), 'created_at' => now(), 'read_at' => null,
        ]);
        if ($email && $recent === 0) {
            Mailer::notice($u['email'], $title, 'There is an update waiting for you in ' . Settings::get('portal_name') . '. Sign in to view it.',
                App::absoluteUrl($link), 'Sign in to view');
        }
    }

    /** Everyone working a case: lead, assigned staff, and admins for emergencies. */
    public static function caseTeam(array $case, string $kind, string $title, ?int $exceptUserId = null, bool $email = true): void
    {
        $ids = [];
        if ($case['lead_user_id']) {
            $ids[] = (int) $case['lead_user_id'];
        }
        foreach (Db::all('SELECT user_id FROM case_staff WHERE case_id = ?', [(int) $case['id']]) as $r) {
            $ids[] = (int) $r['user_id'];
        }
        if (!$ids || $case['priority'] === 'emergency') {
            foreach (Db::all("SELECT id FROM users WHERE role IN ('master','admin') AND status = 'active'") as $r) {
                $ids[] = (int) $r['id'];
            }
        }
        $link = in_array($case['status'], ['lead', 'declined'], true) ? '/leads/' . $case['id'] : '/cases/' . $case['id'];
        foreach (array_unique($ids) as $id) {
            if ($id !== $exceptUserId) {
                self::user($id, $kind, $title, $link, $email);
            }
        }
    }

    /** All active portal users (client users and advisers) of a client. */
    public static function clientUsers(int $clientId, string $kind, string $title, string $link, ?int $exceptUserId = null): void
    {
        $rows = Db::all(
            "SELECT id FROM users WHERE status = 'active' AND ((role = 'client' AND client_id = ?) OR (role = 'adviser' AND id IN (SELECT user_id FROM client_advisers WHERE client_id = ?)))",
            [$clientId, $clientId]
        );
        foreach ($rows as $r) {
            if ((int) $r['id'] !== $exceptUserId) {
                self::user((int) $r['id'], $kind, $title, $link);
            }
        }
    }

    /** New website lead: admins, masters, case leads, and the configured inbox list. */
    public static function newLead(array $case): void
    {
        $emergency = $case['priority'] === 'emergency';
        $title = ($emergency ? 'EMERGENCY lead ' : 'New lead ') . $case['ref'];
        foreach (Db::all("SELECT id FROM users WHERE role IN ('master','admin','lead') AND status = 'active'") as $r) {
            self::user((int) $r['id'], $emergency ? 'lead_emergency' : 'lead_new', $title, '/leads/' . $case['id']);
        }
        foreach (preg_split('/[\s,;]+/', Settings::get('notify_emails')) ?: [] as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                Mailer::notice($addr, $title, 'A new ' . ($emergency ? 'priority (deadline)' : 'website') . ' case request has arrived. Details are held encrypted in the portal.',
                    App::absoluteUrl('/leads/' . $case['id']), 'Review the lead');
            }
        }
    }
}
