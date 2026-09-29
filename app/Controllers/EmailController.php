<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Settings;
use App\Providers\ProviderException;
use App\Services\EmailService;
use App\Services\PlanLimits;

/**
 * Mailboxes, aliases and routing trace, shared by the admin and customer
 * panels. Subclasses scope which email domains the user may reach.
 */
abstract class EmailController extends Controller
{
    /** Load an email domain the current user may access, or 404. */
    abstract protected function emailDomain(int $id): array;

    abstract protected function isAdmin(): bool;

    abstract protected function base(): string;

    protected function domainPath(int $id): string
    {
        return $this->base() . '/' . $id;
    }

    public function show(int $id): string
    {
        $d = $this->emailDomain($id);
        $mailboxes = DB::all('SELECT * FROM mailboxes WHERE email_domain_id = ? ORDER BY local_part', [$id]);
        $aliases = DB::all('SELECT * FROM email_aliases WHERE email_domain_id = ? ORDER BY local_part', [$id]);
        foreach ($aliases as &$a) {
            $r = EmailService::resolve($a['destination'], $a['address']);
            $a['final'] = $r['mailbox']['address'] ?? null;
            $a['hops'] = count($r['steps']);
        }
        unset($a);
        $usage = $d['customer_id'] ? PlanLimits::summary((int) $d['customer_id']) : null;
        return $this->view('shared/email_domain', [
            'title' => 'Email · ' . $d['name'],
            'domain' => $d,
            'mailboxes' => $mailboxes,
            'aliases' => $aliases,
            'usage' => $usage,
            'base' => $this->domainPath($id),
            'isAdmin' => $this->isAdmin(),
            'canEdit' => $this->canEdit($d),
            'trace' => $this->traceWithin($d, query('trace')),
            'servers' => \App\Mail\Webmail::serversFor($d),
            'serverDefaults' => \App\Mail\Webmail::defaults(),
            'settings' => [
                'webmail' => Settings::get('mail.webmail_url') ?: (Settings::bool('webmail.enabled') ? url('/mails') : ''),
                'imap' => Settings::get('mail.imap_host'),
                'smtp' => Settings::get('mail.smtp_host_display'),
            ],
        ]);
    }

    protected function canEdit(array $domain): bool
    {
        return $domain['status'] !== 'suspended' || $this->isAdmin();
    }

    /** Trace an address on this domain only (so customers cannot probe other domains). */
    private function traceWithin(array $domain, string $q): ?array
    {
        if ($q === '') {
            return null;
        }
        $q = strtolower($q);
        $address = str_contains($q, '@') ? $q : "$q@{$domain['name']}";
        if (!str_ends_with($address, '@' . $domain['name'])) {
            return ['ok' => false, 'summary' => "Only addresses on {$domain['name']} can be traced here.", 'domain' => null, 'steps' => []];
        }
        return EmailService::trace($address);
    }

    // ---- Mailboxes ----------------------------------------------------------

    public function storeMailbox(int $id): string
    {
        $d = $this->emailDomain($id);
        $quota = input_str('quota_mb');
        $this->attempt($id, fn () => EmailService::createMailbox(
            $id,
            input_str('local_part'),
            (string) ($_POST['password'] ?? ''),
            $quota === '' ? null : (ctype_digit($quota) ? (int) $quota : -1),
            mb_substr(input_str('display_name'), 0, 150) ?: null,
            !$this->isAdmin()
        ), 'Mailbox ' . strtolower(input_str('local_part')) . '@' . $d['name'] . ' created.');
    }

    public function updateMailbox(int $id, int $mid): string
    {
        $this->mailboxIn($id, $mid);
        $quota = input_str('quota_mb');
        $this->attempt($id, fn () => EmailService::updateMailbox($mid, mb_substr(input_str('display_name'), 0, 150) ?: null, $quota === '' ? null : (ctype_digit($quota) ? (int) $quota : -1), !$this->isAdmin()), 'Mailbox updated.');
    }

    public function mailboxPassword(int $id, int $mid): string
    {
        $m = $this->mailboxIn($id, $mid);
        if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmation'] ?? '')) {
            $this->failed($this->domainPath($id), ['Passwords do not match.']);
        }
        $this->attempt($id, fn () => EmailService::changeMailboxPassword($mid, (string) ($_POST['password'] ?? '')),
            $m['status'] === 'disabled' ? 'Password set and mailbox enabled.' : 'Mailbox password changed.');
    }

    public function disableMailbox(int $id, int $mid): string
    {
        $m = $this->mailboxIn($id, $mid);
        if ($m['status'] === 'suspended' && !$this->isAdmin()) {
            abort(403);
        }
        $status = $this->isAdmin() && input_str('status') === 'suspended' ? 'suspended' : 'disabled';
        $this->attempt($id, fn () => EmailService::disableMailbox($mid, $status, mb_substr(input_str('reason'), 0, 255)),
            $status === 'suspended' ? 'Mailbox suspended.' : 'Mailbox disabled. Set a new password to enable it again.');
    }

    public function deleteMailbox(int $id, int $mid): string
    {
        $m = $this->mailboxIn($id, $mid);
        if (input_str('confirm') !== $m['address']) {
            $this->failed($this->domainPath($id), ['Type the full address to confirm deletion.']);
        }
        $this->attempt($id, fn () => EmailService::deleteMailbox($mid), "Mailbox {$m['address']} deleted.");
    }

    // ---- Aliases ------------------------------------------------------------

    public function storeAlias(int $id): string
    {
        $this->emailDomain($id);
        $this->attempt($id, fn () => EmailService::createAlias($id, input_str('local_part'), input_str('destination'), !$this->isAdmin()), 'Alias created.');
    }

    public function updateAlias(int $id, int $aid): string
    {
        $this->aliasIn($id, $aid);
        $this->attempt($id, fn () => EmailService::updateAlias($aid, input_str('destination')), 'Alias updated.');
    }

    public function deleteAlias(int $id, int $aid): string
    {
        $a = $this->aliasIn($id, $aid);
        $this->attempt($id, fn () => EmailService::deleteAlias($aid), "Alias {$a['address']} deleted.");
    }

    // ---- Helpers ------------------------------------------------------------

    protected function mailboxIn(int $domainId, int $mid): array
    {
        $d = $this->emailDomain($domainId);
        if (!$this->canEdit($d)) {
            abort(403, 'Email for this domain is suspended.');
        }
        return $this->requireFound(DB::one('SELECT * FROM mailboxes WHERE id = ? AND email_domain_id = ?', [$mid, $domainId]));
    }

    protected function aliasIn(int $domainId, int $aid): array
    {
        $d = $this->emailDomain($domainId);
        if (!$this->canEdit($d)) {
            abort(403, 'Email for this domain is suspended.');
        }
        return $this->requireFound(DB::one('SELECT * FROM email_aliases WHERE id = ? AND email_domain_id = ?', [$aid, $domainId]));
    }

    /** Run an email operation, turning errors into flash messages. */
    protected function attempt(int $domainId, callable $fn, string $success): never
    {
        $d = $this->emailDomain($domainId);
        if (!$this->canEdit($d)) {
            abort(403, 'Email for this domain is suspended.');
        }
        try {
            $fn();
        } catch (ProviderException $e) {
            $this->failed($this->domainPath($domainId), [$this->isAdmin() ? $e->getMessage() : $e->publicMessage()]);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $this->failed($this->domainPath($domainId), [$e->getMessage()]);
        }
        $this->success($this->domainPath($domainId), $success);
    }
}
