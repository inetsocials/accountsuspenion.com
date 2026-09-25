<?php
/**
 * Scheduler entry point. hPanel > Advanced > Cron Jobs, every 15 minutes:
 *   php /home/USER/domains/accountsuspension.com/ascrm/cli/cron.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

if (!DR\Core\Config::installed()) {
    fwrite(STDERR, "Not installed.\n");
    exit(1);
}
$lock = fopen(DR_STORAGE . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another run is in progress.\n");
    exit(0);
}
$out = DR\Service\Maintenance::runAll();
echo date('m/d/Y H:i:s') . ' ' . json_encode($out) . PHP_EOL;
flock($lock, LOCK_UN);
fclose($lock);
