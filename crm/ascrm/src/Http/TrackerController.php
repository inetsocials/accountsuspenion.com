<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Access;
use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Labels;
use DR\Core\Request;
use DR\Service\Cases;
use DR\Service\Notify;

/**
 * Appeal tracker (one row per submission to a platform) and held-funds tracker.
 * Identifiers, outcomes, fund references and notes are sealed with the client key.
 */
final class TrackerController extends Controller
{
    public static function targets(array $case, bool $clientView): array
    {
        $rows = Db::all('SELECT * FROM targets WHERE case_id = ?' . ($clientView ? ' AND client_visible = 1' : '') . ' ORDER BY updated_at DESC, id DESC', [(int) $case['id']]);
        foreach ($rows as &$r) {
            $r['url'] = (string) Crypto::fromClient((int) $case['client_id'], $r['url_enc']);
            $r['outcome'] = (string) Crypto::fromClient((int) $case['client_id'], $r['outcome_enc']);
        }
        return $rows;
    }

    public static function funds(array $case, bool $clientView): array
    {
        $rows = Db::all('SELECT * FROM funds WHERE case_id = ?' . ($clientView ? ' AND client_visible = 1' : '') . ' ORDER BY updated_at DESC, id DESC', [(int) $case['id']]);
        foreach ($rows as &$r) {
            $r['reference'] = (string) Crypto::fromClient((int) $case['client_id'], $r['reference_enc']);
            $r['notes'] = (string) Crypto::fromClient((int) $case['client_id'], $r['notes_enc']);
        }
        return $rows;
    }

