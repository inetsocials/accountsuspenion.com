<?php
declare(strict_types=1);

namespace DR\Core;

/** Key-value settings. Secret values are sealed with the general subkey. */
final class Settings
{
    private const SECRET = ['smtp_pass'];
    private static ?array $cache = null;

    public const DEFAULTS = [
        'org_name' => 'AccountSuspension.com',
        'legal_name' => '',
        'portal_name' => 'AS Case Vault',
        'website_url' => 'https://accountsuspension.com',
        'consult_response_window' => 'within one business day',
        'priority_response_window' => 'the same business day',
        'notify_emails' => '',
        'mail_driver' => 'mail',
        'mail_from' => '',
        'mail_from_name' => 'AccountSuspension.com',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_secure' => 'tls',
        'upload_max_mb' => '100',
        'retention_declined_days' => '365',
        'retention_audit_days' => '1095',
        'session_idle_minutes' => '30',
        'maintenance' => '0',
        'invoice_terms_days' => '14',
        'invoice_footer' => 'Payment by bank transfer or ACH. Please quote the invoice number as your reference.',
        'bank_details' => '',
        'vat_number' => '',
        'currency' => 'USD',
        'nda_text' => "ENGAGEMENT AND CONFIDENTIALITY AGREEMENT\n\nThis agreement is between AccountSuspension.com (\"we\") and the client named below (\"you\").\n\n1. Independent service. We are an independent case preparation service. We are not affiliated with, endorsed by or acting for any platform, and we are not a law firm. Nothing we provide is legal advice.\n2. No guaranteed outcome. Platforms make their own decisions. We do not guarantee reinstatement, release of funds or any other platform outcome.\n3. Your account, your submission. Appeals and documents are submitted through your own account and only with your approval. You will not share passwords or one-time codes with us.\n4. Accuracy. You will provide genuine, accurate and complete information and documents. We will not create, alter or source documents, help open new or secondary accounts to get around enforcement, or misrepresent facts to any platform. We may end the engagement if asked to.\n5. Confidentiality. Each party will keep the other's confidential information secret, use it only for this engagement, and share it only with people who need it for that purpose and are bound by equivalent duties. We will not disclose that you are a client without your written consent.\n6. Exceptions. These duties do not apply to information that is public through no fault of the receiving party, or that must be disclosed by law, in which case the receiving party will give notice where lawful.\n7. Return and retention. On request, each party will return or securely destroy the other's confidential information, subject to legal retention duties. These duties continue for five years after the engagement ends, and indefinitely for personal data.\n8. Fees and governing law. Scope and fees are set out in your written engagement letter, which also states the governing law.",
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Db::all('SELECT k, v FROM settings') as $r) {
                    self::$cache[$r['k']] = $r['v'];
                }
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $k, ?string $default = null): string
    {
        $all = self::all();
        $v = $all[$k] ?? ($default ?? (self::DEFAULTS[$k] ?? ''));
        if (in_array($k, self::SECRET, true) && is_string($v) && str_starts_with($v, 'v1.')) {
            return (string) Crypto::fromSystem('general', $v);
        }
        return (string) $v;
    }

    public static function set(string $k, string $v): void
    {
        if (in_array($k, self::SECRET, true) && $v !== '') {
            $v = (string) Crypto::forSystem('general', $v);
        }
        $exists = Db::value('SELECT COUNT(*) FROM settings WHERE k = ?', [$k]);
        if ((int) $exists > 0) {
            Db::update('settings', ['v' => $v], 'k = :k', ['k' => $k]);
        } else {
            Db::insert('settings', ['k' => $k, 'v' => $v]);
        }
        self::$cache = null;
    }

    public static function int(string $k): int
    {
        return (int) self::get($k);
    }
}
