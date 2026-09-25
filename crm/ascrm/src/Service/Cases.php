<?php
declare(strict_types=1);

namespace DR\Service;

use DR\Core\Audit;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Numbers;

final class Cases
{
    /**
     * Create a lead from the public website forms. The whole submission is sealed with the
     * intake subkey; the email is also stored as a keyed hash so status checks can match it.
     */
    public static function fromIntake(array $in): array
    {
        // Website fields: platform, issue, history, urgency (deadline | revenue | standard).
        $urgencyKey = array_key_exists($in['urgency'] ?? '', Labels::URGENCY_IN) ? $in['urgency'] : 'standard';
        $emergency = $urgencyKey === 'deadline';
        $sourceMap = ['readiness' => 'readiness', 'decoder' => 'decoder', 'platform' => 'platform', 'service' => 'service', 'guide' => 'guide'];
        $source = $emergency ? 'emergency' : ($sourceMap[$in['source'] ?? ''] ?? 'website');
        $urgency = ['deadline' => 5, 'revenue' => 4, 'standard' => 2][$urgencyKey];
        $priority = $emergency ? 'emergency' : ($urgencyKey === 'revenue' ? 'high' : 'medium');
        $issue = array_key_exists($in['issue'] ?? '', Labels::ISSUES) ? $in['issue'] : 'other';
        $platform = array_key_exists($in['platform'] ?? '', Labels::PLATFORMS) ? $in['platform'] : 'other';
        $platformName = $platform === 'other' && !empty($in['platform_other']) ? mb_substr((string) $in['platform_other'], 0, 60) : Labels::PLATFORMS[$platform];
        $title = ($emergency ? 'Priority: ' : '') . $platformName . ': ' . Labels::ISSUES[$issue];
        $ref = Numbers::caseRef();
        $id = Db::insert('cases', [
            'ref' => $ref, 'client_id' => null, 'title' => mb_substr($title, 0, 190), 'status' => 'lead', 'stage' => 'diagnose', 'priority' => $priority,
            'source' => $source,
            'subject_type' => array_key_exists($in['subject_type'] ?? '', Labels::SUBJECT) ? $in['subject_type'] : null,
            'issue' => $issue,
            'jurisdiction' => array_key_exists($in['jurisdiction'] ?? '', Labels::JURIS) ? $in['jurisdiction'] : null,
            'platform' => $platform,
            'appeal_history' => array_key_exists($in['history'] ?? '', Labels::HISTORY) ? $in['history'] : null,
            'urgency' => $urgency, 'services' => null, 'lead_user_id' => null, 'nda_required' => 1,
            'nda_signed_at' => null, 'intake_enc' => Crypto::forSystem('intake', json_encode($in, JSON_UNESCAPED_UNICODE)),
            'intake_email_hash' => Crypto::lookupHash((string) $in['email']), 'decline_reason' => null, 'screening' => null,
            'opened_at' => null, 'closed_at' => null, 'created_at' => now(), 'updated_at' => now(), 'last_activity_at' => now(),
        ]);
        $case = Db::one('SELECT * FROM cases WHERE id = ?', [$id]);
        Audit::log('lead_received', 'case', $id, ['source' => $source, 'priority' => $priority], ['id' => null, 'role' => 'website']);
        Notify::newLead($case);
        return $case;
    }

    public static function intake(array $case): array
    {
        if (!$case['intake_enc']) {
            return [];
        }
        return json_decode((string) Crypto::fromSystem('intake', $case['intake_enc']), true) ?: [];
    }

    public static function touch(int $caseId): void
    {
        Db::update('cases', ['last_activity_at' => now(), 'updated_at' => now()], 'id = :id', ['id' => $caseId]);
    }

    /** Post a message on a case (optionally internal staff note). Returns message id. */
    public static function postMessage(array $case, array $user, string $body, bool $internal): int
    {
        if (!$case['client_id']) {
            throw new HttpError('Messages are available once the lead is accepted.', 422);
        }
        $body = trim($body);
        if ($body === '') {
            throw new HttpError('Write a message first.', 422);
        }
        $mid = Db::insert('messages', [
            'case_id' => (int) $case['id'], 'sender_id' => (int) $user['id'],
            'body_enc' => Crypto::forClient((int) $case['client_id'], $body), 'internal' => $internal ? 1 : 0, 'created_at' => now(),
        ]);
        self::markRead((int) $case['id'], (int) $user['id']);
        self::touch((int) $case['id']);
        Audit::log($internal ? 'note_added' : 'message_sent', 'case', (int) $case['id'], ['message_id' => $mid], $user);
        return $mid;
    }