    /** Totals per currency: [currency => [held, released, outstanding]]. */
    public static function fundTotals(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $c = (string) $r['currency'];
            $out[$c] ??= ['held' => 0, 'released' => 0, 'outstanding' => 0];
            $out[$c]['held'] += (int) $r['amount'];
            $out[$c]['released'] += min((int) $r['released'], (int) $r['amount']);
            if (!in_array($r['status'], ['released', 'forfeited'], true)) {
                $out[$c]['outstanding'] += max(0, (int) $r['amount'] - (int) $r['released']);
            }
        }
        return $out;
    }

    private function openCase(array $p, array $u): array
    {
        $case = Access::loadCase($u, $this->id($p));
        if (!$case['client_id']) {
            throw new HttpError('Accept the lead before tracking work.', 422);
        }
        return $case;
    }

    public function saveTarget(array $p, ?array $u): void
    {
        $case = $this->openCase($p, $u);
        $back = '/cases/' . $case['id'];
        $ident = Request::str('url', 500);
        if ($ident === '') {
            $this->fail('Enter the account, listing, channel or case identifier this submission concerns.', $back);
        }
        $defaultPlatform = Labels::PLATFORMS[$case['platform'] ?? ''] ?? '';
        $data = [
            'url_enc' => Crypto::forClient((int) $case['client_id'], $ident),
            'platform' => mb_substr(Request::str('platform', 60) ?: $defaultPlatform ?: 'Platform', 0, 60),
            'route' => Request::oneOf('route', Labels::ROUTES, 'other'),
            'strength' => Request::oneOf('strength', Labels::STRENGTH, 'case-dependent'),
            'status' => Request::oneOf('status', Labels::TARGET_STATUS, 'drafted'),
            'platform_ref' => Request::str('platform_ref', 120) ?: null,
            'submitted_on' => Request::date('submitted_on'), 'decided_on' => Request::date('decided_on'),
            'outcome_enc' => Crypto::forClient((int) $case['client_id'], Request::text('outcome', 4000)),
            'client_visible' => !empty($_POST['client_visible']) ? 1 : 0, 'updated_at' => now(),
        ];
        if ($data['status'] !== 'drafted' && !$data['submitted_on']) {
            $data['submitted_on'] = today();
        }
        if (in_array($data['status'], ['approved', 'refused', 'partial', 'suppressed'], true) && !$data['decided_on']) {
            $data['decided_on'] = today();
        }
        $tid = Request::int('target_id');
        $prev = $tid ? Db::one('SELECT * FROM targets WHERE id = ? AND case_id = ?', [$tid, (int) $case['id']]) : null;
        if ($tid && !$prev) {
            throw new HttpError('Submission not found.', 404);
        }
        if ($prev) {
            Db::update('targets', $data, 'id = :id', ['id' => $tid]);
        } else {
            $tid = Db::insert('targets', $data + ['case_id' => (int) $case['id'], 'created_by' => (int) $u['id'], 'created_at' => now()]);
        }
        Cases::touch((int) $case['id']);
        Audit::log('target_saved', 'case', (int) $case['id'], ['target_id' => $tid, 'status' => $data['status']], $u);
        if ($data['client_visible'] && (!$prev || $prev['status'] !== $data['status'])) {
            Notify::clientUsers((int) $case['client_id'], 'tracker', 'Appeal tracker updated on ' . $case['ref'], '/client/cases/' . $case['id'] . '?tab=tracker');
        }
        $this->flash('ok', 'Appeal tracker updated.');
        App::redirect($back, ['tab' => 'appeals']);
    }

    public function deleteTarget(array $p, ?array $u): void
    {
        $case = $this->openCase($p, $u);
        Db::run('DELETE FROM targets WHERE id = ? AND case_id = ?', [$this->id($p, 'tid'), (int) $case['id']]);
        Audit::log('target_deleted', 'case', (int) $case['id'], [], $u);
        $this->flash('ok', 'Submission record deleted.');
        App::redirect('/cases/' . $case['id'], ['tab' => 'appeals']);
    }

    public function saveFunds(array $p, ?array $u): void
    {
        $case = $this->openCase($p, $u);
        $back = '/cases/' . $case['id'];
        try {
            $amount = to_minor(Request::str('amount', 20));
            $released = to_minor(Request::str('released', 20) ?: '0');
        } catch (\Throwable) {
            $this->fail('Enter amounts as numbers, for example 1250.00', $back);
        }
        if ($amount <= 0 || $released < 0) {
            $this->fail('The held amount must be above zero and the released amount cannot be negative.', $back);
        }
        if ($released > $amount) {
            $this->fail('The released amount cannot be more than the amount held.', $back);
        }
        $status = Request::oneOf('status', Labels::FUNDS_STATUS, 'held');
        if ($released === $amount && $status !== 'forfeited') {
            $status = 'released';
        } elseif ($released > 0 && in_array($status, ['held', 'requested'], true)) {
            $status = 'partial';
        }
        $platform = Request::str('platform', 60);
        if ($platform === '') {
            $this->fail('Enter the platform holding the funds.', $back);
        }
        $cid = (int) $case['client_id'];
        $data = [
            'platform' => $platform, 'amount' => $amount, 'released' => $released,
            'currency' => Request::oneOf('currency', Labels::CURRENCIES, 'USD'), 'status' => $status,
            'held_since' => Request::date('held_since'), 'expected_on' => Request::date('expected_on'),
            'reference_enc' => Crypto::forClient($cid, Request::str('reference', 120)),
            'notes_enc' => Crypto::forClient($cid, Request::text('notes', 4000)),
            'client_visible' => !empty($_POST['client_visible']) ? 1 : 0, 'updated_at' => now(),
        ];
        $fid = Request::int('fund_id');
        $prev = $fid ? Db::one('SELECT * FROM funds WHERE id = ? AND case_id = ?', [$fid, (int) $case['id']]) : null;
        if ($fid && !$prev) {
            throw new HttpError('Held balance not found.', 404);
        }
        if ($prev) {
            Db::update('funds', $data, 'id = :id', ['id' => $fid]);
        } else {
            $fid = Db::insert('funds', $data + ['case_id' => (int) $case['id'], 'created_by' => (int) $u['id'], 'created_at' => now()]);
        }
        Cases::touch((int) $case['id']);
        Audit::log('funds_saved', 'case', (int) $case['id'], ['fund_id' => $fid, 'status' => $status], $u);
        if ($data['client_visible'] && (!$prev || $prev['status'] !== $status || (int) $prev['released'] !== $released)) {
            Notify::clientUsers($cid, 'funds', 'Held funds updated on ' . $case['ref'], '/client/cases/' . $case['id'] . '?tab=tracker');
        }
        $this->flash('ok', 'Held funds updated.');
        App::redirect($back, ['tab' => 'funds']);
    }

    public function deleteFunds(array $p, ?array $u): void
    {
        $case = $this->openCase($p, $u);
        Db::run('DELETE FROM funds WHERE id = ? AND case_id = ?', [$this->id($p, 'fid'), (int) $case['id']]);
        Audit::log('funds_deleted', 'case', (int) $case['id'], [], $u);
        $this->flash('ok', 'Held balance record deleted.');
        App::redirect('/cases/' . $case['id'], ['tab' => 'funds']);
    }

    public function exportTargets(array $p, ?array $u): void
    {
        $case = $this->openCase($p, $u);
        $rows = self::targets($case, false);
        Audit::log('targets_exported', 'case', (int) $case['id'], ['rows' => count($rows)], $u);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $case['ref'] . '-appeal-tracker.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        $safe = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
        fputcsv($out, ['Account or item', 'Platform', 'Route', 'Strength', 'Status', 'Platform reference', 'Submitted', 'Decided', 'Outcome', 'Client visible'], ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($out, array_map($safe, [$r['url'], $r['platform'], Labels::ROUTES[$r['route']] ?? $r['route'], Labels::STRENGTH[$r['strength']] ?? $r['strength'],
                Labels::TARGET_STATUS[$r['status']] ?? $r['status'], $r['platform_ref'], fdate($r['submitted_on']), fdate($r['decided_on']), $r['outcome'], $r['client_visible'] ? 'Yes' : 'No']), ',', '"', '\\');
        }
        fclose($out);
    }
}
