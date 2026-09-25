<?php
declare(strict_types=1);

namespace DR\Core;

/** Human-facing identifiers. */
final class Numbers
{
    public static function caseRef(): string
    {
        do {
            $ref = 'AS-' . strtoupper(bin2hex(random_bytes(4)));
        } while ((int) Db::value('SELECT COUNT(*) FROM cases WHERE ref = ?', [$ref]) > 0);
        return $ref;
    }

    public static function clientNumber(): string
    {
        $max = (string) (Db::value("SELECT MAX(number) FROM clients WHERE number LIKE 'CL-%'") ?? '');
        $n = $max !== '' ? (int) substr($max, 3) + 1 : 1;
        return 'CL-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    public static function invoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $max = (string) (Db::value('SELECT MAX(number) FROM invoices WHERE number LIKE ?', [$prefix . '%']) ?? '');
        $n = $max !== '' ? (int) substr($max, strlen($prefix)) + 1 : 1;
        return $prefix . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
