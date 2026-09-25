<?php
declare(strict_types=1);

namespace DR\Core;

/** Append-only audit trail. Metadata must never contain decrypted client content. */
final class Audit
{
    public static function log(string $action, ?string $entity = null, int|string|null $entityId = null, array $meta = [], ?array $user = null): void
    {
        $u = $user ?? Auth::userOrNull();
        Db::insert('audit_log', [
            'at' => now(),
            'user_id' => isset($u['id']) ? (int) $u['id'] : null,
            'role' => $u['role'] ?? null,
            'action' => mb_substr($action, 0, 60),
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'ip' => PHP_SAPI === 'cli' ? 'cli' : Request::ip(),
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
