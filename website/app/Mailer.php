<?php
final class Mailer {
    static function send(string $to, string $subject, string $body): void {
        $mode = env('MAIL_MODE', 'log');
        if ($mode === 'fixed' || $mode === 'log') {
            if (env('APP_ENV') !== 'local') throw new RuntimeException('Real email (MAIL_MODE=smtp) is required outside local development.');
            @file_put_contents(ROOT . '/storage/logs/mail.log', gmdate('c') . " TO $to | $subject | $body\n", FILE_APPEND);
            return;
        }
        throw new RuntimeException('SMTP mailer is not implemented yet (planned: PHPMailer + free SMTP).');
    }
}
