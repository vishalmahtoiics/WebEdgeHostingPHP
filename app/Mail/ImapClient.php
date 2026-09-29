<?php
declare(strict_types=1);

namespace App\Mail;

/**
 * Minimal IMAP4rev1 client over PHP streams (no imap extension needed).
 * Covers what the webmail uses: login, folders, search/sort, fetch, flags,
 * move/copy/expunge and append.
 */
final class ImapClient
{
    /** @var resource */
    private $fp;
    private int $tag = 0;
    private array $caps = [];

    public function __construct(string $host, int $port, string $security, int $timeout = 20)
    {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $fp = @stream_socket_client(($security === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new MailException("Could not connect to the mail server $host:$port" . ($errstr ? " ($errstr)" : '') . '.');
        }
        stream_set_timeout($fp, 60);
        $this->fp = $fp;
        $greeting = $this->readLine();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new MailException('The mail server did not accept the connection.');
        }
        if ($security === 'tls') {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new MailException('Secure connection (STARTTLS) to the mail server failed.');
            }
        }
    }

    public function __destruct()
    {
        if (is_resource($this->fp)) {
            @fwrite($this->fp, 'z' . (++$this->tag) . " LOGOUT\r\n");
            @fclose($this->fp);
        }
    }

    /** Returns false for wrong credentials; throws for connection problems. */
    public function login(string $user, string $password): bool
    {
        if (preg_match('/[\r\n\x00]/', $user . $password)) {
            return false;
        }
        [$status] = $this->command('LOGIN ' . $this->astring($user) . ' ' . $this->astring($password), false);
        if ($status !== 'OK') {
            return false;
        }
        $this->capabilities(true);
        return true;
    }

    public function capabilities(bool $refresh = false): array
    {
        if ($refresh || !$this->caps) {
            [, $lines] = $this->command('CAPABILITY');
            foreach ($lines as $l) {
                if (preg_match('/^\* CAPABILITY (.+)$/i', $l['text'], $m)) {
                    $this->caps = array_map('strtoupper', preg_split('/\s+/', trim($m[1])));
                }
            }
        }
        return $this->caps;
    }

    public function has(string $cap): bool
    {
        return in_array(strtoupper($cap), $this->capabilities(), true);
    }

    /** @return list<array{name: string, label: string, delimiter: string, flags: list<string>, special: ?string}> */
    public function folders(): array
    {
        [, $lines] = $this->command('LIST "" "*"');
        $out = [];
        foreach ($lines as $l) {
            $t = $this->tokens($l);
            if (($t[0] ?? '') !== '*' || strtoupper((string) ($t[1] ?? '')) !== 'LIST') {
                continue;
            }
            $flags = array_map('strval', (array) ($t[2] ?? []));
            if (in_array('\\Noselect', $flags, true) || in_array('\\NonExistent', $flags, true)) {
                continue;
            }
            $name = (string) ($t[4] ?? '');
            $out[] = [
                'name' => $name,
                'label' => self::decodeName($name),
                'delimiter' => (string) ($t[3] ?? '.'),
                'flags' => $flags,
                'special' => self::special($name, $flags),
            ];
        }
        return $out;
    }

    private static function special(string $name, array $flags): ?string
    {
        foreach (['\\Sent' => 'sent', '\\Drafts' => 'drafts', '\\Trash' => 'trash', '\\Junk' => 'junk', '\\Archive' => 'archive'] as $f => $k) {
            if (in_array($f, $flags, true)) {
                return $k;
            }
        }
        if (strtoupper($name) === 'INBOX') {
            return 'inbox';
        }
        $leaf = strtolower((string) preg_replace('/^.*[.\/]/', '', $name));
        return match ($leaf) {
            'sent', 'sent items', 'sent messages', 'sent mail' => 'sent',
            'drafts', 'draft' => 'drafts',
            'trash', 'deleted', 'deleted items', 'deleted messages', 'bin' => 'trash',
            'junk', 'spam', 'junk e-mail', 'junk email', 'bulk mail' => 'junk',
            'archive', 'archives' => 'archive',
            default => null,
        };
    }

    /** @return array{messages: int, unseen: int} */
    public function status(string $folder): array
    {
        [$st, $lines] = $this->command('STATUS ' . $this->astring($folder) . ' (MESSAGES UNSEEN)', false);
        $r = ['messages' => 0, 'unseen' => 0];
        if ($st === 'OK') {
            foreach ($lines as $l) {
                if (preg_match('/MESSAGES (\d+)/i', $l['text'], $m)) {
                    $r['messages'] = (int) $m[1];
                }
                if (preg_match('/UNSEEN (\d+)/i', $l['text'], $m)) {
                    $r['unseen'] = (int) $m[1];
                }
            }
        }
        return $r;
    }

    public function select(string $folder): int
    {
        [$st, $lines] = $this->command('SELECT ' . $this->astring($folder), false);
        if ($st !== 'OK') {
            throw new MailException('That folder could not be opened.');
        }
        foreach ($lines as $l) {
            if (preg_match('/^\* (\d+) EXISTS/i', $l['text'], $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }

    /**
     * UIDs of the selected folder, newest first, optionally filtered by a text query.
     * @return list<int>
     */
    public function uids(string $query = ''): array
    {
        $criteria = 'ALL';
        $charset = '';
        if ($query !== '') {
            $q = $this->astring($query);
            $criteria = "OR OR FROM $q SUBJECT $q OR TO $q BODY $q";
            $charset = 'CHARSET UTF-8 ';
        }
        if ($this->has('SORT')) {
            [$st, $lines] = $this->command("UID SORT (REVERSE ARRIVAL) UTF-8 $criteria", false);
            if ($st === 'OK') {
                return $this->numbersFrom($lines, 'SORT');
            }
        }
        [$st, $lines] = $this->command("UID SEARCH $charset$criteria", false);
        if ($st !== 'OK' && $charset !== '') {
            [$st, $lines] = $this->command("UID SEARCH $criteria", false);
        }
        $uids = $this->numbersFrom($lines, 'SEARCH');
        rsort($uids);
        return $uids;
    }

    private function numbersFrom(array $lines, string $keyword): array
    {
        $out = [];
        foreach ($lines as $l) {
            if (preg_match('/^\* ' . $keyword . '\b(.*)$/i', $l['text'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $n) {
                    if (ctype_digit($n)) {
                        $out[] = (int) $n;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Summary rows for a message list.
     * @param list<int> $uids
     */
    public function summaries(array $uids): array
    {
        if (!$uids) {
            return [];
        }
        [, $lines] = $this->command('UID FETCH ' . implode(',', $uids) . ' (UID FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE)])');
        $rows = [];
        foreach ($lines as $l) {
            $f = $this->fetchItems($l);
            if (!isset($f['UID'])) {
                continue;
            }
            $headerRaw = '';
            foreach ($f as $k => $v) {
                if (str_starts_with($k, 'BODY[')) {
                    $headerRaw = (string) $v;
                }
            }
            $h = Mime::parseHeaders($headerRaw);
            $bs = $f['BODYSTRUCTURE'] ?? [];
            $rows[(int) $f['UID']] = [
                'uid' => (int) $f['UID'],
                'flags' => array_map('strval', (array) ($f['FLAGS'] ?? [])),
                'date' => Mime::date($h['date'][0] ?? (string) ($f['INTERNALDATE'] ?? '')),
                'size' => (int) ($f['RFC822.SIZE'] ?? 0),
                'from' => Mime::addresses($h['from'][0] ?? ''),
                'to' => Mime::addresses($h['to'][0] ?? ''),
                'subject' => Mime::decodeHeader($h['subject'][0] ?? ''),
                'attachment' => self::hasAttachment($bs),
            ];
        }
        // Keep the requested order (newest first).
        $out = [];
        foreach ($uids as $u) {
            if (isset($rows[$u])) {
                $out[] = $rows[$u];
            }
        }
        return $out;
    }

    private static function hasAttachment(mixed $bs): bool
    {
        if (!is_array($bs) || !$bs || !is_array($bs[0])) {
            return false;
        }
        foreach ($bs as $item) {
            if (is_string($item)) {
                return strtoupper($item) === 'MIXED';
            }
        }
        return false;
    }

    /** Full raw message (does not mark it read). */
    public function raw(int $uid): ?string
    {
        [, $lines] = $this->command("UID FETCH $uid (UID BODY.PEEK[])");
        foreach ($lines as $l) {
            $f = $this->fetchItems($l);
            if ((int) ($f['UID'] ?? 0) === $uid && isset($f['BODY[]'])) {
                return (string) $f['BODY[]'];
            }
        }
        return null;
    }

    /** @param list<int> $uids */
    public function flag(array $uids, string $flag, bool $on): void
    {
        if ($uids) {
            $this->command('UID STORE ' . implode(',', $uids) . ($on ? ' +' : ' -') . "FLAGS.SILENT ($flag)");
        }
    }

    /** @param list<int> $uids */
    public function move(array $uids, string $to): void
    {
        if (!$uids) {
            return;
        }
        $set = implode(',', $uids);
        if ($this->has('MOVE')) {
            $this->command("UID MOVE $set " . $this->astring($to));
            return;
        }
        $this->command("UID COPY $set " . $this->astring($to));
        $this->expunge($uids);
    }

    /** Permanently delete. @param list<int> $uids */
    public function expunge(array $uids): void
    {
        if (!$uids) {
            return;
        }
        $set = implode(',', $uids);
        $this->command("UID STORE $set +FLAGS.SILENT (\\Deleted)");
        $this->command($this->has('UIDPLUS') ? "UID EXPUNGE $set" : 'EXPUNGE');
    }

    /** @param list<string> $flags */
    public function append(string $folder, string $message, array $flags = ['\\Seen']): void
    {
        $this->command('APPEND ' . $this->astring($folder) . ' (' . implode(' ', $flags) . ') ' . $this->literal($message));
    }

    public function create(string $folder): void
    {
        [$st] = $this->command('CREATE ' . $this->astring($folder), false);
        if ($st !== 'OK') {
            throw new MailException('The folder could not be created (it may already exist).');
        }
        $this->command('SUBSCRIBE ' . $this->astring($folder), false);
    }

    // ------------------------------------------------------------------ protocol

    /** A string argument: quoted when safe, otherwise a literal. */
    public function astring(string $s): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $s) && strlen($s) < 1000) {
            return '"' . addcslashes($s, '"\\') . '"';
        }
        return $this->literal($s);
    }

    private function literal(string $s): string
    {
        return "\x01" . base64_encode($s) . "\x01";
    }

    /**
     * Send a command (literals marked by literal()) and read the tagged reply.
     * @return array{0: string, 1: list<array{text: string, literals: list<string>}>}
     */
    private function command(string $cmd, bool $mustSucceed = true): array
    {
        $tag = 'a' . (++$this->tag);
        $parts = preg_split("/\x01([A-Za-z0-9+\/=]*)\x01/", $cmd, -1, PREG_SPLIT_DELIM_CAPTURE);
        $plus = $this->caps && (in_array('LITERAL+', $this->caps, true) || in_array('LITERAL-', $this->caps, true));
        $buffer = $tag . ' ';
        for ($i = 0; $i < count($parts); $i++) {
            if ($i % 2 === 0) {
                $buffer .= $parts[$i];
                continue;
            }
            $data = (string) base64_decode($parts[$i]);
            $buffer .= '{' . strlen($data) . ($plus ? '+' : '') . "}\r\n";
            $this->write($buffer);
            $buffer = '';
            if (!$plus) {
                $cont = $this->readLine();
                if (!str_starts_with($cont, '+')) {
                    throw new MailException('The mail server refused the request.');
                }
            }
            $buffer = $data;
        }
        $this->write($buffer . "\r\n");

        $lines = [];
        while (true) {
            $resp = $this->readResponse();
            if (str_starts_with($resp['text'], $tag . ' ')) {
                $status = strtoupper((string) (explode(' ', $resp['text'])[1] ?? ''));
                if ($mustSucceed && $status !== 'OK') {
                    throw new MailException('The mail server reported an error: ' . substr($resp['text'], strlen($tag) + 1, 200));
                }
                return [$status, $lines];
            }
            $lines[] = $resp;
        }
    }

    private function write(string $data): void
    {
        for ($written = 0; $written < strlen($data);) {
            $n = @fwrite($this->fp, substr($data, $written));
            if ($n === false || $n === 0) {
                throw new MailException('Lost connection to the mail server.');
            }
            $written += $n;
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->fp);
        if ($line === false) {
            throw new MailException('Lost connection to the mail server.');
        }
        return rtrim($line, "\r\n");
    }

    /** One response, with literals replaced by \x01<index>\x01 markers. */
    private function readResponse(): array
    {
        $text = '';
        $literals = [];
        while (true) {
            $line = $this->readLine();
            if (preg_match('/\{(\d+)\+?\}$/', $line, $m)) {
                $size = (int) $m[1];
                $text .= substr($line, 0, -strlen($m[0])) . "\x01" . count($literals) . "\x01";
                $data = '';
                while (strlen($data) < $size) {
                    $chunk = fread($this->fp, min(65536, $size - strlen($data)));
                    if ($chunk === false || $chunk === '') {
                        throw new MailException('Lost connection to the mail server.');
                    }
                    $data .= $chunk;
                }
                $literals[] = $data;
                continue;
            }
            return ['text' => $text . $line, 'literals' => $literals];
        }
    }

    /** Tokenise a response into nested arrays of atoms/strings (NIL => null). */
    private function tokens(array $resp): array
    {
        $s = $resp['text'];
        $pos = 0;
        return $this->parseList($s, $pos, $resp['literals'], false);
    }

    private function parseList(string $s, int &$pos, array $lits, bool $nested): array
    {
        $out = [];
        $len = strlen($s);
        while ($pos < $len) {
            $c = $s[$pos];
            if ($c === ' ') {
                $pos++;
            } elseif ($c === '(') {
                $pos++;
                $out[] = $this->parseList($s, $pos, $lits, true);
            } elseif ($c === ')') {
                $pos++;
                if ($nested) {
                    return $out;
                }
            } elseif ($c === '"') {
                $pos++;
                $str = '';
                while ($pos < $len && $s[$pos] !== '"') {
                    if ($s[$pos] === '\\' && $pos + 1 < $len) {
                        $pos++;
                    }
                    $str .= $s[$pos++];
                }
                $pos++;
                $out[] = $str;
            } elseif ($c === "\x01") {
                $end = strpos($s, "\x01", $pos + 1);
                $out[] = $lits[(int) substr($s, $pos + 1, $end - $pos - 1)] ?? '';
                $pos = $end + 1;
            } else {
                $start = $pos;
                $depth = 0;
                while ($pos < $len) {
                    $ch = $s[$pos];
                    if ($ch === '[') {
                        $depth++;
                    } elseif ($ch === ']') {
                        $depth--;
                    } elseif ($depth === 0 && ($ch === ' ' || $ch === '(' || $ch === ')')) {
                        break;
                    }
                    $pos++;
                }
                $atom = substr($s, $start, $pos - $start);
                $out[] = strtoupper($atom) === 'NIL' ? null : $atom;
            }
        }
        return $out;
    }

    /** Key/value items of a "* n FETCH (...)" response. */
    private function fetchItems(array $resp): array
    {
        $t = $this->tokens($resp);
        if (($t[0] ?? '') !== '*' || strtoupper((string) ($t[2] ?? '')) !== 'FETCH' || !is_array($t[3] ?? null)) {
            return [];
        }
        $items = $t[3];
        $out = [];
        for ($i = 0; $i + 1 < count($items); $i += 2) {
            $key = strtoupper((string) $items[$i]);
            // BODY[HEADER.FIELDS (...)] -> keep the section, drop partial "<0>" markers
            $key = (string) preg_replace('/<\d+>$/', '', $key);
            $out[$key] = $items[$i + 1];
        }
        return $out;
    }

    /** Folder names use modified UTF-7 (RFC 3501). */
    public static function decodeName(string $name): string
    {
        if (!str_contains($name, '&')) {
            return $name;
        }
        $d = @mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');
        return is_string($d) && $d !== '' ? $d : $name;
    }

    public static function encodeName(string $name): string
    {
        return preg_match('/^[\x20-\x7e]*$/', $name) && !str_contains($name, '&') ? $name : (string) mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');
    }
}
