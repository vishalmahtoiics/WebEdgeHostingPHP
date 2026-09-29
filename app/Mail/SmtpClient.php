<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Sends a message through the user's own SMTP server, authenticated with
 * their mailbox credentials.
 */
final class SmtpClient
{
    /** @var resource */
    private $fp;

    public function __construct(string $host, int $port, string $security, int $timeout = 20)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client(($security === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new MailException("Could not connect to the outgoing mail server $host:$port" . ($errstr ? " ($errstr)" : '') . '.');
        }
        stream_set_timeout($fp, 60);
        $this->fp = $fp;
        $this->expect([220]);
        $this->ehlo();
        if ($security === 'tls') {
            $this->cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new MailException('Secure connection (STARTTLS) to the outgoing mail server failed.');
            }
            $this->ehlo();
        }
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            @fwrite($this->fp, "QUIT\r\n");
            @fclose($this->fp);
        }
    }

    private function ehlo(): void
    {
        $host = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
        $this->cmd("EHLO $host", [250]);
    }

    public function login(string $user, string $password): bool
    {
        [$code] = $this->send('AUTH PLAIN ' . base64_encode("\0$user\0$password"));
        if ($code === 235) {
            return true;
        }
        if ($code === 504 || $code === 502) {
            [$c] = $this->send('AUTH LOGIN');
            if ($c === 334) {
                $this->send(base64_encode($user));
                [$c] = $this->send(base64_encode($password));
                return $c === 235;
            }
        }
        return false;
    }

    /**
     * @param list<string> $recipients
     * @return list<string> recipients the server refused (empty on full success)
     */
    public function sendMessage(string $from, array $recipients, string $raw): array
    {
        $this->cmd('MAIL FROM:<' . $this->clean($from) . '>', [250]);
        $refused = [];
        foreach ($recipients as $r) {
            [$code] = $this->send('RCPT TO:<' . $this->clean($r) . '>');
            if ($code !== 250 && $code !== 251) {
                $refused[] = $r;
            }
        }
        if (count($refused) === count($recipients)) {
            $this->send('RSET');
            return $refused;
        }
        $this->cmd('DATA', [354]);
        $data = str_replace(["\r\n", "\r"], "\n", $raw);
        $data = (string) preg_replace('/^\./m', '..', $data);
        $data = str_replace("\n", "\r\n", rtrim($data, "\n")) . "\r\n.\r\n";
        fwrite($this->fp, $data);
        $this->expect([250]);
        return $refused;
    }

    private function clean(string $address): string
    {
        return (string) preg_replace('/[\r\n<>\s]/', '', $address);
    }

    private function cmd(string $line, array $ok): string
    {
        [$code, $text] = $this->send($line);
        if (!in_array($code, $ok, true)) {
            throw new MailException('The outgoing mail server refused the message: ' . $text);
        }
        return $text;
    }

    /** @return array{0: int, 1: string} */
    private function send(string $line): array
    {
        fwrite($this->fp, $line . "\r\n");
        return $this->read();
    }

    private function expect(array $ok): void
    {
        [$code, $text] = $this->read();
        if (!in_array($code, $ok, true)) {
            throw new MailException('The outgoing mail server replied: ' . $text);
        }
    }

    /** @return array{0: int, 1: string} */
    private function read(): array
    {
        $text = '';
        while (($line = fgets($this->fp, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        if ($text === '') {
            throw new MailException('Lost connection to the outgoing mail server.');
        }
        return [(int) substr($text, 0, 3), trim(substr(trim($text), 4, 300))];
    }
}
