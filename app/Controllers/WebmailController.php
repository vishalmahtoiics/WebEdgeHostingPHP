<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Logger;
use App\Core\Session;
use App\Core\Settings;
use App\Mail\HtmlSanitizer;
use App\Mail\ImapClient;
use App\Mail\MailException;
use App\Mail\Mime;
use App\Mail\Webmail;
use App\Services\EmailService;

/**
 * Built-in webmail at /mails. Customers sign in with their mailbox address and
 * password; everything is read from and sent through their own mail servers.
 */
final class WebmailController extends Controller
{
    private ?ImapClient $imap = null;

    // ------------------------------------------------------------------ session

    public function index(): string
    {
        if (!Webmail::enabled()) {
            abort(404);
        }
        if (Webmail::user()) {
            redirect('/mails/list');
        }
        return view('webmail/login', ['title' => 'Webmail'], 'layouts/webmail');
    }

    public function login(): string
    {
        if (!Webmail::enabled()) {
            abort(404);
        }
        $error = Webmail::login(input_str('email'), (string) ($_POST['password'] ?? ''));
        if ($error !== null) {
            Session::flashInput(['email' => input_str('email')]);
            flash('danger', $error);
            redirect('/mails');
        }
        redirect('/mails/list');
    }

    public function logout(): string
    {
        Webmail::logout();
        flash('success', 'You have been signed out.');
        redirect('/mails');
    }

    // ------------------------------------------------------------------ reading

    public function list(): string
    {
        $user = $this->user();
        return $this->mail(function () use ($user): string {
            $folders = $this->folders();
            $folder = $this->folderParam($folders);
            $q = mb_substr(trim(query('q')), 0, 200);
            $this->imap->select($folder);
            $uids = $this->imap->uids($q);
            $size = Webmail::prefs($user['email'])['page_size'];
            $pages = max(1, (int) ceil(count($uids) / $size));
            $page = min($pages, max(1, (int) query('page', '1')));
            $rows = $this->imap->summaries(array_slice($uids, ($page - 1) * $size, $size));
            return $this->page('webmail/list', [
                'title' => $this->folderLabel($folders, $folder),
                'folders' => $folders, 'folder' => $folder, 'rows' => $rows, 'q' => $q,
                'page' => $page, 'pages' => $pages, 'total' => count($uids),
                'special' => $this->specialOf($folders, $folder),
            ]);
        });
    }

    public function read(): string
    {
        $this->user();
        return $this->mail(function (): string {
            $folders = $this->folders();
            $folder = $this->folderParam($folders);
            $uid = (int) query('uid');
            $this->imap->select($folder);
            $raw = $this->imap->raw($uid);
            if ($raw === null) {
                flash('warning', 'That message no longer exists.');
                redirect('/mails/list', ['folder' => $folder]);
            }
            $this->imap->flag([$uid], '\\Seen', true);
            $msg = Mime::parse($raw);
            $allowImages = query('images') === '1';
            $cid = [];
            foreach ($msg['parts'] as $p) {
                if ($p['cid'] !== '') {
                    $cid[strtolower($p['cid'])] = url('/mails/part', ['folder' => $folder, 'uid' => $uid, 'part' => $p['id'], 'inline' => 1]);
                }
            }
            $remote = false;
            if ($msg['html'] !== null) {
                $clean = HtmlSanitizer::clean($msg['html'], static fn (string $id): ?string => $cid[strtolower($id)] ?? null);
                $body = $clean['html'];
                $remote = $clean['remote_images'];
            } else {
                $body = HtmlSanitizer::textToHtml((string) $msg['text']);
            }
            // The message is shown in a sandboxed frame that inherits this page's policy:
            // remote images load only when the reader asks for them.
            header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:"
                . ($allowImages ? ' https: http:' : '') . "; connect-src 'self'; frame-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
            return $this->page('webmail/read', [
                'title' => $msg['subject'] ?: '(no subject)',
                'folders' => $folders, 'folder' => $folder, 'uid' => $uid, 'msg' => $msg,
                'body' => $body, 'remoteImages' => $remote && !$allowImages,
                'attachments' => array_values(array_filter($msg['parts'], static fn ($p) => !($p['inline'] && $p['cid'] !== '' && str_starts_with($p['type'], 'image/')))),
                'special' => $this->specialOf($folders, $folder),
            ]);
        });
    }

