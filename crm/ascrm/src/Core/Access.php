<?php
declare(strict_types=1);

namespace DR\Core;

/** Row-level access rules. Every controller must pass through these before reading data. */
final class Access
{
    public static function caseVisible(array $u, array $case): bool
    {
        $role = $u['role'];
        if ($role === 'master' || $role === 'admin') {
            return true;
        }
        if ($role === 'lead' || $role === 'staff') {
            if ((int) ($case['lead_user_id'] ?? 0) === (int) $u['id']) {
                return true;
            }
            if ($role === 'lead' && $case['status'] === 'lead' && empty($case['lead_user_id'])) {
                return true;
            }
            return (int) Db::value('SELECT COUNT(*) FROM case_staff WHERE case_id = ? AND user_id = ?', [(int) $case['id'], (int) $u['id']]) > 0;
        }
        if (in_array($case['status'], ['lead', 'declined'], true) || empty($case['client_id'])) {
            return false;
        }
        return self::clientVisible($u, (int) $case['client_id']);
    }

    public static function clientVisible(array $u, int $clientId): bool
    {
        switch ($u['role']) {
            case 'master':
            case 'admin':
                return true;
            case 'client':
                return (int) $u['client_id'] === $clientId;
            case 'adviser':
                return (int) Db::value('SELECT COUNT(*) FROM client_advisers WHERE client_id = ? AND user_id = ?', [$clientId, (int) $u['id']]) > 0;
            default:
                return (int) Db::value(
                    'SELECT COUNT(*) FROM cases c LEFT JOIN case_staff s ON s.case_id = c.id AND s.user_id = ?
                     WHERE c.client_id = ? AND (c.lead_user_id = ? OR s.user_id IS NOT NULL)',
                    [(int) $u['id'], $clientId, (int) $u['id']]
                ) > 0;
        }
    }

    public static function documentVisible(array $u, array $doc): bool
    {
        if (!self::clientVisible($u, (int) $doc['client_id'])) {
            return false;
        }
        if ($doc['case_id']) {
            $case = Db::one('SELECT * FROM cases WHERE id = ?', [(int) $doc['case_id']]);
            if (!$case || !self::caseVisible($u, $case)) {
                return false;
            }
        }
        $staff = Auth::isStaff($u);
        if (!$staff) {
            return $doc['visibility'] === 'shared' || in_array($doc['uploaded_role'], ['client', 'adviser'], true);
        }
        if ($doc['visibility'] === 'confidential') {
            if (in_array($u['role'], ['master', 'admin'], true)) {
                return true;
            }
            if ($doc['case_id']) {
                return (int) Db::value('SELECT lead_user_id FROM cases WHERE id = ?', [(int) $doc['case_id']]) === (int) $u['id'];
            }
            return false;
        }
        return true;
    }

    /** SQL fragment + params restricting a cases query (alias c) to what the user may see. */
    public static function caseScope(array $u): array
    {
        return match ($u['role']) {
            'master', 'admin' => ['1=1', []],
            'lead' => ["(c.lead_user_id = :scope_uid OR (c.status = 'lead' AND c.lead_user_id IS NULL) OR EXISTS (SELECT 1 FROM case_staff s WHERE s.case_id = c.id AND s.user_id = :scope_uid2))",
                ['scope_uid' => (int) $u['id'], 'scope_uid2' => (int) $u['id']]],
            'staff' => ['(c.lead_user_id = :scope_uid OR EXISTS (SELECT 1 FROM case_staff s WHERE s.case_id = c.id AND s.user_id = :scope_uid2))',
                ['scope_uid' => (int) $u['id'], 'scope_uid2' => (int) $u['id']]],
            'client' => ["c.client_id = :scope_cid AND c.status NOT IN ('lead','declined')", ['scope_cid' => (int) $u['client_id']]],
            'adviser' => ["c.status NOT IN ('lead','declined') AND c.client_id IN (SELECT client_id FROM client_advisers WHERE user_id = :scope_uid)", ['scope_uid' => (int) $u['id']]],
            default => ['1=0', []],
        };
    }

    public static function loadCase(array $u, int $id): array
    {
        $case = Db::one('SELECT * FROM cases WHERE id = ?', [$id]);
        if (!$case || !self::caseVisible($u, $case)) {
            if ($case) {
                Audit::log('access_denied', 'case', $id, [], $u);
            }
            throw new HttpError('Case not found.', 404);
        }
        return $case;
    }

    public static function loadClient(array $u, int $id): array
    {
        $c = Db::one('SELECT * FROM clients WHERE id = ? AND erased_at IS NULL', [$id]);
        if (!$c || !self::clientVisible($u, $id)) {
            if ($c) {
                Audit::log('access_denied', 'client', $id, [], $u);
            }
            throw new HttpError('Client not found.', 404);
        }
        return $c;
    }

    /** Staff who may be assigned work. */
    public static function staffList(): array
    {
        return Db::all("SELECT id, name, role FROM users WHERE role IN ('master','admin','lead','staff') AND status = 'active' ORDER BY name");
    }
}
