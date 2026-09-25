<?php
/**
 * Website enquiry handler settings. Blocked from web access by /api/.htaccess.
 *
 * Enquiries are written straight into AS Case Vault (the CRM) as encrypted leads.
 * Origin checks, rate limits and the optional Turnstile secret are configured in
 * ascrm/config/config.php under "intake".
 */
declare(strict_types=1);

// Absolute path of the ascrm folder. Leave empty to use the default: the folder "ascrm"
// next to public_html (for example /home/u000/domains/accountsuspension.com/ascrm).
const ASCRM_PATH = '';