    public function part(): string
    {
        $this->user();
        return $this->mail(function (): string {
            $folder = $this->folderParam($this->folders());
            $this->imap->select($folder);
            $raw = $this->imap->raw((int) query('uid'));
            $p = $raw !== null ? Mime::partContent($raw, (string) query('part')) : null;
            if (!$p) {
                abort(404);
            }
            $inlineOk = query('inline') === '1' && in_array($p['type'], ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true);
            $this->sendFile($p['data'], $inlineOk ? $p['type'] : 'application/octet-stream', $p['filename'], $inlineOk);
        });
    }

    /** Download the whole message as .eml. */
    public function source(): string
    {
        $this->user();
        return $this->mail(function (): string {
            $folder = $this->folderParam($this->folders());
            $this->imap->select($folder);
            $raw = $this->imap->raw((int) query('uid'));
            if ($raw === null) {
                abort(404);
            }
            $this->sendFile($raw, 'message/rfc822', 'message-' . (int) query('uid') . '.eml', false);
        });
    }

    // ------------------------------------------------------------------ writing

    public function compose(): string
    {
        $user = $this->user();
        return $this->mail(function () use ($user): string {
            $folders = $this->folders();
            $prefs = Webmail::prefs($user['email']);
            $mode = query('mode');
            $f = ['to' => '', 'cc' => '', 'bcc' => '', 'subject' => '', 'body' => '', 'in_reply_to' => '', 'references' => '',
                'reply_folder' => '', 'reply_uid' => '', 'fwd_folder' => '', 'fwd_uid' => '', 'draft_uid' => '', 'fwd_files' => []];
            $sig = trim($prefs['signature']) !== '' ? "\n\n-- \n" . $prefs['signature'] : '';
            if (in_array($mode, ['reply', 'replyall', 'forward', 'draft'], true)) {
                $folder = $this->folderParam($folders);
                $uid = (int) query('uid');
                $this->imap->select($folder);
                $raw = $this->imap->raw($uid);
                if ($raw === null) {
                    abort(404);
                }
                $m = Mime::parse($raw);
                $list = static fn (array $a): string => implode(', ', array_map(static fn ($x) => Mime::formatAddress($x['email'], $x['name']), $a));
                $plain = $m['text'] ?? trim(html_entity_decode(strip_tags((string) preg_replace('/<(br|\/p|\/div|\/tr|\/h\d)[^>]*>/i', "\n", (string) $m['html'])), ENT_QUOTES, 'UTF-8'));
                if ($mode === 'draft') {
                    $f = array_merge($f, ['to' => $list($m['to']), 'cc' => $list($m['cc']), 'bcc' => $list(Mime::addresses($m['headers']['bcc'][0] ?? '')),
                        'subject' => $m['subject'], 'body' => (string) $plain, 'draft_uid' => (string) $uid,
                        'in_reply_to' => trim($m['headers']['in-reply-to'][0] ?? ''), 'references' => $m['references']]);
                } elseif ($mode === 'forward') {
                    $f['subject'] = preg_match('/^(fwd?|fw):/i', $m['subject']) ? $m['subject'] : 'Fwd: ' . $m['subject'];
                    $f['body'] = $sig . "\n\n---------- Forwarded message ----------\nFrom: " . $list($m['from']) . "\nDate: " . ($m['date'] ? date('D, j M Y H:i', $m['date']) : '')
                        . "\nSubject: " . $m['subject'] . "\nTo: " . $list($m['to']) . "\n\n" . $plain;
                    $f['fwd_folder'] = $folder;
                    $f['fwd_uid'] = (string) $uid;
                    $f['fwd_files'] = array_values(array_filter($m['parts'], static fn ($p) => !($p['inline'] && $p['cid'] !== '')));
                } else {
                    $replyTo = $m['reply_to'] ?: $m['from'];
                    $f['to'] = $list($replyTo);
                    if ($mode === 'replyall') {
                        $seen = array_map(static fn ($a) => strtolower($a['email']), array_merge($replyTo, [['email' => $user['email']]]));
                        $cc = array_filter(array_merge($m['to'], $m['cc']), static function ($a) use (&$seen) {
                            $e = strtolower($a['email']);
                            if (in_array($e, $seen, true)) {
                                return false;
                            }
                            $seen[] = $e;
                            return true;
                        });
                        $f['cc'] = $list(array_values($cc));
                    }
                    $f['subject'] = preg_match('/^re:/i', $m['subject']) ? $m['subject'] : 'Re: ' . $m['subject'];
                    $who = $m['from'][0]['name'] ?? '' ?: ($m['from'][0]['email'] ?? '');
                    $quoted = preg_replace('/^/m', '> ', rtrim((string) $plain));
                    $f['body'] = $sig . "\n\nOn " . ($m['date'] ? date('D, j M Y \a\t H:i', $m['date']) : 'an earlier date') . ", $who wrote:\n" . $quoted;
                    $f['in_reply_to'] = $m['message_id'];
                    $f['references'] = trim($m['references'] . ' ' . $m['message_id']);
                    $f['reply_folder'] = $folder;
                    $f['reply_uid'] = (string) $uid;
                }
            } else {
                $f['to'] = mb_substr(query('to'), 0, 300);
                $f['body'] = $sig;
            }
            return $this->page('webmail/compose', ['title' => 'New message', 'folders' => $folders, 'folder' => '', 'f' => $f,
                'maxMb' => max(1, Settings::int('webmail.max_attachment_mb'))]);
        });
    }

