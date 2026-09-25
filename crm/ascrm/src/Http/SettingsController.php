<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\App;
use DR\Core\Audit;
use DR\Core\Mailer;
use DR\Core\Request;
use DR\Core\Settings;

final class SettingsController extends Controller
{
    /** key => [label, type, group, help] */
    public const FIELDS = [
        'org_name' => ['Trading name', 'text', 'Organization', ''],
        'legal_name' => ['Legal entity name', 'text', 'Organization', 'Shown on invoices and the client agreement.'],
        'portal_name' => ['Portal name', 'text', 'Organization', ''],
        'website_url' => ['Website address', 'url', 'Organization', ''],
        'consult_response_window' => ['Standard response window', 'text', 'Organization', 'Shown to enquirers, for example "within one business day".'],
        'priority_response_window' => ['Priority response window', 'text', 'Organization', ''],
        'notify_emails' => ['New lead alert addresses', 'text', 'Notifications', 'Comma separated. Alerts contain no case details.'],
        'mail_driver' => ['Mail transport', 'select:mail=PHP mail (Hostinger default),smtp=Authenticated SMTP', 'Notifications', ''],
        'mail_from' => ['From address', 'email', 'Notifications', 'Use a mailbox on your own domain so SPF and DMARC pass.'],
        'mail_from_name' => ['From name', 'text', 'Notifications', ''],
        'smtp_host' => ['SMTP host', 'text', 'Notifications', 'Hostinger: smtp.hostinger.com'],
        'smtp_port' => ['SMTP port', 'number', 'Notifications', '587 for STARTTLS, 465 for SSL'],
        'smtp_secure' => ['SMTP security', 'select:tls=STARTTLS,ssl=SSL/TLS', 'Notifications', ''],
        'smtp_user' => ['SMTP user', 'text', 'Notifications', ''],
        'smtp_pass' => ['SMTP password', 'password', 'Notifications', 'Stored encrypted. Leave blank to keep the current one.'],
        'upload_max_mb' => ['Maximum upload size (MB)', 'number', 'Security and retention', 'Hard ceiling 500 MB.'],
        'session_idle_minutes' => ['Sign out after idle (minutes)', 'number', 'Security and retention', 'Between 5 and 240.'],
        'retention_declined_days' => ['Keep declined lead details for (days)', 'number', 'Security and retention', 'Minimum 30. Intake contents are erased after this.'],
        'retention_audit_days' => ['Keep audit log for (days)', 'number', 'Security and retention', 'Minimum 365.'],
        'currency' => ['Default currency', 'select:USD=USD,CAD=CAD,GBP=GBP,EUR=EUR', 'Invoicing', ''],
        'invoice_terms_days' => ['Payment terms (days)', 'number', 'Invoicing', ''],
        'vat_number' => ['Tax ID (EIN), shown on invoices', 'text', 'Invoicing', ''],
        'bank_details' => ['Bank details for invoices', 'textarea', 'Invoicing', ''],
        'invoice_footer' => ['Invoice footer', 'textarea', 'Invoicing', ''],
        'nda_text' => ['Client engagement agreement', 'textarea', 'Client agreements', 'Clients sign this before messaging or sharing documents. Have it reviewed by your attorney. Changing it affects new signatures only; each signature stores the exact text signed.'],
    ];

    public function index(array $p, ?array $u): void
    {
        $this->view('admin/settings', ['title' => 'Settings', 'fields' => self::FIELDS], 'app');
    }

    public function save(array $p, ?array $u): void
    {
        $changed = [];
        foreach (self::FIELDS as $k => [$label, $type]) {
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            $v = $type === 'textarea' ? Request::text($k, 20000) : Request::str($k, 500);
            if ($type === 'password') {
                if ($v === '') {
                    continue;
                }
            } elseif ($type === 'number') {
                $v = (string) max(0, (int) $v);
            } elseif ($type === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $this->fail($label . ' is not a valid email address.', '/admin/settings');
            } elseif (str_starts_with($type, 'select:')) {
                $allowed = array_map(fn($o) => explode('=', $o, 2)[0], explode(',', substr($type, 7)));
                if (!in_array($v, $allowed, true)) {
                    continue;
                }
            }
            $v = match ($k) {
                'upload_max_mb' => (string) max(1, min(500, (int) $v)),
                'session_idle_minutes' => (string) max(5, min(240, (int) $v)),
                'retention_declined_days' => (string) max(30, (int) $v),
                'retention_audit_days' => (string) max(365, (int) $v),
                'invoice_terms_days' => (string) max(0, min(120, (int) $v)),
                default => $v,
            };
            if (Settings::get($k) !== $v) {
                Settings::set($k, $v);
                $changed[] = $k;
            }
        }
        Audit::log('settings_changed', 'system', null, ['keys' => $changed], $u);
        $this->flash('ok', $changed ? 'Settings saved.' : 'No changes.');
        App::redirect('/admin/settings');
    }

    public function testMail(array $p, ?array $u): void
    {
        $ok = Mailer::notice($u['email'], 'Test message from ' . Settings::get('portal_name'), 'Email delivery is working. You can ignore this message.');
        $this->flash($ok ? 'ok' : 'error', $ok ? 'Test email sent to ' . $u['email'] . '. Check the inbox and spam folder.' : 'The test email could not be sent. Check the mail settings and the error log.');
        App::redirect('/admin/settings');
    }
}
