<?php
/**
 * Outgoing e-mail, chosen by MAIL_MODE in .env:
 *  fixed / log : local development only; nothing leaves the computer, the message is written to storage/logs/mail.log
 *  smtp        : a real mail server (MAIL_HOST, MAIL_PORT, MAIL_SECURE=tls|ssl|none, MAIL_USER, MAIL_PASS)
 * Sender name/address: MAIL_FROM_NAME, MAIL_FROM. $cfg (optional) can override name / email / reply / smtp for one message.
 */
final class Mailer {
    static function send(string $to, string $subject, string $body, ?array $cfg = null): void {
        $smtp = $cfg['smtp'] ?? null;
        if (!$smtp) {
            $mode = env('MAIL_MODE', 'log');
            if ($mode === 'fixed' || $mode === 'log') {
                if (env('APP_ENV') !== 'local') throw new RuntimeException('Real email (MAIL_MODE=smtp) is required outside local development.');
                $from = trim(($cfg['name'] ?? '') . ' <' . ($cfg['email'] ?? '') . '>'); $rt = $cfg['reply'] ?? '';
                @file_put_contents(ROOT . '/storage/logs/mail.log', gmdate('c') . " TO $to | " . ($from !== '<>' ? "FROM $from | " : '') . ($rt !== '' ? "REPLY-TO $rt | " : '') . "$subject | $body\n", FILE_APPEND);
                return;
            }
            if ($mode !== 'smtp' || !env('MAIL_HOST')) throw new RuntimeException('Mail is not configured on this server.');
            $smtp = ['host' => (string)env('MAIL_HOST'), 'port' => (int)env('MAIL_PORT', '587'), 'secure' => (string)env('MAIL_SECURE', 'tls'), 'user' => (string)env('MAIL_USER', ''), 'pass' => (string)env('MAIL_PASS', '')];
        }
        $addr = ($cfg['email'] ?? '') !== '' ? $cfg['email'] : (string)(env('MAIL_FROM') ?: ($smtp['user'] ?: 'no-reply@localhost'));
        $name = ($cfg['name'] ?? '') !== '' ? $cfg['name'] : (string)env('MAIL_FROM_NAME', 'Krono');
        self::smtp($smtp, $addr, $name, (string)($cfg['reply'] ?? ''), $to, $subject, $body);
    }

    private static function clean(string $s): string { return trim(str_replace(["\r", "\n", '<', '>'], ' ', $s)); }
    private static function enc(string $s): string { return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?='; }

    private static function smtp(array $c, string $from, string $fromName, string $reply, string $to, string $subject, string $body): void {
        $host = (string)$c['host']; $port = (int)$c['port'] ?: 587; $sec = (string)($c['secure'] ?? 'tls');
        $fp = @stream_socket_client(($sec === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 10);
        if (!$fp) { error_log("SMTP connect failed: $es"); throw new RuntimeException('Could not connect to the mail server.'); }
        stream_set_timeout($fp, 15);
        $read = function () use ($fp): array { $t = ''; while (($l = fgets($fp, 1024)) !== false) { $t .= $l; if (strlen($l) < 4 || $l[3] !== '-') break; } return [(int)substr($t, 0, 3), $t]; };
        $cmd = function (string $line, array $ok) use ($fp, $read): void { fwrite($fp, $line . "\r\n"); [$code, $txt] = $read(); if (!in_array($code, $ok, true)) { error_log('SMTP rejected: ' . trim($txt)); throw new RuntimeException('The mail server did not accept the message.'); } };
        try {
            [$code] = $read(); if ($code !== 220) throw new RuntimeException('The mail server is not ready.');
            $cmd('EHLO krono.local', [250]);
            if ($sec === 'tls') { $cmd('STARTTLS', [220]); if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Could not secure the connection to the mail server.'); $cmd('EHLO krono.local', [250]); }
            if (($c['user'] ?? '') !== '') { $cmd('AUTH LOGIN', [334]); $cmd(base64_encode((string)$c['user']), [334]); $cmd(base64_encode((string)$c['pass']), [235]); }
            $from = self::clean($from); $to = self::clean($to);
            $cmd("MAIL FROM:<$from>", [250]); $cmd("RCPT TO:<$to>", [250, 251]); $cmd('DATA', [354]);
            $h = ['From: ' . self::enc(self::clean($fromName)) . " <$from>", "To: <$to>", 'Subject: ' . self::enc(self::clean($subject)), 'Date: ' . gmdate('r'), 'Message-ID: <' . bin2hex(random_bytes(8)) . '@krono>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'];
            if ($reply !== '') $h[] = 'Reply-To: <' . self::clean($reply) . '>';
            fwrite($fp, implode("\r\n", $h) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
            [$code, $txt] = $read(); if ($code !== 250) { error_log('SMTP data rejected: ' . trim($txt)); throw new RuntimeException('The mail server did not accept the message.'); }
            @fwrite($fp, "QUIT\r\n");
        } finally { @fclose($fp); }
    }
}