    public static function markRead(int $caseId, int $userId): void
    {
        $last = (int) Db::value('SELECT COALESCE(MAX(id), 0) FROM messages WHERE case_id = ?', [$caseId]);
        $exists = (int) Db::value('SELECT COUNT(*) FROM case_reads WHERE case_id = ? AND user_id = ?', [$caseId, $userId]);
        if ($exists) {
            Db::update('case_reads', ['last_message_id' => $last, 'read_at' => now()], 'case_id = :c AND user_id = :u', ['c' => $caseId, 'u' => $userId]);
        } else {
            Db::insert('case_reads', ['case_id' => $caseId, 'user_id' => $userId, 'last_message_id' => $last, 'read_at' => now()]);
        }
    }

    /** Messages with decrypted bodies, sender names and attachments. */
    public static function thread(array $case, bool $includeInternal): array
    {
        $rows = Db::all(
            'SELECT m.*, u.name AS sender_name, u.role AS sender_role FROM messages m LEFT JOIN users u ON u.id = m.sender_id
             WHERE m.case_id = ?' . ($includeInternal ? '' : ' AND m.internal = 0') . ' ORDER BY m.id ASC',
            [(int) $case['id']]
        );
        $atts = [];
        foreach (Db::all('SELECT * FROM documents WHERE case_id = ? AND message_id IS NOT NULL AND deleted_at IS NULL', [(int) $case['id']]) as $d) {
            $atts[(int) $d['message_id']][] = $d + ['name' => (string) Crypto::fromClient((int) $d['client_id'], $d['name_enc'])];
        }
        foreach ($rows as &$r) {
            $r['body'] = (string) Crypto::fromClient((int) $case['client_id'], $r['body_enc']);
            $r['attachments'] = $atts[(int) $r['id']] ?? [];
        }
        return $rows;
    }

    public static function unreadFor(int $caseId, array $user, bool $staff): int
    {
        $last = (int) (Db::value('SELECT last_message_id FROM case_reads WHERE case_id = ? AND user_id = ?', [$caseId, (int) $user['id']]) ?? 0);
        return (int) Db::value(
            'SELECT COUNT(*) FROM messages WHERE case_id = ? AND id > ? AND sender_id <> ?' . ($staff ? '' : ' AND internal = 0'),
            [$caseId, $last, (int) $user['id']]
        );
    }

    /** Case activity from the audit trail (no decrypted content is stored there). */
    public static function activity(int $caseId, int $limit = 60): array
    {
        return Db::all(
            "SELECT a.*, u.name AS user_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE (a.entity = 'case' AND a.entity_id = ?) OR (a.entity = 'document' AND a.meta LIKE ?)
             ORDER BY a.id DESC LIMIT " . (int) $limit,
            [(string) $caseId, '%"case_id":' . $caseId . ',%']
        );
    }

    public static function describe(array $a): string
    {
        $m = json_decode((string) $a['meta'], true) ?: [];
        return match ($a['action']) {
            'lead_received' => 'Lead received via ' . strtolower(Labels::SOURCES[$m['source'] ?? ''] ?? 'website'),
            'lead_claimed' => 'Lead claimed',
            'lead_screened' => 'Screening checklist updated',
            'lead_accepted' => 'Lead accepted and client file opened',
            'lead_declined' => 'Lead declined',
            'case_created' => 'Case opened',
            'case_updated' => 'Case details updated' . (isset($m['status']) ? ' (status: ' . Labels::get((string) $m['status']) . ')' : ''),
            'team_updated' => 'Case team changed',
            'message_sent' => 'Message sent',
            'note_added' => 'Internal note added',
            'nda_signed' => 'Engagement agreement signed',
            'target_saved' => 'Appeal tracker updated',
            'target_deleted' => 'Appeal entry deleted',
            'funds_saved' => 'Held funds updated' . (isset($m['status']) ? ' (' . strtolower(Labels::FUNDS_STATUS[$m['status']] ?? (string) $m['status']) . ')' : ''),
            'funds_deleted' => 'Held funds entry deleted',
            'document_uploaded' => 'Document uploaded' . (!empty($m['version']) && $m['version'] > 1 ? ' (version ' . (int) $m['version'] . ')' : ''),
            'document_downloaded' => 'Document downloaded',
            'document_deleted' => 'Document deleted',
            'document_updated' => 'Document sharing changed',
            'access_denied' => 'Access denied',
            'invoice_sent' => 'Invoice issued',
            default => ucfirst(str_replace('_', ' ', (string) $a['action'])),
        };
    }
}
