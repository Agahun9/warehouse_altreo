<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

class MailService
{
    public function send($to, $subject, $html, $text = ''): bool
    {
        $config = Config::get('app');
        $from = (string) $config['mail_from'];
        $fromName = (string) $config['mail_from_name'];

        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $from . '>';

        $sent = @mail((string) $to, (string) $subject, (string) $html, implode("\r\n", $headers));

        if ($sent) {
            return true;
        }

        $this->logMail($to, $subject, $html, $text);
        return false;
    }

    private function logMail($to, $subject, $html, $text): void
    {
        $config = Config::get('app');
        $dir = (string) $config['mail_log_dir'];

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $file = $dir . '/mail_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.html';
        $content = '<h3>TO: ' . htmlspecialchars((string) $to, ENT_QUOTES, 'UTF-8') . '</h3>';
        $content .= '<h4>SUBJECT: ' . htmlspecialchars((string) $subject, ENT_QUOTES, 'UTF-8') . '</h4>';
        $content .= '<div>' . $html . '</div>';

        if ($text !== '') {
            $content .= '<pre>' . htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8') . '</pre>';
        }

        @file_put_contents($file, $content);
    }
}
