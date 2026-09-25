<?php
declare(strict_types=1);

namespace DR\Http;

use DR\Core\Config;
use DR\Core\HttpError;
use DR\Service\Maintenance;

/** URL-triggered cron for hosts without CLI cron. Protected by a 48-hex token in config. */
final class CronController extends Controller
{
    public function web(array $p, ?array $u): void
    {
        $token = (string) Config::get('cron_token', '');
        if (strlen($token) < 32 || !hash_equals($token, (string) ($p['token'] ?? ''))) {
            throw new HttpError('Page not found', 404);
        }
        $out = Maintenance::runAll();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'result' => $out]);
    }
}
