<?php
declare(strict_types=1);

namespace DR\Core;

/** Controlled vocabularies used by forms, filters, badges and validation. */
final class Labels
{
    public const ROLES = [
        'master' => 'Master Admin', 'admin' => 'Admin', 'lead' => 'Case Lead', 'staff' => 'Staff',
        'client' => 'Client', 'adviser' => 'Adviser',
    ];
    public const STAFF_ROLES = ['master', 'admin', 'lead', 'staff'];

    public const CASE_STATUS = [
        'lead' => 'Lead', 'qualified' => 'Qualified', 'nda' => 'Awaiting agreement', 'active' => 'Active',
        'monitoring' => 'Monitoring', 'closed' => 'Closed', 'declined' => 'Declined',
    ];
    /** Diagnose, Evidence, Appeal, Protect (the AccountSuspension.com method). */
    public const STAGES = ['diagnose' => 'Diagnose', 'evidence' => 'Evidence', 'appeal' => 'Appeal', 'protect' => 'Protect'];
    public const PRIORITY = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'emergency' => 'Priority (deadline)'];
    public const SOURCES = [
        'website' => 'Website intake', 'emergency' => 'Priority review', 'manual' => 'Added by staff',
        'readiness' => 'Readiness Score', 'decoder' => 'Notice Decoder', 'platform' => 'Platform page',
        'service' => 'Service page', 'guide' => 'Guide',
    ];
    public const SUBJECT = [
        'individual' => 'Individual', 'business' => 'Business or seller', 'creator' => 'Creator or public figure',
        'adviser' => 'Agency or adviser acting for a client',
    ];
    /** Matches the website intake form (api/contact.php). */
    public const ISSUES = [
        'suspended' => 'Suspended or deactivated', 'funds' => 'Funds or payouts held', 'limited' => 'Limited or restricted',
        'verification' => 'Verification failing', 'ip' => 'IP or authenticity complaint', 'strikes' => 'Strikes or warnings',
        'rejected' => 'Appeal already rejected', 'prevent' => 'Prevention or audit', 'other' => 'Other',
    ];
    public const HISTORY = ['none' => 'No appeal yet', 'one' => 'One appeal submitted', 'many' => 'Several appeals', 'final' => 'Told the decision is final'];
    public const URGENCY_IN = ['deadline' => 'Response deadline within 72 hours', 'revenue' => 'Revenue or income has stopped', 'standard' => 'Standard timing'];
    /** Platform ids match the website /{id}-reinstatement/ pages. */
    public const PLATFORMS = [
        'amazon' => 'Amazon',
        'ebay' => 'eBay',
        'walmart' => 'Walmart',
        'etsy' => 'Etsy',
        'wayfair' => 'Wayfair',
        'lowes' => 'Lowe\'s',
        'stockx' => 'StockX',
        'shopify' => 'Shopify',
        'tiktok-shop' => 'TikTok Shop',
        'paypal' => 'PayPal',
        'stripe' => 'Stripe',
        'wise' => 'Wise',
        'payoneer' => 'Payoneer',
        'coinbase' => 'Coinbase',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'twitter' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'reddit' => 'Reddit',
        'discord' => 'Discord',
        'linkedin' => 'LinkedIn',
        'snapchat' => 'Snapchat',
        'pinterest' => 'Pinterest',
        'google-adsense' => 'Google AdSense',
        'google-ads' => 'Google Ads',
        'google-merchant-center' => 'Google Merchant Center',
        'meta-ads' => 'Meta Ads',
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
        'twitch' => 'Twitch',
        'xbox-live' => 'Xbox Live',
        'booking-com' => 'Booking.com',
        'airbnb' => 'Airbnb',
        'uber' => 'Uber',
        'lyft' => 'Lyft',
        'doordash' => 'DoorDash',
        'instacart' => 'Instacart',
        'upwork' => 'Upwork',
        'fiverr' => 'Fiverr',
        'yahoo-mail' => 'Yahoo Mail',
        'gmail' => 'Gmail and Google Account',
        'other' => 'Another platform',
    ];
    /** Service ids match the website /services/ pages (used to target testimonials). */
    public const SERVICES = ['case-review' => 'Suspension Case Review', 'plan-of-action' => 'Plan of Action Writing', 'appeal-prep' => 'Appeal Preparation and Submission Support', 'held-funds' => 'Held Funds and Payouts', 'verification' => 'Identity and Business Verification Support', 'ip-complaints' => 'IP and Authenticity Complaint Response', 'compliance-audit' => 'Policy Compliance Audit', 'monitoring' => 'Account Health Monitoring', 'priority' => 'Priority Case Review'];
    public const POST_CATEGORIES = [
        'commerce' => 'E-commerce and marketplaces', 'payments' => 'Payments and held funds', 'social' => 'Social media',
        'ads' => 'Advertising and publishing', 'content' => 'Video, streaming and gaming', 'gig' => 'Gig, travel and freelance',
        'email' => 'Email and core accounts', 'general' => 'All platforms',
    ];
    public const POST_STATUS = ['draft' => 'Draft', 'published' => 'Published'];
    public const TESTIMONIAL_STATUS = ['pending' => 'Awaiting approval', 'published' => 'Published', 'hidden' => 'Hidden'];
    public const JURIS = ['US' => 'United States', 'CA' => 'Canada', 'UK' => 'United Kingdom', 'EU' => 'European Union', 'OTHER' => 'Elsewhere'];
    public const CLIENT_TYPE = ['individual' => 'Individual', 'organisation' => 'Business'];
    public const RISK = ['standard' => 'Standard', 'elevated' => 'Elevated', 'high' => 'High value or high profile'];

    /** Appeal tracker: each row is one submission to a platform. */
    public const TARGET_STATUS = [
        'drafted' => 'Drafted', 'submitted' => 'Submitted', 'acknowledged' => 'Acknowledged', 'approved' => 'Reinstated or resolved',
        'partial' => 'Partly resolved', 'refused' => 'Rejected', 'appealed' => 'Resubmitted', 'withdrawn' => 'Withdrawn',
        'suppressed' => 'Funds released',
    ];
    public const STRENGTH = ['strong' => 'Strong', 'moderate' => 'Moderate', 'case-dependent' => 'Case-dependent', 'limited' => 'Limited'];
    public const ROUTES = [
        'account_health' => 'Account Health or in-account appeal', 'plan_of_action' => 'Plan of Action', 'policy_review' => 'Policy center review request',
        'appeal_form' => 'Official appeal form', 'identity' => 'Identity or business verification', 'ip_retraction' => 'Rights owner retraction',
        'counter_notice' => 'Counter notice (with attorney)', 'funds_release' => 'Funds release or disbursement request', 'support' => 'Support ticket or case',
        'screening_dispute' => 'Background check dispute (FCRA)', 'deactivation_review' => 'Deactivation review', 'arbitration' => 'Arbitration or legal (with attorney)',
        'other' => 'Other',
    ];
    /** Held funds tracker. */
    public const FUNDS_STATUS = [
        'held' => 'Held', 'requested' => 'Release requested', 'partial' => 'Partly released', 'released' => 'Released',
        'disputed' => 'Disputed or offset', 'forfeited' => 'Forfeited',
    ];
    public const CURRENCIES = ['USD' => 'USD', 'CAD' => 'CAD', 'GBP' => 'GBP', 'EUR' => 'EUR', 'AUD' => 'AUD'];

    public const VISIBILITY = ['shared' => 'Shared with client', 'internal' => 'Internal only', 'confidential' => 'Confidential (restricted)'];
    public const TASK_STATUS = ['open' => 'Open', 'done' => 'Done', 'cancelled' => 'Canceled'];
    public const TASK_PRIORITY = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
    public const DEADLINE_KINDS = [
        'platform_response' => 'Platform response due', 'appeal_window' => 'Appeal window closes', 'funds_release' => 'Expected funds release',
        'monitoring_review' => 'Account health review', 'client_deliverable' => 'Client deliverable', 'regulator' => 'Legal or arbitration deadline', 'other' => 'Other',
    ];
    public const DEADLINE_STATUS = ['upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Canceled'];
    public const INVOICE_STATUS = ['draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Part-paid', 'paid' => 'Paid', 'cancelled' => 'Canceled'];
    public const PAY_METHODS = ['bank_transfer' => 'Bank transfer or ACH', 'card' => 'Card', 'cash' => 'Cash', 'cheque' => 'Check', 'other' => 'Other'];

    /** Ethics screening: every item must be confirmed before a lead is accepted. */
    public const SCREENING = [
        'no_evasion' => 'Not asking us to create, rent or use new or secondary accounts to get around enforcement',
        'genuine_documents' => 'Documents are genuine and in the client\'s own name; nothing is to be created or altered',
        'no_harm_category' => 'Not an account removed for fraud, scams, child safety, sanctions or violent extremism',
        'sanctions_checked' => 'Sanctions and source of funds checked',
        'identity_checked' => 'Identity or authority to act verified',
    ];

    public static function get(string $key): string
    {
        foreach ([self::CASE_STATUS, self::STAGES, self::PRIORITY, self::TARGET_STATUS, self::FUNDS_STATUS, self::INVOICE_STATUS, self::ROLES, self::VISIBILITY, self::POST_STATUS, self::TESTIMONIAL_STATUS] as $set) {
            if (isset($set[$key])) {
                return $set[$key];
            }
        }
        return ucfirst(str_replace('_', ' ', $key));
    }

    /** CSS tone for a status badge. */
    public static function tone(string $key): string
    {
        return match ($key) {
            'emergency', 'refused', 'declined', 'cancelled', 'suspended', 'high', 'forfeited' => 'danger',
            'lead', 'drafted', 'draft', 'invited', 'upcoming', 'open', 'medium', 'nda', 'held', 'disputed', 'pending' => 'warn',
            'active', 'approved', 'paid', 'done', 'completed', 'suppressed', 'strong', 'released', 'published' => 'ok',
            'submitted', 'acknowledged', 'appealed', 'sent', 'qualified', 'monitoring', 'partial', 'moderate', 'requested' => 'info',
            default => 'neutral',
        };
    }
}
