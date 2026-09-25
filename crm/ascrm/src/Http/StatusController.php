<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Auth;
use DR\Core\Crypto;
use DR\Core\Db;
use DR\Core\HttpError;
use DR\Core\Mailer;
use DR\Core\RateLimit;
use DR\Core\Request;
use DR\Core\Settings;

/**
 * Public case status check. The reference plus the email used at intake triggers a one-time
 * link to that email address. The response never reveals whether a match was found.
 */
final class StatusController extends Controller
{
    public function form(array $p, ?array $u): void
    {
        $this->view('auth/status', ['title' => 'Check a case', 'sent' => false, 'ref' => strtoupper(Request::str('ref', 12))], 'auth');
    }

    public function request(array $p, ?array $u): void
    {
        $ref = strtoupper(Request::str('ref', 12));
        $email = mb_strtolower(Request::str('email', 190));
        if (preg_match('/^AS-[A-F0-9]{8}$/', $ref) && filter_var($email, FILTER_VALIDATE_EMAIL)
            && RateLimit::attempt('status_ip:' . Request::ip(), 10, 3600) && RateLimit::attempt('status_ref:' . $ref, 3, 3600)) {
            $case = Db::one('SELECT id, ref FROM cases WHERE ref = ? AND intake_email_hash = ?', [$ref, Crypto::lookupHash($email)]);
            if ($case) {
                $token = Auth::issueToken(null, 'status', 30, ['case_id' => (int) $case['id']]);
                Mailer::notice($email, 'Your case status link', 'Use the link below to see the current status of your case. It works for 30 minutes.',
                    App::absoluteUrl('/status/' . $token), 'View case status');
                Audit::log('status_link_sent', 'case', (int) $case['id'], [], ['id' => null, 'role' => 'public']);
            }
        }
        $this->view('auth/status', ['title' => 'Check a case', 'sent' => true, 'ref' => $ref], 'auth');
    }

    public function show(array $p, ?array $u): void
    {
        $t = Auth::findToken($p['token'] ?? '', 'status');
        $meta = $t ? (json_decode((string) $t['meta'], true) ?: []) : [];
        $case = $meta ? Db::one('SELECT * FROM cases WHERE id = ?', [(int) ($meta['case_id'] ?? 0)]) : null;
        if (!$case) {
            throw new HttpError('This status link has expired. Request a new one.', 404);
        }
        [$label, $text] = match ($case['status']) {
            'lead' => ['Received', 'Your request is with a case lead for review. We reply in writing ' . Settings::get($case['priority'] === 'emergency' ? 'priority_response_window' : 'consult_response_window') . '.'],
            'qualified', 'nda' => ['Accepted', 'Your case has been accepted. Check your email for the invitation to your secure client portal.'],
            'active', 'monitoring' => ['In progress', 'Your case is in progress. Sign in to your client portal for messages, documents, the appeal tracker and held funds.'],
            'declined' => ['Closed', 'This request was reviewed and closed. The reply was sent to you in writing.'],
            default => ['Closed', 'This case is closed. Sign in to your client portal to see its history.'],
        };
        $this->view('auth/status_result', ['title' => 'Case status', 'case' => $case, 'label' => $label, 'text' => $text], 'auth');
    }
}
