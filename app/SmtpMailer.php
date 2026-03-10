<?php

declare(strict_types=1);

final class SmtpMailer
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private float $timeout;

    public function __construct(array $config)
    {
        $this->host = trim((string) ($config['host'] ?? ''));
        $this->port = (int) ($config['port'] ?? 587);
        $this->encryption = strtolower(trim((string) ($config['encryption'] ?? 'tls')));
        $this->username = trim((string) ($config['username'] ?? ''));
        $this->password = (string) ($config['password'] ?? '');
        $this->fromEmail = trim((string) ($config['from_email'] ?? ''));
        $this->fromName = trim((string) ($config['from_name'] ?? 'Daily Checklist'));
        $this->timeout = (float) ($config['timeout'] ?? 15.0);
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->port > 0 && $this->fromEmail !== '';
    }

    public function sendWithAttachment(
        string $toEmail,
        string $subject,
        string $textBody,
        string $attachmentName,
        string $attachmentData,
        ?string &$error = null
    ): bool {
        if (!$this->isConfigured()) {
            $error = 'SMTP belum dikonfigurasi.';
            return false;
        }

        $targetHost = $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host;
        $socket = @stream_socket_client(
            $targetHost . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            $error = 'Gagal konek ke SMTP: ' . $errstr;
            return false;
        }

        stream_set_timeout($socket, (int) $this->timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS gagal.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            }

            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->username), [334]);
                $this->command($socket, base64_encode($this->password), [235]);
            }

            $boundary = 'b-' . bin2hex(random_bytes(8));
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $safeFromName = str_replace(['"', "\r", "\n"], '', $this->fromName);

            $headers = [];
            $headers[] = 'From: "' . $safeFromName . '" <' . $this->fromEmail . '>';
            $headers[] = 'To: <' . $toEmail . '>';
            $headers[] = 'Subject: ' . $encodedSubject;
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $textBody . "\r\n";
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Type: application/pdf; name="' . $attachmentName . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $attachmentName . '"' . "\r\n\r\n";
            $body .= chunk_split(base64_encode($attachmentData));
            $body .= '--' . $boundary . "--\r\n";

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $messageData = preg_replace("/(?<!\r)\n/", "\r\n", $message) ?? $message;
            $messageData = preg_replace('/^\./m', '..', $messageData) ?? $messageData;

            $this->command($socket, 'MAIL FROM:<' . $this->fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $messageData . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);

            fclose($socket);
            return true;
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            return false;
        }
    }

    private function command($socket, string $command, array $expectedCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes);
    }

    private function expect($socket, array $expectedCodes): void
    {
        $response = '';
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                break;
            }
            $response .= $line;
            $isMultiLine = isset($line[3]) && $line[3] === '-';
        } while ($isMultiLine);

        $statusCode = (int) substr(trim($response), 0, 3);
        if (!in_array($statusCode, $expectedCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }
}
