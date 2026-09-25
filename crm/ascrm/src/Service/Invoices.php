<?php
declare(strict_types=1);

namespace DR\Service;

use DR\Core\Db;

final class Invoices
{
    /** Line total in minor units from quantity (x100) and unit price. */
    public static function lineNet(int $qtyX100, int $unit): int
    {
        return intdiv($qtyX100 * $unit + 50, 100);
    }

    /**
     * Recalculate totals. Discount is applied to the net before tax, pro rata across lines,
     * so tax is charged on the discounted amount (unconditional discounts reduce the taxable base).
     */
    public static function recalc(int $invoiceId): array
    {
        $inv = Db::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$inv) {
            return [];
        }
        $items = Db::all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort, id', [$invoiceId]);
        $subtotal = 0;
        foreach ($items as $it) {
            $net = self::lineNet((int) $it['qty_x100'], (int) $it['unit_price']);
            if ($net !== (int) $it['line_total']) {
                Db::update('invoice_items', ['line_total' => $net], 'id = :id', ['id' => (int) $it['id']]);
            }
            $subtotal += $net;
        }
        $discount = max(0, min((int) $inv['discount'], $subtotal));
        $vat = 0;
        foreach ($items as $it) {
            $net = self::lineNet((int) $it['qty_x100'], (int) $it['unit_price']);
            $share = $subtotal > 0 ? $net - intdiv($net * $discount + intdiv($subtotal, 2), $subtotal) : 0;
            $vat += intdiv($share * (int) $it['vat_bp'] + 5000, 10000);
        }
        $total = $subtotal - $discount + $vat;
        $paid = (int) Db::value('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?', [$invoiceId]);
        $status = $inv['status'];
        if (!in_array($status, ['draft', 'cancelled'], true)) {
            $status = $paid >= $total && $total > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'sent');
        }
        Db::update('invoices', [
            'subtotal' => $subtotal, 'discount' => $discount, 'vat' => $vat, 'total' => $total, 'paid' => $paid,
            'status' => $status, 'updated_at' => now(),
        ], 'id = :id', ['id' => $invoiceId]);
        return Db::one('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) ?? [];
    }

    public static function isOverdue(array $inv): bool
    {
        return in_array($inv['status'], ['sent', 'partial'], true) && $inv['due_on'] < today();
    }
}