    public function send(): string
    {
        $user = $this->user();
        return $this->mail(function () use ($user): string {
            $folders = $this->folders();
            $isDraft = input('save_draft') !== null;
            $parse = static function (string $v): array {
                $out = [];
                foreach (Mime::addresses($v) as $a) {
                    if ($a['email'] !== '') {
                        $out[] = $a;
                    }
                }
                return $out;
            };
            $to = $parse(input_str('to'));
            $cc = $parse(input_str('cc'));
            $bcc = $parse(input_str('bcc'));
            $all = array_merge($to, $cc, $bcc);
            $errors = [];
            foreach ($all as $a) {
                if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Not a valid email address: ' . $a['email'];
                }
            }
            if (!$isDraft && !$all) {
                $errors[] = 'Add at least one recipient.';
            }
            if (count($all) > 100) {
                $errors[] = 'Send to at most 100 recipients at a time.';
            }
            $subject = mb_substr(input_str('subject'), 0, 500);
            $text = mb_substr((string) ($_POST['body'] ?? ''), 0, 2_000_000);

            // Attachments: uploads plus (when forwarding) the original message's files.
            $files = [];
            $total = 0;
            $max = max(1, Settings::int('webmail.max_attachment_mb')) * 1024 * 1024;
            $up = $_FILES['attachments'] ?? null;
            if (is_array($up) && is_array($up['name'] ?? null)) {
                foreach ($up['name'] as $i => $name) {
                    if (($up['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($up['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'][$i])) {
                        $errors[] = "The file $name could not be uploaded (it may be too large).";
                        continue;
                    }
                    $data = (string) file_get_contents($up['tmp_name'][$i]);
                    $total += strlen($data);
                    $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: 'application/octet-stream';
                    $files[] = [mb_substr(basename((string) $name), 0, 150), $type, $data];
                }
            }
            $fwdFolder = input_str('fwd_folder');
            $fwdUid = (int) input('fwd_uid', 0);
            $keep = array_map('strval', (array) ($_POST['fwd_keep'] ?? []));
            if ($fwdFolder !== '' && $fwdUid > 0 && $keep) {
                $this->assertFolder($folders, $fwdFolder);
                $this->imap->select($fwdFolder);
                $raw = $this->imap->raw($fwdUid);
                foreach ($keep as $partId) {
                    if ($raw !== null && ($p = Mime::partContent($raw, $partId))) {
                        $total += strlen($p['data']);
                        $files[] = [$p['filename'], $p['type'], $p['data']];
                    }
                }
            }
            if ($total > $max) {
                $errors[] = 'Attachments are larger than ' . (int) ($max / 1048576) . ' MB in total.';
            }
            if ($errors) {
                Session::flashInput($_POST);
                foreach ($errors as $e) {
                    flash('danger', $e);
                }
                return $this->page('webmail/compose', ['title' => 'New message', 'folders' => $folders, 'folder' => '', 'maxMb' => (int) ($max / 1048576), 'f' => [
                    'to' => input_str('to'), 'cc' => input_str('cc'), 'bcc' => input_str('bcc'), 'subject' => $subject, 'body' => $text,
                    'in_reply_to' => input_str('in_reply_to'), 'references' => input_str('references'), 'reply_folder' => input_str('reply_folder'),
                    'reply_uid' => input_str('reply_uid'), 'fwd_folder' => $fwdFolder, 'fwd_uid' => (string) $fwdUid, 'draft_uid' => input_str('draft_uid'), 'fwd_files' => [],
                ]]);
            }

            $prefs = Webmail::prefs($user['email']);
            $extra = [];
            $irt = input_str('in_reply_to');
            if (preg_match('/^<[^<>\s]+>$/', $irt)) {
                $extra['In-Reply-To'] = $irt;
                $refs = implode(' ', array_slice(array_filter(preg_split('/\s+/', input_str('references')) ?: [], static fn ($r) => (bool) preg_match('/^<[^<>\s]+>$/', $r)), -20));
                $extra['References'] = $refs !== '' ? $refs : $irt;
            }
            $built = Mime::build(['email' => $user['email'], 'name' => $prefs['display_name']], $to, $cc, $bcc, $subject, $text, $files, $extra, true);
            $raw = $built['raw'];

            $draftsFolder = $this->specialFolder($folders, 'drafts', 'Drafts');
            $oldDraft = (int) input('draft_uid', 0);
            if ($isDraft) {
                $this->imap->append($draftsFolder, $raw, ['\\Draft', '\\Seen']);
                $this->dropDraft($draftsFolder, $oldDraft);
                flash('success', 'Draft saved.');
                redirect('/mails/list', ['folder' => $draftsFolder]);
            }

            $recipients = array_values(array_unique(array_map(static fn ($a) => strtolower($a['email']), $all)));
            $refused = Webmail::smtp($user)->sendMessage($user['email'], $recipients, self::withoutBcc($raw));
            if (count($refused) === count($recipients)) {
                throw new MailException('The message was not sent: the mail server refused every recipient.');
            }
            // Keep a copy in Sent (with Bcc, like other mail programs do).
            $sent = $this->specialFolder($folders, 'sent', 'Sent');
            try {
                $this->imap->append($sent, $raw, ['\\Seen']);
            } catch (MailException $e) {
                error_log('Webmail: could not save to Sent: ' . $e->getMessage());
            }
            if (($rf = input_str('reply_folder')) !== '' && ($ru = (int) input('reply_uid', 0)) > 0 && $this->folderExists($folders, $rf)) {
                $this->imap->select($rf);
                $this->imap->flag([$ru], '\\Answered', true);
            }
            $this->dropDraft($draftsFolder, $oldDraft);
            flash('success', $refused ? 'Message sent, but these addresses were refused: ' . implode(', ', $refused) : 'Message sent.');
            redirect('/mails/list');
        });
    }

    private function dropDraft(string $drafts, int $uid): void
    {
        if ($uid > 0) {
            $this->imap->select($drafts);
            $this->imap->expunge([$uid]);
        }
    }

    /** Bcc must never reach the recipients. */
    private static function withoutBcc(string $raw): string
    {
        [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $head = (string) preg_replace('/^Bcc:.*(\r\n[ \t].*)*\r\n/mi', '', $head . "\r\n");
        return rtrim($head, "\r\n") . "\r\n\r\n" . $body;
    }

    // ------------------------------------------------------------------ actions

    public function action(): string
    {
        $this->user();
        return $this->mail(function (): string {
            $folders = $this->folders();
            $folder = input_str('folder');
            $this->assertFolder($folders, $folder);
            $uids = array_values(array_filter(array_map('intval', (array) ($_POST['uids'] ?? [])), static fn ($u) => $u > 0));
            $op = input_str('op');
            $back = input_str('back') === 'read' && count($uids) === 1 ? ['/mails/read', ['folder' => $folder, 'uid' => $uids[0]]] : ['/mails/list', ['folder' => $folder, 'page' => (int) input('page', 1)]];
            if ($op !== 'empty' && !$uids) {
                flash('warning', 'Select one or more messages first.');
                redirect(...$back);
            }
            $this->imap->select($folder);
            $special = $this->specialOf($folders, $folder);
            $count = count($uids);
            switch ($op) {
                case 'read':
                case 'unread':
                    $this->imap->flag($uids, '\\Seen', $op === 'read');
                    break;
                case 'flag':
                case 'unflag':
                    $this->imap->flag($uids, '\\Flagged', $op === 'flag');
                    break;
                case 'delete':
                    if ($special === 'trash') {
                        $this->imap->expunge($uids);
                        flash('success', "$count message(s) deleted permanently.");
                    } else {
                        $this->imap->move($uids, $this->specialFolder($folders, 'trash', 'Trash'));
                        flash('success', "$count message(s) moved to Trash.");
                    }
                    $back = ['/mails/list', ['folder' => $folder]];
                    break;
                case 'junk':
                    $this->imap->move($uids, $this->specialFolder($folders, 'junk', 'Junk'));
                    flash('success', "$count message(s) moved to Junk.");
                    $back = ['/mails/list', ['folder' => $folder]];
                    break;
                case 'archive':
                    $this->imap->move($uids, $this->specialFolder($folders, 'archive', 'Archive'));
                    flash('success', "$count message(s) archived.");
                    $back = ['/mails/list', ['folder' => $folder]];
                    break;
                case 'move':
                    $to = input_str('to');
                    $this->assertFolder($folders, $to);
                    if ($to !== $folder) {
                        $this->imap->move($uids, $to);
                        flash('success', "$count message(s) moved.");
                    }
                    $back = ['/mails/list', ['folder' => $folder]];
                    break;
                case 'empty':
                    if (!in_array($special, ['trash', 'junk'], true)) {
                        abort(400);
                    }
                    $this->imap->expunge($this->imap->uids());
                    flash('success', 'Folder emptied.');
                    break;
                default:
                    abort(400);
            }
            redirect(...$back);
        });
    }

    public function createFolder(): string
    {
        $this->user();
        return $this->mail(function (): string {
            $name = trim(input_str('name'));
            if ($name === '' || mb_strlen($name) > 60 || preg_match('/[\x00-\x1f\/\\\\*%"]/', $name)) {
                flash('danger', 'Use a folder name of up to 60 characters without / \\ * % or quotes.');
                redirect('/mails/list');
            }
            $folders = $this->folders();
            $delim = $folders[0]['delimiter'] ?? '.';
            $inbox = array_values(array_filter($folders, static fn ($f) => $f['special'] === 'inbox'))[0] ?? null;
            // Servers whose folders live under INBOX (INBOX.Sent) expect new ones there too.
            $prefix = count(array_filter($folders, static fn ($f) => str_starts_with($f['name'], 'INBOX' . $delim))) > 0 && $inbox ? 'INBOX' . $delim : '';
            try {
                $this->imap->create($prefix . ImapClient::encodeName(str_replace($delim, ' ', $name)));
            } catch (MailException $e) {
                flash('danger', $e->getMessage());
                redirect('/mails/list');
            }
            flash('success', "Folder \"$name\" created.");
            redirect('/mails/list', ['folder' => $prefix . ImapClient::encodeName(str_replace($delim, ' ', $name))]);
        });
    }

    // ------------------------------------------------------------------ settings

    public function settings(): string
    {
        $user = $this->user();
        return $this->mail(fn (): string => $this->page('webmail/settings', [
            'title' => 'Settings', 'folders' => $this->folders(), 'folder' => '',
            'prefs' => Webmail::prefs($user['email']),
            'canChangePassword' => (bool) DB::value('SELECT id FROM mailboxes WHERE address = ?', [$user['email']]),
        ]));
    }

    public function saveSettings(): string
    {
        $user = $this->user();
        Webmail::savePrefs($user['email'], trim(input_str('display_name')), (string) ($_POST['signature'] ?? ''), (int) input('page_size', 50));
        flash('success', 'Settings saved.');
        redirect('/mails/settings');
    }

    public function password(): string
    {
        $user = $this->user();
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!hash_equals(Webmail::password($user), $current)) {
            Logger::security('mailbox_password_change', 'failure', "Webmail password change for {$user['email']}: wrong current password", null, substr($user['email'], 0, 190), 'webmail');
            flash('danger', 'Your current password is not correct.');
            redirect('/mails/settings');
        }
        if ($new !== (string) ($_POST['confirm'] ?? '')) {
            flash('danger', 'The new passwords do not match.');
            redirect('/mails/settings');
        }
        $mailbox = DB::one('SELECT id FROM mailboxes WHERE address = ?', [$user['email']]);
        if (!$mailbox) {
            flash('danger', 'The password for this address cannot be changed here. Please contact support.');
            redirect('/mails/settings');
        }
        try {
            EmailService::changeMailboxPassword((int) $mailbox['id'], $new);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            flash('danger', $e instanceof \App\Providers\ProviderException ? $e->publicMessage() : $e->getMessage());
            redirect('/mails/settings');
        }
        $s = Session::get('webmail');
        $s['secret'] = \App\Core\Crypto::encrypt($new);
        Session::set('webmail', $s);
        flash('success', 'Password changed. Use the new password in your mail apps too.');
        redirect('/mails/settings');
    }

    // ------------------------------------------------------------------ helpers

    private function user(): array
    {
        if (!Webmail::enabled()) {
            abort(404);
        }
        $u = Webmail::user();
        if (!$u) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                flash('info', 'Please sign in to continue.');
            }
            redirect('/mails');
        }
        return $u;
    }

    /** Run a mail operation with an IMAP connection; turn server errors into friendly messages. */
    private function mail(callable $fn): string
    {
        try {
            $this->imap = Webmail::imap(Webmail::user() ?? $this->user());
            return $fn();
        } catch (MailException $e) {
            if (!Webmail::user()) {
                flash('warning', $e->getMessage());
                redirect('/mails');
            }
            return $this->page('webmail/error', ['title' => 'Mail error', 'folders' => [], 'folder' => '', 'message' => $e->getMessage()]);
        } catch (\App\Core\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Unexpected problem: log it with a reference the user can quote, keep the webmail usable.
            $ref = strtoupper(bin2hex(random_bytes(3)));
            error_log("Webmail error [$ref] for " . (Webmail::user()['email'] ?? '?') . ': ' . $e);
            http_response_code(500);
            return $this->page('webmail/error', ['title' => 'Mail error', 'folders' => [], 'folder' => '',
                'message' => "Something went wrong while loading your mail. Please try again. If it keeps happening, contact support and quote reference $ref."]);
        }
    }

    private function page(string $template, array $data): string
    {
        $user = Webmail::user();
        $unread = [];
        foreach ($data['folders'] ?? [] as $f) {
            if (in_array($f['special'], ['inbox', 'junk', null], true) && $this->imap) {
                $unread[$f['name']] = $this->imap->status($f['name'])['unseen'];
            }
        }
        return view($template, $data + ['user' => $user, 'unread' => $unread], 'layouts/webmail');
    }

    private ?array $folderCache = null;

    private function folders(): array
    {
        if ($this->folderCache !== null) {
            return $this->folderCache;
        }
        $order = ['inbox' => 0, 'drafts' => 1, 'sent' => 2, 'archive' => 3, 'junk' => 4, 'trash' => 5];
        $f = $this->imap->folders();
        usort($f, static fn ($a, $b) => [$order[$a['special']] ?? 9, strtolower($a['label'])] <=> [$order[$b['special']] ?? 9, strtolower($b['label'])]);
        return $this->folderCache = $f;
    }

    private function folderExists(array $folders, string $name): bool
    {
        foreach ($folders as $f) {
            if ($f['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    private function assertFolder(array $folders, string $name): void
    {
        if (!$this->folderExists($folders, $name)) {
            abort(404, 'That folder does not exist.');
        }
    }

    private function folderParam(array $folders): string
    {
        $name = (string) ($_GET['folder'] ?? 'INBOX');
        if ($name === 'INBOX' || $this->folderExists($folders, $name)) {
            return $name;
        }
        abort(404, 'That folder does not exist.');
    }

    private function folderLabel(array $folders, string $name): string
    {
        foreach ($folders as $f) {
            if ($f['name'] === $name) {
                return $f['special'] === 'inbox' ? 'Inbox' : $f['label'];
            }
        }
        return $name;
    }

    private function specialOf(array $folders, string $name): ?string
    {
        foreach ($folders as $f) {
            if ($f['name'] === $name) {
                return $f['special'];
            }
        }
        return null;
    }

    /** The folder for a role (Sent, Trash...), creating it when the account has none. */
    private function specialFolder(array $folders, string $role, string $fallback): string
    {
        foreach ($folders as $f) {
            if ($f['special'] === $role) {
                return $f['name'];
            }
        }
        $delim = $folders[0]['delimiter'] ?? '.';
        $prefix = count(array_filter($folders, static fn ($f) => str_starts_with($f['name'], 'INBOX' . $delim))) > 0 ? 'INBOX' . $delim : '';
        try {
            $this->imap->create($prefix . $fallback);
        } catch (MailException) {
            // It may exist without being listed as special; use it anyway.
        }
        $this->folderCache = null;
        return $prefix . $fallback;
    }

    private function sendFile(string $data, string $type, string $filename, bool $inline): never
    {
        $safe = (string) preg_replace('/[^\w.\- ()]+/u', '_', $filename) ?: 'download';
        header('Content-Type: ' . $type);
        header('Content-Length: ' . strlen($data));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $safe) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, no-store');
        echo $data;
        exit;
    }
}
