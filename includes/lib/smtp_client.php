<?php
/**
 * Public Utility Management System — Standalone Pure-PHP SMTP & Mailer Client
 * Supports SMTP (TLS, SSL, Plain), AUTH LOGIN / PLAIN, HTML content & binary attachments
 */

class SimpleSMTPClient
{
    private string $host;
    private int $port;
    private string $secure; // 'tls', 'ssl', 'none'
    private string $username;
    private string $password;
    private int $timeout;
    private $socket = null;
    private string $lastError = '';
    private array $logs = [];

    public function __construct(string $host, int $port = 587, string $secure = 'tls', string $username = '', string $password = '', int $timeout = 15)
    {
        $this->host = $host;
        $this->port = $port;
        $this->secure = strtolower($secure);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    private function log(string $msg): void
    {
        $this->logs[] = date('H:i:s') . ' - ' . $msg;
    }

    /**
     * Send email via SMTP
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient name
     * @param string $fromEmail Sender email
     * @param string $fromName Sender name
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string $plainText Plain text alternative
     * @param array $attachments Array of ['name' => 'bill.pdf', 'data' => binaryString, 'type' => 'application/pdf']
     * @return bool True on success, false on failure
     */
    public function send(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody, string $plainText = '', array $attachments = []): bool
    {
        $this->logs = [];
        $this->lastError = '';

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Invalid recipient email address: $toEmail";
            return false;
        }

        $hostPrefix = ($this->secure === 'ssl') ? 'ssl://' : '';
        $targetHost = $hostPrefix . $this->host;

        $this->log("Connecting to $targetHost:{$this->port}...");
        $this->socket = @fsockopen($targetHost, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->lastError = "Could not connect to SMTP host {$this->host}:{$this->port} ($errno: $errstr)";
            $this->log($this->lastError);
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        $greeting = $this->readResponse();
        if (!$this->isCode($greeting, '220')) {
            $this->lastError = "Invalid greeting from SMTP server: $greeting";
            $this->close();
            return false;
        }

        // Send EHLO
        $heloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->sendCommand("EHLO $heloHost");
        $ehloResp = $this->readResponse();

        // STARTTLS if configured
        if ($this->secure === 'tls') {
            $this->log("Starting TLS handshake...");
            $this->sendCommand("STARTTLS");
            $tlsResp = $this->readResponse();
            if (!$this->isCode($tlsResp, '220')) {
                $this->lastError = "STARTTLS negotiation failed: $tlsResp";
                $this->close();
                return false;
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($this->socket, true, $cryptoMethod)) {
                $this->lastError = "Failed to enable TLS encryption on SMTP stream.";
                $this->close();
                return false;
            }

            // Re-send EHLO after TLS established
            $this->sendCommand("EHLO $heloHost");
            $this->readResponse();
        }

        // Authenticate if username provided
        if (!empty($this->username)) {
            $this->log("Authenticating as {$this->username}...");
            $this->sendCommand("AUTH LOGIN");
            $authResp = $this->readResponse();
            if (!$this->isCode($authResp, '334')) {
                $this->lastError = "AUTH LOGIN rejected: $authResp";
                $this->close();
                return false;
            }

            $this->sendCommand(base64_encode($this->username));
            $userResp = $this->readResponse();
            if (!$this->isCode($userResp, '334')) {
                $this->lastError = "Username rejected: $userResp";
                $this->close();
                return false;
            }

            $this->sendCommand(base64_encode($this->password));
            $passResp = $this->readResponse();
            if (!$this->isCode($passResp, '235')) {
                $this->lastError = "Authentication failed (Invalid password/credentials): $passResp";
                $this->close();
                return false;
            }
            $this->log("SMTP Authentication successful.");
        }

        // MAIL FROM
        $this->sendCommand("MAIL FROM:<$fromEmail>");
        $fromResp = $this->readResponse();
        if (!$this->isCode($fromResp, '250')) {
            $this->lastError = "MAIL FROM rejected: $fromResp";
            $this->close();
            return false;
        }

        // RCPT TO
        $this->sendCommand("RCPT TO:<$toEmail>");
        $rcptResp = $this->readResponse();
        if (!$this->isCode($rcptResp, '250') && !$this->isCode($rcptResp, '251')) {
            $this->lastError = "RCPT TO rejected: $rcptResp";
            $this->close();
            return false;
        }

        // DATA
        $this->sendCommand("DATA");
        $dataResp = $this->readResponse();
        if (!$this->isCode($dataResp, '354')) {
            $this->lastError = "DATA rejected: $dataResp";
            $this->close();
            return false;
        }

        // Build MIME message
        $mimeMessage = $this->buildMimeMessage($toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $plainText, $attachments);

        // Send payload and terminate with CRLF.CRLF
        $this->sendRaw($mimeMessage . "\r\n.\r\n");
        $sendResp = $this->readResponse();

        if (!$this->isCode($sendResp, '250')) {
            $this->lastError = "Message delivery failed: $sendResp";
            $this->close();
            return false;
        }

        $this->log("Email delivered successfully: $sendResp");
        $this->sendCommand("QUIT");
        $this->close();
        return true;
    }

    private function buildMimeMessage(string $toEmail, string $toName, string $fromEmail, string $fromName, string $subject, string $htmlBody, string $plainText, array $attachments): string
    {
        $boundaryMixed = '=_mixed_' . md5(uniqid(microtime(true), true));
        $boundaryAlt = '=_alt_' . md5(uniqid(microtime(true), true));

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = !empty($fromName) ? '=?UTF-8?B?' . base64_encode($fromName) . '?=' : '';
        $fromFormatted = !empty($encodedFromName) ? "$encodedFromName <$fromEmail>" : "<$fromEmail>";
        $toFormatted = !empty($toName) ? '"' . addslashes($toName) . "\" <$toEmail>" : "<$toEmail>";

        $headers = [];
        $headers[] = "Date: " . date('r');
        $headers[] = "To: $toFormatted";
        $headers[] = "From: $fromFormatted";
        $headers[] = "Subject: $encodedSubject";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Message-ID: <" . md5(uniqid(microtime(true), true)) . "@" . ($_SERVER['SERVER_NAME'] ?? 'publicutility.local') . ">";
        $headers[] = "X-Mailer: PublicUtility-PHPMailer/2.0";

        if (!empty($attachments)) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundaryMixed\"";
            $body = "--$boundaryMixed\r\n";
            $headersCombined = implode("\r\n", $headers);

            // Alternate block for HTML/Text
            $body .= "Content-Type: multipart/alternative; boundary=\"$boundaryAlt\"\r\n\r\n";

            if (!empty($plainText)) {
                $body .= "--$boundaryAlt\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($plainText)) . "\r\n";
            }

            $body .= "--$boundaryAlt\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--$boundaryAlt--\r\n";

            // Attachments
            foreach ($attachments as $att) {
                $attName = $att['name'] ?? 'document.pdf';
                $attType = $att['type'] ?? 'application/pdf';
                $attData = $att['data'] ?? '';

                $body .= "--$boundaryMixed\r\n";
                $body .= "Content-Type: $attType; name=\"$attName\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$attName\"\r\n\r\n";
                $body .= chunk_split(base64_encode($attData)) . "\r\n";
            }

            $body .= "--$boundaryMixed--";
            return $headersCombined . "\r\n\r\n" . $body;
        } else {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundaryAlt\"";
            $headersCombined = implode("\r\n", $headers);

            $body = "";
            if (!empty($plainText)) {
                $body .= "--$boundaryAlt\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($plainText)) . "\r\n";
            }

            $body .= "--$boundaryAlt\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            $body .= "--$boundaryAlt--";

            return $headersCombined . "\r\n\r\n" . $body;
        }
    }

    private function sendCommand(string $cmd): void
    {
        $this->log("> $cmd");
        fwrite($this->socket, $cmd . "\r\n");
    }

    private function sendRaw(string $data): void
    {
        fwrite($this->socket, $data);
    }

    private function readResponse(): string
    {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 512);
            if ($line === false) break;
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $this->log("< " . trim($response));
        return trim($response);
    }

    private function isCode(string $response, string $code): bool
    {
        return (substr($response, 0, 3) === $code);
    }

    private function close(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
