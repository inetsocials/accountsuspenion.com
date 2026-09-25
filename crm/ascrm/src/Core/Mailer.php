<?php
declare(strict_types=1);

namespace DR\Core;

/**
 * Outbound email. Policy: emails never contain case content. They say that something is
 * waiting in the portal and link to it. Drivers: PHP mail() or authenticated SMTP (TLS).
 * In tests (config mail_driver = "log") messages are written to storage/mail/.
 */
final class Mailer
{
    public static function notice(string $to, string $subject, string $body, ?string $link = null, string $linkLabel = 'Open the secure portal'): bool
    {
        $portal = Settings::get('portal_name');
        $text = $body . "\n\n" . ($link ? $linkLabel . ': ' . $link . "\n\n" : '')
            . "For your security this email contains no case details. Everything is in your secure portal.\n"
            . Settings::get('org_name') . " will never ask for your password or authentication codes.\n";
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto;color:#1A2233">'
            . '<p style="font-size:13px;color:#566074;letter-spacing:.08em;text-transform:uppercase">' . h($portal) . '</p>'
            . '<h1 style="font-size:20px;color:#051733">' . h($subject) . '</h1>'
            . '<p style="line-height:1.6">' . nl2br(h($body)) . '</p>'
            . ($link ? '<p><a href="' . h($link) . '" style="display:inline-block;background:#0054E4;color:#fff;padding:12px 20px;border-radius:999px;text-decoration:none;font-weight:bold">' . h($linkLabel) . '</a></p>' : '')
            . '<p style="font-size:12px;color:#566074;line-height:1.6;border-top:1px solid #E2E8F0;padding-top:12px">For your security this email contains no case details. '
            . h(Settings::get('org_name')) . ' will never ask for your password or authentication codes.</p></div>';
        return self::send($to, $subject, $text, $html);
    }

    public static function send(string $to, string $subject, string $text, string $html): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $from = Settings::get('mail_from') ?: (string) Config::get('mail_from', '');
        $fromName = str_replace(["\r", "\n", '"'], '', Settings::get('mail_from_name'));
        $driver = (string) Config::get('mail_driver_override', '') ?: Settings::get('mail_driver');
        $boundary = 'b' . bin2hex(random_bytes(12));
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "--{$boundary}--\r\n";
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . ($fromName !== '' ? '"' . $fromName . '" ' : '') . '<' . $from . '>',
            'X-Auto-Response-Suppress: All',
        ];
        try {
            if ($driver === 'log') {
                $dir = DR_STORAGE . '/mail';
                if (!is_dir($dir)) {
                    mkdir($dir, 0700, true);
                }
                file_put_contents($dir . '/' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.eml',
                    "To: {$to}\r\nSubject: {$subject}\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $text);
                return true;
            }
            if ($from === '') {
                return false;
            }
            if ($driver === 'smtp') {
                return self::smtp($to, $subject, $headers, $body, $from);
            }
            $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            return mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $from);
        } catch (\Throwable $e) {
            Log::error($e);
            return false;
        }
    }

    private static function smtp(string $to, string $subject, array $headers, string $body, string $from): bool
    {
        $host = Settings::get('smtp_host');
        $port = (int) Settings::get('smtp_port');
        $secure = Settings::get('smtp_secure');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException('SMTP connect failed');
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('SMTP error at ' . strtok($c, ' ') . ': ' . trim(substr($r, 0, 3)));
            }
            return $r;
        };
        $read();
        $ehloHost = parse_url(Settings::get('website_url'), PHP_URL_HOST) ?: 'localhost';
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('SMTP TLS failed');
            }
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        $user = Settings::get('smtp_user');
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode(Settings::get('smtp_pass')), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = 'To: <' . $to . ">\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . ">\r\n" . implode("\r\n", $headers) . "\r\n\r\n"
            . preg_replace('/^\./m', '..', $body);
        $cmd($msg . "\r\n.", [250]);
        $cmd('QUIT', [221]);
        fclose($fp);
        return true;
    }
}
